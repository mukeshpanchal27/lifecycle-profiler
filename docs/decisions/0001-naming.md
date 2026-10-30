# 0001: Plugin name, slug, prefix and minimum versions

- Status: Accepted
- Date: 2026-10-30
- Issue: #1

## Decision

| Item | Value |
| --- | --- |
| Plugin name | Lifecycle Profiler |
| Short label in the interface | Profiler |
| wordpress.org slug and text domain | `lifecycle-profiler` |
| PHP namespace | `LifecycleProfiler` |
| Function prefix | `lifecycle_profiler_` |
| Constant prefix | `LIFECYCLE_PROFILER_` |
| Option, transient and meta prefix | `lifecycle_profiler_` |
| Minimum PHP version | 7.4 |
| Minimum WordPress version | 6.8 |

## Why

The slug `lifecycle-profiler` was free on wordpress.org on 2026-10-30. The plugin information API returned "Plugin not found" for it.

"Profiler" alone is too generic. Other plugins already use names such as Plugin Profiler and Code Profiler. A short prefix like `profiler_` or a class named `Profiler` could clash with other code. The full name `lifecycle_profiler_` is long, but it is very unlikely to clash.

The name leaves out "WordPress" because of the wordpress.org trademark rules.

PHP 7.4 is the minimum PHP version that WordPress itself supports.

WordPress 7.1 is the latest release. A minimum of 6.8 covers the last four major releases, and the plugin needs no newer API. We can raise the minimum later if a feature needs it.
