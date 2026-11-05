# Lifecycle Profiler

Lifecycle Profiler is a WordPress plugin that times the whole request. It measures bootstrap, the load time of each plugin and every hook callback. It sends the results in the `Server-Timing` header and offers a timing API.

## Status

Version 1.0.0 is ready for wordpress.org, but it is not submitted yet. See the [milestones](https://github.com/mukeshpanchal27/lifecycle-profiler/milestones) for the roadmap.

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

## Data and uninstall

The plugin sends no data to any other site. A report holds the URL of the profiled request, the ID of the user who profiled it, and the times. Reports stay in the database, in options with autoload off.

Deleting the plugin removes every option that starts with `lifecycle_profiler_`, on every site of a network, and the loader file in `mu-plugins/`.

## Limits

- A hook callback that takes an argument by reference gets a copy during a session. Changes it makes to an array or scalar argument are lost. Objects are not affected.
- During a session, each response is buffered in memory until shutdown, so the header can be sent.
- AJAX, cron and REST requests do not save a report.
- Two profiled requests that end at the same moment can both update the report list. One of the two reports may then be missing from the list.
- A request that ends before `init` sends no header and saves no report, because the profiler cannot confirm the user yet.
- On multisite, every site administrator can profile their own site. The report then shows the network plugins' callbacks, file paths and times.
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

Reports leave out the profiler's own time. The profiler adds up the time its own code runs, and every time in a report subtracts it. Two costs happen outside the profiler's code: WordPress calling the profiler for every hook, and calling each callback wrapper. The profiler times both once when the session starts, and subtracts them too. Each report shows how much time it left out, and the `Server-Timing` header sends it as `lp-overhead`.

On `/sample-page/`, the report total was within about 1 ms of the server time without a session, in 5 of 6 runs of 20 requests each. Before this change, reports were about 6 ms, or 13%, slower than the page.

To measure your own site, start wp-env and run:

```sh
bin/measure-overhead.sh /sample-page/ 100
```

## Requirements

- PHP 7.4 or later
- WordPress 6.8 or later

## Local development

You need Node.js and Docker.

Run the tools with `npm exec --no --`, not `npx`. `npx` downloads a package that is not installed and runs its install scripts. `npm exec --no --` runs only the packages in `package-lock.json`, and fails if one is missing.

```sh
npm ci
composer install
npm exec --no -- wp-env start
```

`npm ci` installs exactly the versions in `package-lock.json`.

The site runs at http://localhost:8888 with the plugin active. Log in with `admin` and `password`.

A second site has the [Excimer](https://www.mediawiki.org/wiki/Excimer) PHP extension installed, for testing flame graphs:

```sh
npm exec --no -- wp-env start --config=.wp-env.excimer.json
```

This site runs at http://localhost:8890.

### Coding standards

PHPCS checks the code against the WordPress coding standards and PHP 7.4 compatibility. The rules are in `phpcs.xml.dist`.

```sh
composer lint
composer format
```

`composer format` fixes what PHPCBF can fix by itself.

### Tests

The PHPUnit tests run in the wp-env tests container with the WordPress core test suite. Start wp-env and install the Composer packages first.

```sh
composer install
npm run test:php
npm run test:php:multisite
```

PHPUnit uses the database of the tests site at http://localhost:8889, and it reinstalls WordPress there on every run. Run `npm exec --no -- wp-env clean tests` to get that site back.

The end-to-end tests use Playwright with Chromium, against the development site at http://localhost:8888. They log in as `admin` and turn a profiling session on and off. Set `WP_BASE_URL` to use another site.

```sh
npm exec --no -- playwright install chromium
npm run test:e2e
```

## Continuous integration

GitHub Actions runs these checks on every pull request and on trunk:

| Workflow | What it checks |
| --- | --- |
| PHP lint | Syntax on PHP 7.4 to 8.5 |
| Coding standards | PHPCS |
| PHPUnit | The PHPUnit tests, as a single site and as a multisite |
| End-to-end tests | The Playwright tests |
| Plugin Check | The release files, with the official [Plugin Check](https://github.com/wordpress/plugin-check-action) tool. It posts its results on the pull request. |
| Spell check | Misspellings, with [typos](https://github.com/crate-ci/typos). Allowed words are in `.github/typos.toml`. |
| Build zip | Builds the release zip and checks its files |

Every action is pinned to a full commit hash, with the exact tag name in a comment, for example `actions/checkout@3d3c42e5… # v7.0.1`. A tag can move to other code, but a hash cannot. Dependabot proposes updates for actions, Composer and npm every Monday. It waits two weeks after a Composer or npm release.

## Releasing

See [docs/releasing.md](docs/releasing.md). In short:

- `bin/build-zip.sh` builds `build/lifecycle-profiler.zip` from the files that `.distignore` does not exclude.
- `npm run assets:wordpress-org` makes the banners, icons and screenshots in `.wordpress-org/`.
- Pushing a version tag such as `v1.0.0` deploys the release to wordpress.org.

## Documentation

- [Contributing](CONTRIBUTING.md)
- [Security policy](SECURITY.md): how to report a security problem privately
- [Code of conduct](CODE_OF_CONDUCT.md)
- [0001: Plugin name, slug, prefix and minimum versions](docs/decisions/0001-naming.md)
- [0002: How to time template parts](docs/decisions/0002-template-part-timing.md)
- [Security review](docs/security-review.md)
- [Releasing](docs/releasing.md)

## License

[GPL-3.0-or-later](LICENSE)
