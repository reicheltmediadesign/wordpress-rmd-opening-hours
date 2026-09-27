# RMD Opening Hours

WordPress plugin to manage regular and seasonal opening hours, show them as a block or shortcode, and announce upcoming changes automatically. Built by [reichelt media.design](https://reicheltmedia.design) for small business websites (practices, shops, restaurants, service providers).

## Features

- **Several sets per site**, e.g. "Shop" and "Phone hours" or two locations.
- **Weekly hours with any number of time slots per day** (08:00–12:00 and 14:00–18:00), free-text days ("by appointment"), per-day notes and overnight hours (18:00–02:00).
- **Planned periods**: closed, the same hours every day, or a different weekly schedule for a date range. Optionally repeating every year (e.g. 24 Dec – 2 Jan).
- **German public holidays** calculated per federal state, including regional opt-ins (e.g. Corpus Christi in parts of Saxony). Decide **per holiday** whether you are closed, keep regular hours or open with special hours.
- **Blocks and shortcodes** for the opening hours (table, list or one line), an **automatic notice** that appears a configurable number of days before a period and disappears when it is over, and a **live "open now" status**.
- **Works with page caching**: the page embeds exact time windows and a small script reveals notices and updates the status at the right moment; known caches are also purged on save.
- **Structured data** (schema.org `openingHoursSpecification` and `specialOpeningHoursSpecification`), optional per set.
- **Export/import** all sets and settings as JSON.
- Custom capability `manage_opening_hours`; choose per site which roles get access (administrators always do).
- Built-in help page describing all fields, options and shortcodes.
- Automatic updates from GitHub releases.

## Requirements

WordPress 6.8 or newer, PHP 8.1 or newer.

## Installation

1. Download `rmd-opening-hours.zip` from the latest [release](https://github.com/reicheltmediadesign/wordpress-rmd-opening-hours/releases). **Do not use the “Source code” downloads** – they lack the built block files, translations and the update library.
2. In WordPress go to Plugins → Add New → Upload Plugin, choose the zip and activate it.
3. Open **Opening Hours → Add set**, enter a name (e.g. "Shop") and your weekly hours.
4. Add the blocks to a page, or paste the shortcode shown next to the set.

Later releases are offered under Dashboard → Updates like any other plugin. The check reads a small `update.json` attached to the latest release (no GitHub API calls, so no rate limits on shared hosting).

## Usage

### Sets

A set holds everything for one business or department:

| Tab | What you configure |
| --- | --- |
| Regular hours | Weekly hours. Each day is closed, open (one or more time slots) or a text such as "by appointment". |
| Periods | Date ranges with different hours: holidays, reduced summer hours, extended hours before Christmas. Each period has a name, an optional note and its own lead time for the notice. |
| Holidays | Federal state, regional holidays and one rule per holiday: closed, regular hours or special hours. |
| Display | Default layout, time format and options for this set. Blocks and shortcodes can override them. |
| Structured data | Optional schema.org output with business type, name and URL. |

Which hours apply on a given day: a **closed period** always wins, then a **public holiday** (unless its rule says "regular hours" or the period is marked "also open on public holidays"), then a **period with alternative hours**, then the **regular week**.

### Blocks

| Block | Shows |
| --- | --- |
| Opening Hours | The hours of a set as table, list or compact line. Supports colors, typography and spacing from your theme. |
| Opening Hours Notice | Announcements for upcoming and running periods, e.g. "Summer break (20 July – 2 August): closed". Empty when nothing is due. |
| Open Now Status | "Open now · closes at 18:00" or "Closed now · opens tomorrow at 09:00", updated live. |

### Shortcodes

```
[rmd_opening_hours set="shop"]
[rmd_opening_hours set="shop" layout="compact" week="regular" time_style="24h-short"]
[rmd_opening_hours_notice set="shop" lead_days="14"]
[rmd_opening_hours_status set="shop" next="no"]
```

| Attribute | Values | Applies to |
| --- | --- | --- |
| `set` | Set slug or ID; empty = default set, `all` (notice only) = every set | all |
| `layout` | `table`, `list`, `compact` | opening hours |
| `week` | `current` (with holidays and periods), `regular` | opening hours |
| `group` | `yes`, `no` – merge consecutive days with equal hours (Mon–Fri) | opening hours |
| `today` | `yes`, `no` – highlight today | opening hours |
| `notes` | `yes`, `no` – show notes | opening hours |
| `time_style` | `24h`, `24h-short`, `12h` | opening hours |
| `title` | `yes`, `no` – show the set name | opening hours |
| `lead_days` | Number of days before a period starts; `0` = plugin setting | notice |
| `template` | Text with placeholders `{name}`, `{start}`, `{end}`, `{hours}`, `{note}`, `{set}` | notice |
| `dismissible` | `yes`, `no` | notice |
| `next` | `yes`, `no` – show next opening/closing time | status |
| `class` | Extra CSS classes | all |

### Settings

Under **Opening Hours → Settings**: default set, federal state for new sets, time format of the status, lead time and text template of notices, whether visitors may dismiss notices, how far ahead notices and status intervals are embedded for cached pages, structured data on every page, **which roles may manage opening hours** (administrators always can; e.g. add Editor or Shop Manager), and whether all data is removed when the plugin is deleted.

**Opening Hours → Help** explains every field, the holiday rules, blocks, shortcode attributes and placeholders directly in the admin area.

**Opening Hours → Import/Export** downloads a JSON file with all sets and settings, and imports such a file after showing a preview. Sets are matched by slug.

## Styling with CSS

All markup uses the `rmd-oh` prefix and a handful of CSS custom properties. Override them in your theme, for example:

```css
.rmd-oh {
	--rmd-oh-today-bg: #fff3cd;
	--rmd-oh-border: 1px solid #ddd;
}
.rmd-oh-notice {
	--rmd-oh-notice-bg: #e8f4fd;
	--rmd-oh-notice-border: 1px solid #9ec9ee;
}
.rmd-oh-status {
	--rmd-oh-open-color: #1e7e34;
	--rmd-oh-closed-color: #b02a37;
}
```

Useful classes: `.rmd-oh--table` / `--list` / `--compact`, `.rmd-oh__row.is-today`, `.is-closed`, `.is-exception` (holiday or period), `.rmd-oh__note`, `.rmd-oh-status.is-open` / `.is-closed`, `.rmd-oh-notice.is-running`. The full list is documented at the top of `assets/css/front.css`.

Notices are unstyled by default so they inherit your theme. Choose the block style **Box** (or add `class="is-style-box"` to the shortcode) for a highlighted box, or target the parts of the text: `.rmd-oh-notice__name`, `__start`, `__end`, `__hours`, `__note`, `__set`.

## Developer hooks

- Filter `rmd_oh_jsonld( array $graph, SetData $set )` – change or suppress the structured data of a set.
- Action `rmd_oh_cache_purged` – runs after the plugin asked known page caches to purge; hook custom caches here.
- REST (read-only, public): `GET /wp-json/rmd-opening-hours/v1/sets`, `/status/{slug}`, `/notices/{slug|all}`.
- Constant `RMD_OH_FAKE_NOW` (only with `WP_DEBUG`) sets a fake current time, e.g. `define( 'RMD_OH_FAKE_NOW', '2026-12-24 09:30' );`, to preview holidays and notices.

## Privacy

The plugin stores no personal data and makes no external requests, except for checking GitHub for plugin updates from the WordPress admin. Dismissed notices are remembered in the visitor's browser (`localStorage`) only.

## License

GPL-2.0-or-later. Uses [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT).
