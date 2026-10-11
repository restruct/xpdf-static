Xpdf tools — pre-compiled binaries + PHP wrappers
======

Pre-compiled [Xpdf](https://www.xpdfreader.com/) command-line tools with fluent PHP wrapper classes. Includes `pdftotext`, `pdfinfo`, `pdftopng`, `pdfimages`, and more.

**Xpdf version:** 4.06

*Maintained by [Restruct](https://github.com/restruct). If this package saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

## Requirements

- PHP 8.1 or higher and `symfony/process` ^6 or ^7 (`composer.json` is the source of truth).
- Bundled binaries are x86_64 only: Linux x86_64, and macOS x86_64 or Apple Silicon via Rosetta 2.
  On other platforms (e.g. Linux ARM64) install xpdf yourself and point `XPDF_BIN_DIR` at it.

## Installation

```bash
composer require restruct/xpdf-static
```

## Quick examples

```php
use Restruct\Xpdf\PdfToText;
use Restruct\Xpdf\PdfInfo;
use Restruct\Xpdf\PdfToPng;
use Restruct\Xpdf\PdfImages;

// Extract text (UTF-8, layout preserved)
$text = PdfToText::extract('/path/to/file.pdf');

// Page count
$pages = PdfInfo::pageCount('/path/to/file.pdf');

// Full document info
$info = PdfInfo::create('/path/to/file.pdf');
echo $info->getTitle();
echo $info->getPageCount();
$dims = $info->getPageDimensions();
echo $dims->standard; // 'A4'

// Render pages to PNG
$pngs = PdfToPng::render('/path/to/file.pdf', '/tmp/output', dpi: 240);

// Extract images
$images = PdfImages::extractAll('/path/to/file.pdf', '/tmp/img');

// List images without extracting
$listing = PdfImages::list('/path/to/file.pdf');
```

## Wrapper classes

### `Restruct\Xpdf\Xpdf` — binary resolution + generic runner

```php
use Restruct\Xpdf\Xpdf;

// Check availability
Xpdf::isAvailable();
Xpdf::availableTools(); // ['pdftotext', 'pdfinfo', ...]

// Run any tool directly
$result = Xpdf::run('pdftotext', ['-layout', '-enc', 'UTF-8', 'input.pdf', '-']);
echo $result->output;
echo $result->isSuccessful();
```

### `Restruct\Xpdf\PdfToText` — text extraction

```php
use Restruct\Xpdf\PdfToText;

// Quick extraction
$text = PdfToText::extract('input.pdf');

// Fluent API with full control
$text = PdfToText::create('input.pdf')
    ->encoding('UTF-8')
    ->layout()
    ->pages(1, 5)
    ->noPageBreak()
    ->noDiag()        // skip watermarks
    ->getText();

// Table mode for tabular data
$text = PdfToText::create('input.pdf')
    ->table()
    ->encoding('UTF-8')
    ->getText();

// Save to file
PdfToText::create('input.pdf')
    ->encoding('UTF-8')
    ->toFile('output.txt');
```

### `Restruct\Xpdf\PdfInfo` — document metadata

Replaces `howtomakeaturn/pdfinfo` with improved page dimension detection.

```php
use Restruct\Xpdf\PdfInfo;

$info = PdfInfo::create('input.pdf');

// Basic metadata
$info->getTitle();
$info->getAuthor();
$info->getPageCount();
$info->getPdfVersion();
$info->getCreationDate();

// Page dimensions with paper size detection
$dims = $info->getPageDimensions();
$dims->width;       // 595 (points)
$dims->height;      // 842 (points)
$dims->standard;    // 'A4'
$dims->orientation; // 'portrait'
$dims->rotation;    // 0
$dims->nearestDIN;  // 'A4' or 'A3'

// All fields as array
$all = $info->getAll();
```

If pdfinfo fails (damaged, unreadable or user-password-protected file, timeout), every parsed
getter throws a `RuntimeException` with the exit code and pdfinfo's error output, instead of
returning 0 pages (from 1.0.2). A failed run is not cached, so each getter call runs pdfinfo again.
`run()` still returns the raw `XpdfResult` without throwing. (A PDF with only an owner password
opens fine and does not throw.)

Empty fields (e.g. a document without a title) return `null`. When pdfinfo prints a label twice,
the first value wins.

`standard` is pdfinfo's own label when it prints one (it labels A0 to A6 and letter), otherwise the
nearest entry in the built-in table of ISO A/B/C, ANSI and a few other sizes, within 2 points in
either orientation. `null` means no standard size matched.

### `Restruct\Xpdf\PdfToPng` — page rendering

```php
use Restruct\Xpdf\PdfToPng;

// Render all pages
$files = PdfToPng::render('input.pdf', '/tmp/page', dpi: 240);
// ['/tmp/page-000001.png', '/tmp/page-000002.png', ...]

// Render single page
$file = PdfToPng::renderPage('input.pdf', 1, '/tmp/thumb', dpi: 72);

// Full control
$files = PdfToPng::create('input.pdf')
    ->resolution(300)
    ->pages(1, 10)
    ->gray()
    ->convert('/tmp/output');
```

### `Restruct\Xpdf\PdfImages` — image extraction

```php
use Restruct\Xpdf\PdfImages;

// Extract all images (JPEG where possible)
$files = PdfImages::extractAll('input.pdf', '/tmp/img');

// Extract unique images only
$files = PdfImages::extractUnique('input.pdf', '/tmp/img');

// List images without extracting
$list = PdfImages::list('input.pdf');
foreach ($list as $img) {
    echo "{$img['page']}: {$img['width']}x{$img['height']} {$img['color']} {$img['enc']}\n";
}

// Full control
$files = PdfImages::create('input.pdf')
    ->pages(1, 5)
    ->jpeg()
    ->unique()
    ->extract('/tmp/img');
```

## Binary resolution

The bootstrap auto-detects the platform and sets `XPDF_BIN_DIR`:

1. `XPDF_BIN_DIR` constant (if already defined)
2. `XPDF_BIN_DIR` environment variable
3. macOS: bundled `x64/mac/` binaries
4. Linux: bundled `x64/linux/` binaries

## Available binaries

All 9 xpdf tools are bundled: `pdftotext`, `pdfinfo`, `pdffonts`, `pdfimages`, `pdftohtml`, `pdftopng`, `pdftoppm`, `pdftops`, `pdfdetach`.

## Running the tests

```bash
composer install
vendor/bin/phpunit
```

Tests that run the real `pdfinfo` are skipped when it is not available.

## License

- Wrapper code (`bootstrap.php`, `src/`): MIT (Copyright Restruct web & apps).
- Bundled xpdf binaries (`x64/`): unmodified xpdf-tools 4.06, Copyright Glyph & Cog, LLC, licensed
  under the GNU General Public License version 2 or 3. They are not covered by the MIT licence.
  The upstream README, man pages and GPL texts that must accompany them are in `xpdf-doc/`; source
  is available from https://www.xpdfreader.com/download.html.

See [LICENSE](LICENSE) for the full terms.
