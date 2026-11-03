# Lifecycle Profiler

Lifecycle Profiler is a WordPress plugin that times the whole request. It measures bootstrap, the load time of each plugin and every hook callback. It sends the results in the `Server-Timing` header and offers a timing API.

## Status

Version 1.0.0 is the first release for wordpress.org. See the [milestones](https://github.com/mukeshpanchal27/lifecycle-profiler/milestones) for the roadmap.

## Usage

1. Click "Profiler: OFF" in the admin bar. It changes to "Profiler: ON".
2. Load the page you want to profile.
3. Open Tools > Profiler to see the report.
4. Click "Profiler: ON" to stop.

Each profiled page load saves a report. Tools > Profiler lists the newest 20 reports. You can give each report a label, delete it or download it as JSON. Click **Compare reports** to see two reports side by side, with the change for each row, largest change first.

Only users with the `manage_options` capability can start a session. Other visitors are never profiled.

The report shows:

- the main phases of the request, from bootstrap to shutdown
- load time and hook time for each plugin, theme and core
- the template, each template part and each block type, slowest first
- custom spans from the timing API

During a session, each response also has a `Server-Timing` header with the phase times. Your browser's developer tools show it in the network panel.

## Timing API

```php
lifecycle_profiler_start( 'my-plugin: build menu' );
build_menu();
$ms = lifecycle_profiler_stop( 'my-plugin: build menu' );
```

Outside a profiling session, both functions do nothing, and `lifecycle_profiler_stop()` returns `null`.

## How it works

On activation, the plugin writes `0-lifecycle-profiler-loader.php` to `mu-plugins/`. The loader runs before regular plugins, so the profiler can time plugin load. During a session, the profiler wraps every hook callback in a timer. At shutdown it saves the report in its own option, `lifecycle_profiler_report_{id}`, with autoload off. The `lifecycle_profiler_reports` option lists the saved reports. The `lifecycle_profiler_max_reports` filter changes how many reports to keep.

## Limits

- A hook callback that takes an argument by reference gets a copy during a session. Changes it makes to an array or scalar argument are lost. Objects are not affected.
- During a session, each response is buffered in memory until shutdown, so the header can be sent.
- AJAX, cron and REST requests do not save a report.
- Two profiled requests that end at the same moment can both update the report list. One of the two reports may then be missing from the list.
- Some template files are not timed as template parts, such as the file `comments_template()` loads. See [0002: How to time template parts](docs/decisions/0002-template-part-timing.md).

## Overhead

Without a session, the plugin adds about 1 ms to a page for visitors. During a session, it adds about 18 ms, or about 40%, because it wraps and times every hook callback.

| Case | Logged out | Administrator |
| --- | --- | --- |
| Plugin inactive | 42.6 ms | 44.4 ms |
| Plugin active, no session | 43.8 ms (+1.2 ms) | 47.8 ms (+3.4 ms) |
| Plugin active, session | Not possible | 61.9 ms (+17.5 ms) |

Each number is the median response time of `/sample-page/`, averaged over three runs. Each run made 100 requests per case after 10 warm-up requests. The site was the wp-env development site with PHP 7.4, WordPress 7.1.4 and Twenty Twenty-Five, in Docker on macOS.

The results change from run to run by up to about 4 ms. The administrator case without a session showed +0.8 ms to +4.9 ms. The session case gives the most stable result: +16.3 ms to +18.5 ms.

Session times include the profiler's own cost, so a report shows a slightly slower page than the page really is.

To measure your own site, start wp-env and run:

```sh
bin/measure-overhead.sh /sample-page/ 100
```

## Requirements

- PHP 7.4 or later
- WordPress 6.8 or later

## Local development

You need Node.js and Docker.

```sh
npm install
npx wp-env start
```

The site runs at http://localhost:8888 with the plugin active. Log in with `admin` and `password`.

A second site has the [Excimer](https://www.mediawiki.org/wiki/Excimer) PHP extension installed, for testing flame graphs:

```sh
npx wp-env start --config=.wp-env.excimer.json
```

This site runs at http://localhost:8890.

### Tests

The PHPUnit tests run in the wp-env tests container with the WordPress core test suite. Start wp-env and install the Composer packages first.

```sh
composer install
npm run test:php
npm run test:php:multisite
```

PHPUnit uses the database of the tests site at http://localhost:8889, and it reinstalls WordPress there on every run. Run `npx wp-env clean tests` to get that site back.

The end-to-end tests use Playwright with Chromium, against the development site at http://localhost:8888. They log in as `admin` and turn a profiling session on and off. Set `WP_BASE_URL` to use another site.

```sh
npx playwright install chromium
npm run test:e2e
```

## License

[GPL-3.0-or-later](LICENSE)
