=== Lifecycle Profiler ===
Contributors:      mukesh27
Tags:              performance, profiler, server-timing, debug, hooks
Requires at least: 6.8
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPL-3.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-3.0.html

Times the whole WordPress request, from bootstrap and plugin load to every hook callback.

== Description ==

Lifecycle Profiler shows where a WordPress request spends its time. It measures bootstrap, the load time of each plugin, every hook callback, the template, template parts and blocks. It saves each run as a report, so you can compare a page before and after a change. It also sends the results in the `Server-Timing` header.

While the plugin is active, every request by an administrator is profiled. Visitors and other users are never profiled. To stop profiling, deactivate the plugin.

= How to use it =

1. Activate the plugin.
2. Log in as an administrator, and load the page you want to profile.
3. Click "Profiler" in the admin bar. It opens the report of that page.

= Saved reports =

Each profiled page load saves a report. Tools > Profiler lists the newest 20 reports. You can give each report a label, delete it or download it as JSON.

Click "Compare reports" to see two reports side by side. Each row shows the change in milliseconds, with the largest change first.

= What it measures =

* The main phases of the request, from bootstrap to shutdown.
* The load time of each mu-plugin and plugin.
* The time of every hook callback, added up for each plugin, theme and core.
* The 20 slowest hook callbacks, with the hook, the priority and where each one is defined.
* The template, each template part and each block type.
* Custom spans from your own code, through the timing API.

= Timing API =

Time your own code with two functions:

    lifecycle_profiler_start( 'my-plugin: build menu' );
    build_menu();
    $ms = lifecycle_profiler_stop( 'my-plugin: build menu' );

Outside a profiled request, both functions do nothing, and `lifecycle_profiler_stop()` returns `null`. The report shows each span with its total time and number of calls.

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

For visitors, it adds about 1 ms or less to a page. Requests by administrators are profiled, and each one takes about 18 ms longer, or about 36%, because the profiler times every hook callback. Deactivate the plugin when you are done profiling.

= How is it different from Query Monitor? =

Query Monitor shows what happened during one request, such as database queries, HTTP calls and PHP errors. Lifecycle Profiler shows where the time went, down to each hook callback, and saves reports so you can compare runs. The two plugins work well together.

= Are the times exact? =

The profiler leaves its own time out of every number. Each report says how much time it left out. On a test page, report totals were within about 1 ms of the page time without the profiler. Small differences remain, so compare reports made the same way. The change between two reports is more useful than one number.

= Why is time to first byte so slow while profiling? =

During a profiled request, the plugin holds the whole page until the request ends, so it can send the `Server-Timing` header. The browser gets the first byte only then, so time to first byte equals the full request time. Measure time to first byte while logged out, or with the plugin inactive.

= Why does the same page show different times? =

Every request varies a little, because of caches, the database and other work on the server. In one test, an unchanged page took from 46 to 122 ms. Profile each side of a change several times, and compare typical runs.

= Are all mu-plugins timed? =

Most are. WordPress loads mu-plugins in file name order, and the profiler starts in `0-lifecycle-profiler-loader.php`. An mu-plugin whose name sorts before it, such as `00-cache.php`, loads before the profiler, so its load time is not measured.

= Whose requests are profiled? =

Requests by users with the `manage_options` capability, usually administrators. Visitors and other logged-in users are never profiled. On multisite, site administrators are profiled on their own site.

= How do I stop profiling? =

Deactivate the plugin. There is no on and off switch, because an active plugin means you want to profile.

= Why does the plugin set a cookie? =

The profiler decides whether to profile a request before other plugins load, when WordPress cannot check the login yet. So when an administrator logs in, the plugin sets a signed cookie. It lasts as long as the login, and logging out removes it. An administrator who was already logged in gets the cookie on their next page load.

= Why does a REST request have no Server-Timing header? =

WordPress treats a REST request that uses cookies but no nonce as logged out. Send the `X-WP-Nonce` header with the request.

= Which requests save a report? =

Every page load by an administrator, except AJAX, cron and REST requests, and the plugin's own screen and actions. The plugin keeps the newest 20 reports. Change the number with the `lifecycle_profiler_max_reports` filter.

= What does uninstall remove? =

All plugin options on every site, including every report, and the mu-plugin file.

== Screenshots ==

1. Tools > Profiler lists the saved reports, then the time of each plugin, theme and core in the chosen report.
2. The slowest hook callbacks, with the hook, the priority and where each one is defined.
3. The template, the template parts and the time of each block type.
4. The compare screen shows the change between two reports, with the largest change first.
5. The admin bar item opens the report of the current page and shows its request time.

== Changelog ==

= 1.0.0 =

* First release on wordpress.org.
* Profile every request by an administrator while the plugin is active, without an on and off switch. The admin bar item now opens the report of the current page and shows its request time.
* Delete all plugin data on uninstall, on every site of a network.
* Show source types in the site language.
* Explain time to first byte while profiling, run-to-run noise and early mu-plugins, and note on the compare screen that single runs vary.
* Add a shutdown phase, so the phases add up to the total time.
* Show the 20 slowest hook callbacks on the report screen, with where each one is defined.
* Tell reports of the same page apart in the compare lists, with seconds and the total time.
* On multisite, keep the mu-plugin file while another site still uses the plugin.
* Leave the profiler's own time out of every time in a report, and show how much was left out.
* Use the right plural form in the report limit text.
* Stop the mu-plugin file when it is opened directly, and update it on the next admin page load when it is out of date.
* Show an info notice when there are no reports, or too few to compare.
* Stop profiling when the plugin is inactive but its mu-plugin file is still there.
* Send the header and save the report only after WordPress confirms the user.
* Keep percent-encoded characters in report URLs.
* Label callbacks that cannot be looked up as "Unknown" instead of PHP.
* Add PHPUnit and end-to-end tests.

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

== Upgrade Notice ==

= 1.0.0 =

First release on wordpress.org. Profiling now runs for every administrator while the plugin is active, so deactivate it when you are done. Saved reports from 0.3 stay in place.
