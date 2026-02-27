Xpdf tools — pre-compiled binaries for Linux x64 and macOS
======

Pre-compiled [Xpdf](https://www.xpdfreader.com/) command-line tools for PDF processing. Includes `pdftotext`, `pdfinfo`, `pdffonts`, `pdfimages`, `pdftohtml`, `pdftopng`, `pdftoppm`, `pdftops`, and `pdfdetach`.

**Xpdf version:** 4.06

## Features

- **pdftotext** — extract text from PDF files
- **pdfinfo** — PDF document information
- **pdffonts** — list fonts used in a PDF
- **pdfimages** — extract images from PDF files
- **pdftohtml** — convert PDF to HTML
- **pdftopng** / **pdftoppm** — convert PDF pages to images
- **pdftops** — convert PDF to PostScript
- **pdfdetach** — extract embedded files from PDF
- **OS-aware bootstrap** (`bootstrap.php`) auto-detects platform and sets `XPDF_BIN_DIR`

## Installation

```bash
composer require restruct/xpdf-static
```

Make sure the binaries in `x64/linux/` or `x64/mac/` have executable permissions (`chmod +x`).

## Usage

### PHP usage

```php
// Auto-detect platform binaries
require 'vendor/restruct/xpdf-static/bootstrap.php';
$pdftotext = XPDF_BIN_DIR . '/pdftotext';

// Or via the class wrapper
use XpdfStatic\XpdfStatic;

$pdftotext = XpdfStatic::getToolPath('pdftotext');
if (XpdfStatic::isAvailable()) {
    exec("$pdftotext -layout input.pdf output.txt");
}

// List available tools
$tools = XpdfStatic::availableTools();
// ['pdftotext', 'pdfinfo', 'pdffonts', ...]
```

### Common pdftotext options

```bash
# Extract text preserving layout
pdftotext -layout input.pdf output.txt

# Extract to stdout
pdftotext input.pdf -

# Extract specific pages
pdftotext -f 1 -l 5 input.pdf output.txt

# Simple text extraction (no layout preservation)
pdftotext -simple input.pdf output.txt
```

## License

- Xpdf tools: GNU General Public License v2 (Copyright Glyph & Cog, LLC)
- Wrapper code: MIT (Copyright Restruct web & apps)
