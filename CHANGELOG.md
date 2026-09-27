# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
