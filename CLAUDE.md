# RMD Opening Hours – Projektregeln

WordPress-Plugin von reichelt media.design für Kunden-Websites. Repo: `github.com/reicheltmediadesign/wordpress-rmd-opening-hours`, Lizenz GPL-2.0-or-later, Autor Philipp Reichelt.

## Aufbau

- `rmd-opening-hours.php` Bootstrap (muss PHP-7-parsebar bleiben), `includes/` PSR-4 `RMD\OpeningHours\`.
- `includes/Domain/` ist **WordPress-frei** (Validator, Holidays, Schedule, Intervals, Status, Grouper, NoticeSelector) und wird mit plain PHPUnit getestet. Keine WP-Funktionen dort verwenden.
- Ein CPT `rmd_oh_set`, alle Daten als Post-Meta (`_rmd_oh_*`); `Domain\Validator` ist die einzige Instanz, die Datenformen definiert. Jeder Schreibpfad (Meta-Box, Import, Meta-Sanitizer, Settings) läuft durch ihn.
- Admin-Editor: React-App (`src/admin/set-editor`) in einer Meta-Box, Zustand landet als JSON im hidden Feld `rmd_oh_data`; PHP validiert in `Admin\SaveHandler`.
- Blocks: `src/blocks/*` mit `block.json` + `render.php`, Registrierung über `build/blocks-manifest.php` (`wp_register_block_types_from_metadata_collection`). Front-Assets liegen ungebaut in `assets/` und werden per Handle (`rmd-oh-front`, `rmd-oh-front-style`) von Blocks und Shortcodes geteilt.
- Cache-Sicherheit: Server bettet absolute Zeitstempel ein, `assets/js/front.js` vergleicht nur mit `Date.now()`; nach Ablauf des Horizonts REST-Fallback (`rmd-opening-hours/v1/status|notices`).
- Zugriff: Capability `manage_opening_hours`; Administrator immer, weitere Rollen über Einstellung `roles` (`Capabilities::sync()` läuft bei Aktivierung, Settings-Speichern und Self-Heal). Nur Nutzer mit `promote_users` dürfen die Rollen ändern (`Settings::sanitize`).
- `Admin\HelpPage` ist die Nutzer-Doku im Backend; bei neuen Feldern, Attributen oder Platzhaltern dort **und** in README/PO nachziehen.
- Auflösungsreihenfolge für einen Tag: geschlossener Zeitraum > Feiertag (außer Regel „regular“ oder Zeitraum mit `ignore_holidays`) > Sonderzeitraum > reguläre Woche. Änderungen daran immer mit Tests in `tests/Unit/ScheduleTest.php`.

## Code-Regeln

- PHP 8.1+, WP 6.8+. WordPress-Coding-Standards (`phpcs.xml.dist`), Methoden snake_case, Präfix `rmd_oh_` / Namespace `RMD\OpeningHours`, Textdomain `rmd-opening-hours`.
- Quellstrings Englisch, Übersetzung in `languages/rmd-opening-hours-de_DE.po`. Neue Strings dort nachziehen (`npm run i18n` erzeugt POT/MO/JSON; braucht WP-CLI).
- Sicherheit: jede Schreiboperation prüft Nonce + `manage_opening_hours`; Ausgabe escapen; REST nur lesend; keine externen Aufrufe außer Update-Check.
- Version an vier Stellen: Plugin-Header, `RMD_OH_VERSION`, `package.json`, `readme.txt` (Stable tag) und `src/blocks/*/block.json`. `bash bin/check-version.sh` prüft das.
- `RMD_OH_FAKE_NOW` (nur mit `WP_DEBUG`) in `wp-config.php` erlaubt Zeitreisen zum Testen.

## Build und Prüfung

```powershell
npm run build          # Blocks + Admin-Editor nach build/
npm run lint           # JS + SCSS
composer phpcs         # PHPCS
composer test          # PHPUnit (Domain)
npm run i18n           # Übersetzungen (WP-CLI nötig)
npm run zip            # dist/rmd-opening-hours.zip (Git Bash: rsync, zip, composer nötig)
```

Lokal testen: `.\bin\link-local.ps1` legt eine Junction in `wp-content\plugins` an (Standard: marlen-in-flow.de). Danach Plugin aktivieren; Blocks brauchen einen vorherigen `npm run build`.

## Git und Release

- Committen nur auf Anfrage, auf Feature-Branches; nie direkt auf `main`.
- Release: Versionen an allen Stellen erhöhen, `CHANGELOG.md` und `readme.txt` (Changelog) ergänzen, committen, Tag `vX.Y.Z` pushen. Der Workflow lintet, testet, baut Assets + Übersetzungen, erzeugt `rmd-opening-hours.zip` sowie `update.json` und legt ein **Draft-Release** an. Beide Assets müssen am Release bleiben: installierte Sites lesen `releases/latest/download/update.json` (kein GitHub-API-Aufruf, daher kein Rate-Limit); der Changelog darin kommt aus `CHANGELOG.md`.
- Den vom Workflow angelegten Entwurf bearbeiten (Releases → Entwurf → Stift), nicht neu anlegen; erst nach Test des ZIPs veröffentlichen. Kunden-Sites sehen nur veröffentlichte Releases (plugin-update-checker nutzt `releases/latest`).
- Bei jedem Release Titel `vX.Y.Z – <Kernänderung>` und Release Notes (Englisch, Markdown in Codeblock) mit ausgeben; Grundlage `git log <letzter Tag>..HEAD`.
- `gh` ist lokal nicht installiert: Workflow-Status über `https://api.github.com/repos/reicheltmediadesign/wordpress-rmd-opening-hours/actions/runs`, Releases über `.../releases`.
- Topics fürs Repo: `wordpress`, `wordpress-plugin`, `gutenberg-block`, `opening-hours`, `business-hours`.

## README

Nutzerorientiert (Features, Blocks/Shortcodes, Einstellungen, Styling per CSS Custom Properties, Installation, Privacy, License). Kein Development-Abschnitt in der README; Build/Release steht hier.
