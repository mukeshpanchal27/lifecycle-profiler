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

Time your own code with two functions:

    lifecycle_profiler_start( 'my-plugin: build menu' );
    build_menu();
    $ms = lifecycle_profiler_stop( 'my-plugin: build menu' );

Outside a profiling session, both functions do nothing, and `lifecycle_profiler_stop()` returns `null`. The report shows each span with its total time and number of calls.

= Privacy =

The plugin sends no data to any other site. Reports stay in your database, in options with autoload off. A report holds the URL of the profiled request and the ID of the user who profiled it. Deleting the plugin deletes all its data.

== Installation ==

1. Install the plugin from the Plugins screen, or upload the `lifecycle-profiler` folder to `wp-content/plugins/`.
2. Activate the plugin.
3. On activation, the plugin writes the file `0-lifecycle-profiler-loader.php` to `wp-content/mu-plugins/`. If WordPress cannot write there, an admin notice says so. Make the folder writable, then load any admin page. The plugin tries again once an hour.

== Frequently Asked Questions ==

= Why does the plugin add a file to mu-plugins? =

The file loads the profiler before regular plugins, so it can time how long each plugin takes to load. The plugin adds the file on activation and removes it on deactivation.

= Does the plugin slow down my site? =

Without a session, it adds about 1 ms to a page. During a session, it adds about 18 ms, or about 40%, because it times every hook callback. Only the administrator who started the session pays that cost, and only for one hour.

= Are the times exact? =

The times include the profiler's own cost, so a profiled page looks slightly slower than it is. Compare reports made the same way. The difference between two reports is more useful than one number.

= Who can start a session? =

Users with the `manage_options` capability, usually administrators. Other visitors are never profiled. On multisite, site administrators can profile their own site.

= Why does a REST request have no Server-Timing header? =

WordPress treats a REST request that uses cookies but no nonce as logged out. Send the `X-WP-Nonce` header with the request.

= Which requests save a report? =

Every page load during a session, except AJAX, cron and REST requests, and the plugin's own screen and actions. The plugin keeps the newest 20 reports. Change the number with the `lifecycle_profiler_max_reports` filter.

= What does uninstall remove? =

All plugin options on every site, including every report, and the mu-plugin file.

== Screenshots ==

1. Tools > Profiler lists the saved reports, then the time of each plugin, theme and core in the chosen report.
2. The template, the template parts and the time of each block type.
3. The compare screen shows the change between two reports, with the largest change first.
4. The admin bar item starts and stops a profiling session.

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
