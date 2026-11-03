# Security review

- Date: 2026-11-03
- Version: 0.3.0, before 1.0.0
- Issue: #41

This review covers every output, form, action and input in the plugin. Each row says what protects it.

## Actions

Every action runs through `admin-post.php`. No action is registered for logged-out users, so a logged-out request gets a 400 from WordPress.

| Action | Method | Capability | Nonce | Result |
| --- | --- | --- | --- | --- |
| `lifecycle_profiler_toggle`: start or stop a session | GET link | `manage_options` | `lifecycle_profiler_toggle` | Pass |
| `lifecycle_profiler_label`: change a report label | POST | `manage_options` | One nonce for each report | Pass |
| `lifecycle_profiler_delete`: delete a report | POST | `manage_options` | One nonce for each report | Pass |
| `lifecycle_profiler_export`: download a report | GET link | `manage_options` | One nonce for each report | Pass |

The three report actions check the capability before the nonce, and stop with a 404 if the report does not exist. A nonce made for one report does not work for another report. The PHPUnit and manual tests for #31 and #35 checked this.

## Screens

| Screen | Protection | Result |
| --- | --- | --- |
| Tools > Profiler, the report and compare views | `add_management_page()` with `manage_options` | Pass |
| Admin bar item | Shown only to users with `manage_options` | Pass |
| Loader notice | Shown only to users with `activate_plugins` | Pass |

## Input

| Input | Where | Handling | Result |
| --- | --- | --- | --- |
| `report`, `base`, `target` | Screens and actions | `sanitize_key()`, then looked up in the report index | Pass |
| `view` | Report screen | Strict comparison with a constant | Pass |
| `label` | Label action | `sanitize_text_field()` | Pass |
| `action` | Save check at shutdown | `sanitize_key()`, read only to skip saving | Pass |
| Session cookie | Every request | `sanitize_text_field()`, then a strict format check and `hash_equals()` on an HMAC-SHA256 signature | Pass |
| `REQUEST_URI`, `REQUEST_METHOD` | Saved report | `esc_url_raw()` and `sanitize_key()` | Fixed in this review |
| `REQUEST_TIME_FLOAT` | Request start time | Cast to float | Pass |

**Fixed:** the URL used `sanitize_text_field()`, which removes percent-encoded characters. `/?s=hello%20world` was saved as `/?s=helloworld`. The URL now uses `esc_url_raw()`, which keeps them.

## Output

| Output | Handling | Result |
| --- | --- | --- |
| HTML on all screens | `esc_html()`, `esc_attr()`, `esc_url()` and `esc_js()` at the point of output | Pass |
| Text with markup from translations | `esc_html__()` for the text, and markup added only around escaped values | Pass |
| Loader notice | `wp_admin_notice()`, which also runs `wp_kses_post()` | Pass |
| `Server-Timing` header | Metric names limited to letters, digits, `-` and `_`. Durations are floats. | Pass |
| JSON download | `wp_json_encode()`, `Content-Type: application/json`, and an attachment file name built from a date and a hex ID | Pass |

**Fixed:** the JSON download now also sends `X-Content-Type-Options: nosniff`.

## Sessions

- The cookie is `HttpOnly` and `SameSite=Lax`. It is `Secure` on HTTPS sites. It lasts one hour.
- The signing secret is 32 random bytes, stored in the `lifecycle_profiler_session_secret` option.
- Before `init`, the check can only verify the signature and the expiry, because users are not loaded yet. From `init` on, the current user must match the cookie and have `manage_options`.

**Fixed:** a request that ended before `init` still sent the header and saved the report, with only the cookie checked. The header and the save now need `is_session_confirmed()`, which requires that `init` ran. A copied cookie on its own can still make the timers run for that request. That costs only time, and the request gets no results.

## Files

- The loader file is written only with direct file access, through `WP_Filesystem`. Its plugin path is written with `var_export()`, so it is a safe PHP string.
- The loader loads `early.php` only if the file is readable, and `early.php` stops if the plugin is not active.

## Open points

These points are decisions, not defects. They are left as they are for 1.0.0.

- **Multisite site admins:** on a network, a site admin has `manage_options` on their site. That admin can profile the site and see the callbacks, file paths and times of network plugins. Super admins may want to limit this to themselves.
- **Query strings in reports:** a report saves the full request URL. If an administrator profiles a URL with a secret in its query string, the secret is saved in the report and in its JSON download. Only administrators can see reports.
