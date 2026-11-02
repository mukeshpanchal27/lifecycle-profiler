# Lifecycle Profiler

Lifecycle Profiler is a WordPress plugin that times the whole request. It measures bootstrap, the load time of each plugin and every hook callback. It sends the results in the `Server-Timing` header and offers a timing API.

## Status

Version 0.1.0 has core profiling. See the [milestones](https://github.com/mukeshpanchal27/lifecycle-profiler/milestones) for the roadmap.

## Usage

1. Click "Profiler: OFF" in the admin bar. It changes to "Profiler: ON".
2. Load the page you want to profile.
3. Open Tools > Profiler to see the report.
4. Click "Profiler: ON" to stop.

Only users with the `manage_options` capability can start a session. Other visitors are never profiled.

The report shows:

- the main phases of the request, from bootstrap to shutdown
- load time and hook time for each plugin, theme and core
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

On activation, the plugin writes `0-lifecycle-profiler-loader.php` to `mu-plugins/`. The loader runs before regular plugins, so the profiler can time plugin load. During a session, the profiler wraps every hook callback in a timer. At shutdown it saves the report to the `lifecycle_profiler_latest_report` option.

## Limits

- A hook callback that takes an argument by reference gets a copy during a session. Changes it makes to an array or scalar argument are lost. Objects are not affected.
- During a session, each response is buffered in memory until shutdown, so the header can be sent.
- AJAX, cron and REST requests are not saved as the latest report.

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

## License

[GPL-3.0-or-later](LICENSE)
