=== RMD Opening Hours ===
Contributors: reicheltmediadesign
Tags: opening hours, business hours, holidays, block, shortcode
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage regular and seasonal opening hours, show them as a block or shortcode, and announce upcoming changes automatically.

== Description ==

RMD Opening Hours lets site owners maintain their own opening hours without touching page content:

* Several sets per site (e.g. "Shop" and "Phone hours"), each with weekly hours and any number of time slots per day.
* Planned periods with different hours or closures (holidays, seasonal hours), optionally repeating every year.
* German public holidays per federal state, configurable per holiday: closed, regular hours or special hours.
* Blocks and shortcodes: opening hours table/list/one-liner, an automatic notice for upcoming changes, and a live "open now" status.
* Notices appear a configurable number of days ahead and disappear automatically; works with page caching.
* Optional schema.org structured data (openingHoursSpecification).
* JSON export/import to move sets between sites.

== Installation ==

1. Upload the plugin zip under Plugins → Add New → Upload, or copy the folder to `wp-content/plugins/`.
2. Activate the plugin.
3. Go to Opening Hours → Add set and enter your hours.
4. Add the blocks to a page or use the shortcodes shown next to each set.

== Frequently Asked Questions ==

= Which shortcodes are available? =

`[rmd_opening_hours set="shop" layout="table"]`, `[rmd_opening_hours_notice set="shop"]` and `[rmd_opening_hours_status set="shop"]`. The set slug is shown in the set list.

= Do notices work with caching plugins? =

Yes. Upcoming notices are embedded (hidden) in the page with their exact display window and revealed by a small script at the right time. Known page caches are also purged when hours are saved and once a day.

== Changelog ==

= 0.1.6 =
* Fixed: period dates are checked when saving instead of while typing; a period with invalid dates is kept for correction (marked "not used") and ignored until then.

= 0.1.5 =
* New: option "Day names" (display tab, block, shortcode attribute days) to show weekdays abbreviated ("Mon") or in full ("Monday").

= 0.1.4 =
* Fixed: the German translation is also used when the site language is "Deutsch (Sie)" (de_DE_formal).

= 0.1.3 =
* New: import opening hours from pasted text (tables, lists, sentences) in the set editor.
* New: "Public holidays" row after Sunday with a default rule for all holidays (closed by default).
* New: layout "Paragraphs" and time format "24-hour with unit" (08:00 – 12:00 Uhr).

= 0.1.2 =
* Update checks read a metadata file attached to each release instead of the rate-limited GitHub API (fixes "HTTP status code: 403" on shared hosting).

= 0.1.1 =
* Time fields complete short input (14 → 14:00).
* Opening hours table uses the full block width.
* Notices are unstyled by default; new block style "Box" for a highlighted box. Placeholder values get their own CSS classes.
* Notices are no longer dismissible by default.
* Clearer message when the GitHub source download was installed instead of the release package.
* Fixed: saving the settings removed the allowed HTML from the notice template.

= 0.1.0 =
* Initial release.
