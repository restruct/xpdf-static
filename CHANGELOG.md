# Changelog

## 1.0.2 (2026-10-11)

### Fixed

- `PdfInfo`: a failed pdfinfo run now throws a `RuntimeException` (exit code and error output) from
  `getAll()`, `getPageCount()` and the other parsed getters. It used to parse as an empty result,
  so a damaged or unreadable PDF reported 0 pages (#1). A failed run is not cached: each getter call
  runs pdfinfo again. `run()` is unchanged.
- `PdfInfo` output parsing: an empty field (pdfinfo pads it, e.g. `Title:` with trailing spaces) is
  now absent and its getter returns `null`, as with howtomakeaturn/pdfinfo, instead of `''`. When a
  label occurs twice (e.g. in the XMP stream that `-meta` appends), the first value now wins
  instead of the last (#1).
- `PdfInfo` paper sizes: pages now match a standard size within 2 points, in either orientation,
  instead of only on exact rounded keys. A true A3 (841.89 x 1190.55 pt), which pdfinfo does not
  label itself, used to come out as `standard = null` (#1).
- `PdfInfo` paper-size table: corrected keys that never matched a real page: A0, A1, A2, A3, A8 and
  C4 were off by one point, C2 and C3 were wrong (#1).
- `PdfInfo::getPageRotation()` returned 0 for every page: it read a `Page rot` line that xpdf's
  pdfinfo does not print. It now falls back to the `(rotated N degrees)` part of `Page size`.

### Added

- A PHPUnit test suite (`composer install && vendor/bin/phpunit`).
