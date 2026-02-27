Xpdf tools — pre-compiled binaries + PHP wrappers
======

Pre-compiled [Xpdf](https://www.xpdfreader.com/) command-line tools with fluent PHP wrapper classes. Includes `pdftotext`, `pdfinfo`, `pdftopng`, `pdfimages`, and more.

**Xpdf version:** 4.06

## Installation

```bash
composer require restruct/xpdf-static
```

## Quick examples

```php
use Xpdf\PdfToText;
use Xpdf\PdfInfo;
use Xpdf\PdfToPng;
use Xpdf\PdfImages;

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

### `Xpdf\Xpdf` — binary resolution + generic runner

```php
use Xpdf\Xpdf;

// Check availability
Xpdf::isAvailable();
Xpdf::availableTools(); // ['pdftotext', 'pdfinfo', ...]

// Run any tool directly
$result = Xpdf::run('pdftotext', ['-layout', '-enc', 'UTF-8', 'input.pdf', '-']);
echo $result->output;
echo $result->isSuccessful();
```

### `Xpdf\PdfToText` — text extraction

```php
use Xpdf\PdfToText;

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

### `Xpdf\PdfInfo` — document metadata

Replaces `howtomakeaturn/pdfinfo` with improved page dimension detection.

```php
use Xpdf\PdfInfo;

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

### `Xpdf\PdfToPng` — page rendering

```php
use Xpdf\PdfToPng;

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

### `Xpdf\PdfImages` — image extraction

```php
use Xpdf\PdfImages;

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

## License

- Xpdf tools: GNU General Public License v2 (Copyright Glyph & Cog, LLC)
- Wrapper code: MIT (Copyright Restruct web & apps)
