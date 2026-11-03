=== Lifecycle Profiler ===
Contributors:      mukesh27
Tags:              performance, profiler, server-timing, debug, hooks
Requires at least: 6.8
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        0.3.0
License:           GPL-3.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-3.0.html

Times the whole WordPress request, from bootstrap and plugin load to every hook callback.

== Description ==

Lifecycle Profiler shows where a WordPress request spends its time. It measures bootstrap, the load time of each plugin and every hook callback. It sends the results in the `Server-Timing` header and shows them on a Tools screen.

Profiling runs only for administrators who turn it on. Other visitors are never profiled.

= How to use it =

1. Click "Profiler: OFF" in the admin bar. It changes to "Profiler: ON".
2. Load the page you want to profile.
3. Open Tools > Profiler to see the report.
4. Click "Profiler: ON" to stop.

= Saved reports =

Each profiled page load saves a report. Tools > Profiler lists the newest 20 reports. You can give each report a label, delete it or download it as JSON.

Click "Compare reports" to see two reports side by side. Each row shows the change in milliseconds, with the largest change first.

= What it measures =

* The main phases of the request, from bootstrap to shutdown.
* The load time of each mu-plugin and plugin.
* The time of every hook callback, added up for each plugin, theme and core.
* The template, each template part and each block type.
* Custom spans from your own code, through the timing API.

= Timing API =

    lifecycle_profiler_start( 'my-plugin: build menu' );
    build_menu();
    lifecycle_profiler_stop( 'my-plugin: build menu' );

Outside a profiling session, these functions do nothing.

== Frequently Asked Questions ==

= Why does the plugin add a file to mu-plugins? =

The file loads the profiler before regular plugins, so it can time how long each plugin takes to load. The plugin adds the file on activation and removes it on deactivation.

= Why does a REST request have no Server-Timing header? =

WordPress treats a REST request that uses cookies but no nonce as logged out. Send the `X-WP-Nonce` header with the request.

== Changelog ==

= 0.3.0 =

* Keep the newest 20 reports, each with a label, instead of only the latest one.
* Delete a report from the report list.
* Compare two reports side by side, with the change for each phase, plugin, hook, template part and block.
* Download a report as a JSON file.
* Move the report saved by 0.2 into the new report list.

= 0.2.0 =

* Time the template that WordPress loads, and name the block template in block themes.
* Time each template part, as a file in classic themes and as a block in block themes.
* Time block rendering for each block type, with total and self time.
* Show templates, template parts and blocks on Tools > Profiler, slowest first.
* Write the mu-plugin loader on an admin page load when it is missing.

= 0.1.0 =

* Add an mu-plugin loader that runs before regular plugins.
* Add profiling sessions with a signed cookie, started from the admin bar.
* Time the load of each mu-plugin, network plugin and plugin.
* Time the main phases of the request.
* Time every hook callback, with total and self time.
* Attribute each callback to its plugin, theme, core, drop-in or PHP.
* Send phase times in the `Server-Timing` header.
* Save the latest report and show it on Tools > Profiler.
* Add a timing API for custom spans.

= 0.0.1 =

* Initial plugin shell.
