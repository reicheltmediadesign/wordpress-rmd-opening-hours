# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- The German translation is now also used when the site language is “Deutsch (Sie)” (`de_DE_formal`); the plugin was shown in English there.

## [0.1.3] – 2026-09-27

### Added

- **Import from text** in the set editor: paste a copied table, list or sentence such as “Dienstag, Donnerstag: 7 - 16 Uhr”; the parser recognises German and English day names, ranges, lists, several slots per day, “geschlossen”, “nach Vereinbarung” and holiday lines, and shows a preview before applying.
- **“Public holidays” row** after Sunday (table, list, compact and paragraphs), backed by a new default rule for all holidays (closed by default) in the Holidays tab. Single holidays can still override it. Toggle per set, block (“Show ‘Public holidays’ row”) or shortcode (`holidays="no"`).
- **Layout “Paragraphs”**: full day names in bold, one line per time slot, note as its own paragraph.
- **Time format “24-hour with unit”**: `08:00 – 12:00 Uhr`.
- Jest tests for the text parser (`npm run test:js`), also run in CI.

## [0.1.2] – 2026-09-27

### Fixed

- Update checks no longer use the GitHub REST API, whose limit of 60 unauthenticated requests per hour and IP address caused “HTTP status code: 403” errors on shared hosting. The plugin now reads a small `update.json` attached to every release.

## [0.1.1] – 2026-09-27

### Changed

- Time fields in the set editor complete short input: `14` → `14:00`, `1430` → `14:30`, `8.5` → `08:05`.
- The opening hours table stretches to the full block width.
- Notices are unstyled by default; the new block style “Box” (class `is-style-box`) restores the highlighted box. Placeholder values are wrapped in spans (`rmd-oh-notice__name` etc.) for styling.
- Visitors can no longer dismiss notices by default (setting remains available).
- Clearer admin notice when the GitHub “Source code” download was installed instead of the release package.

### Fixed

- Saving the settings stripped the allowed HTML (e.g. `<strong>`) from the notice template.

## [0.1.0] – 2026-09-27

### Added

- Opening hours sets with weekly hours, multiple time slots per day, free-text days and per-day notes.
- Planned periods (closed, same hours daily, alternative weekly hours), optionally recurring yearly.
- German public holidays per federal state with per-holiday rules (closed, regular, special hours) and regional opt-ins.
- Blocks and shortcodes: Opening Hours (table, list, compact), Opening Hours Notice, Open Now Status.
- Cache-safe front-end script for notices and live status, with REST fallback.
- Optional schema.org JSON-LD output per set.
- JSON export and import.
- Settings page, custom capability `manage_opening_hours` with configurable roles, uninstall clean-up (opt-in).
- Help page in the admin area.
- Automatic updates from GitHub releases.
