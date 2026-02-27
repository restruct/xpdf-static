<?php

namespace Restruct\Xpdf;

/**
 * Wrapper for the pdfinfo tool — extract PDF document metadata.
 *
 * Replaces howtomakeaturn/pdfinfo with a direct implementation.
 * Integrates page dimension/paper size detection from FUSE's PdfInfo wrapper.
 *
 * @see https://www.xpdfreader.com/pdfinfo-man.html
 */
class PdfInfo
{
    private string $pdfFile;
    private array $options = [];

    /** Parsed info (lazy-loaded) */
    private ?array $parsed = null;

    # Standard paper sizes in points (width x height, portrait)
    # Source: ISO 216, ANSI, GhostView
    private const PAPER_SIZES = [
        // ISO A series
        '2384x3371' => 'A0',  '1685x2384' => 'A1',  '1190x1684' => 'A2',
        '842x1190'  => 'A3',  '595x842'   => 'A4',  '420x595'   => 'A5',
        '298x420'   => 'A6',  '210x298'   => 'A7',  '148x210'   => 'A8',
        // ISO B series
        '2835x4008' => 'B0',  '2004x2835' => 'B1',  '1417x2004' => 'B2',
        '1001x1417' => 'B3',  '709x1001'  => 'B4',  '499x709'   => 'B5',
        '354x499'   => 'B6',  '249x354'   => 'B7',  '176x249'   => 'B8',
        '125x176'   => 'B9',  '88x125'    => 'B10',
        // ISO C series (envelopes)
        '1837x578'  => 'C2',  '578x919'   => 'C3',
        '919x649'   => 'C4',  '649x459'   => 'C5',  '459x323'  => 'C6',
        // ANSI / American
        '612x792'   => 'Letter',   '792x612'   => 'Letter',
        '612x1008'  => 'Legal',    '792x1224'  => 'Tabloid',
        '1224x792'  => 'Ledger',   '522x756'   => 'Executive',
        '396x612'   => 'Statement',
        '1584x1224' => 'ANSI-C',   '2448x1584' => 'ANSI-D',   '3168x2448' => 'ANSI-E',
        // Misc
        '612x936'   => 'Folio',    '610x780'   => 'Quarto',    '720x1008'  => '10x14',
    ];

    public function __construct(string $pdfFile)
    {
        if (!file_exists($pdfFile)) {
            throw new \RuntimeException("PDF file not found: $pdfFile");
        }
        $this->pdfFile = $pdfFile;
    }

    /**
     * Create a new instance (fluent API).
     */
    public static function create(string $pdfFile): self
    {
        return new self($pdfFile);
    }

    # --- Options ---

    /** Examine pages from $first to $last (enables per-page size output). */
    public function pages(int $first, int $last): self
    {
        $this->options['-f'] = $first;
        $this->options['-l'] = $last;
        $this->parsed = null;
        return $this;
    }

    /** Include page box bounding boxes (MediaBox, CropBox, etc.). */
    public function box(): self
    {
        $this->options['-box'] = true;
        $this->parsed = null;
        return $this;
    }

    /** Include document-level metadata stream. */
    public function meta(): self
    {
        $this->options['-meta'] = true;
        $this->parsed = null;
        return $this;
    }

    /** Print raw (undecoded) date strings. */
    public function rawDates(): self
    {
        $this->options['-rawdates'] = true;
        $this->parsed = null;
        return $this;
    }

    /** Include custom (non-standard) Info dictionary entries. */
    public function custom(): self
    {
        $this->options['-custom'] = true;
        $this->parsed = null;
        return $this;
    }

    /** Set text encoding for output (default: Latin1). */
    public function encoding(string $enc): self
    {
        $this->options['-enc'] = $enc;
        $this->parsed = null;
        return $this;
    }

    /** Owner password. */
    public function ownerPassword(string $password): self
    {
        $this->options['-opw'] = $password;
        $this->parsed = null;
        return $this;
    }

    /** User password. */
    public function userPassword(string $password): self
    {
        $this->options['-upw'] = $password;
        $this->parsed = null;
        return $this;
    }

    # --- Run + parse ---

    /**
     * Run pdfinfo and return raw result.
     */
    public function run(?int $timeout = null): XpdfResult
    {
        $args = $this->buildArgs();
        $args[] = $this->pdfFile;

        return Xpdf::run('pdfinfo', $args, $timeout);
    }

    /**
     * Get all parsed info as associative array.
     *
     * @return array<string, string|null>
     */
    public function getAll(): array
    {
        $this->ensureParsed();

        return $this->parsed;
    }

    /**
     * Get raw output lines from pdfinfo.
     *
     * @return string[]
     */
    public function getRawOutput(): array
    {
        return $this->run()->getLines();
    }

    # --- Individual field accessors ---

    public function getTitle(): ?string       { return $this->get('Title'); }
    public function getAuthor(): ?string      { return $this->get('Author'); }
    public function getCreator(): ?string     { return $this->get('Creator'); }
    public function getProducer(): ?string    { return $this->get('Producer'); }
    public function getSubject(): ?string     { return $this->get('Subject'); }
    public function getKeywords(): ?string    { return $this->get('Keywords'); }
    public function getCreationDate(): ?string { return $this->get('CreationDate'); }
    public function getModDate(): ?string     { return $this->get('ModDate'); }
    public function getTagged(): ?string      { return $this->get('Tagged'); }
    public function getForm(): ?string        { return $this->get('Form'); }
    public function getEncrypted(): ?string   { return $this->get('Encrypted'); }
    public function getFileSize(): ?string    { return $this->get('File size'); }
    public function getOptimized(): ?string   { return $this->get('Optimized'); }
    public function getPdfVersion(): ?string  { return $this->get('PDF version'); }

    /** Get total page count. */
    public function getPageCount(): int
    {
        return (int) ($this->get('Pages') ?? 0);
    }

    /** Get raw page size string (e.g. "595.32 x 841.92 pts (A4) (rotated 0 degrees)"). */
    public function getPageSizeRaw(): ?string
    {
        return $this->get('Page size');
    }

    /** Get page rotation in degrees. */
    public function getPageRotation(): int
    {
        return (int) ($this->get('Page rot') ?? 0);
    }

    /**
     * Get parsed page dimensions with paper size detection.
     *
     * @return object{width: float, height: float, standard: ?string, rotation: ?int, orientation: string, nearestDIN: ?string}
     */
    public function getPageDimensions(): object
    {
        $pageSizeStr = $this->getPageSizeRaw() ?? '';

        # Parse "595.32 x 841.92 pts (A4) (rotated 0 degrees)"
        $parts = explode(' pts ', $pageSizeStr);
        $sizePart = $parts[0] ?? '';
        $sizeValues = explode(' x ', $sizePart);

        $ptsWidth = round((float) ($sizeValues[0] ?? 0));
        $ptsHeight = round((float) ($sizeValues[1] ?? 0));
        $largestSide = max($ptsWidth, $ptsHeight);
        $shortestSide = min($ptsWidth, $ptsHeight);

        # Detect rotation from the string
        $rotation = null;
        foreach ([0, 90, 180, 270] as $angle) {
            if (str_contains($pageSizeStr, "(rotated $angle degrees)")) {
                $rotation = $angle;
                break;
            }
        }

        # Determine orientation
        if ($largestSide === $ptsHeight && (!$rotation || $rotation === 0 || $rotation === 180)) {
            $orientation = 'portrait';
        } else {
            $orientation = 'landscape';
        }

        # Detect standard paper size — first from pdfinfo's own detection
        $standardSize = null;
        for ($i = 0; $i <= 8; $i++) {
            if (str_contains($pageSizeStr, " (A{$i}) ")) {
                $standardSize = "A{$i}";
                break;
            }
        }

        # If not detected by pdfinfo, look up in our table (both orientations)
        if (!$standardSize) {
            $standardSize = self::PAPER_SIZES["{$ptsWidth}x{$ptsHeight}"]
                         ?? self::PAPER_SIZES["{$ptsHeight}x{$ptsWidth}"]
                         ?? null;
        }

        # Nearest DIN size (A4 or A3) for print/display decisions
        # Include slightly larger than A4 to cover Letter size
        $nearestDIN = ($shortestSide <= 620 && $largestSide <= 850) ? 'A4' : 'A3';

        return (object) [
            'width'       => $ptsWidth,
            'height'      => $ptsHeight,
            'standard'    => $standardSize,
            'rotation'    => $rotation,
            'orientation' => $orientation,
            'nearestDIN'  => $nearestDIN,
        ];
    }

    # --- Convenience static methods ---

    /**
     * Quick page count extraction.
     */
    public static function pageCount(string $pdfFile): int
    {
        return self::create($pdfFile)->getPageCount();
    }

    /**
     * Quick info extraction — returns all parsed fields.
     *
     * @return array<string, string|null>
     */
    public static function info(string $pdfFile): array
    {
        return self::create($pdfFile)->getAll();
    }

    # --- Internal ---

    /**
     * Get a single parsed field by key.
     */
    private function get(string $key): ?string
    {
        $this->ensureParsed();

        return $this->parsed[$key] ?? null;
    }

    private function ensureParsed(): void
    {
        if ($this->parsed !== null) {
            return;
        }

        $result = $this->run();
        $this->parsed = [];

        if (!$result->isSuccessful()) {
            return;
        }

        foreach ($result->getLines() as $line) {
            if (preg_match('/^(.+?):\s+(.+)$/', $line, $m)) {
                $this->parsed[trim($m[1])] = trim($m[2]);
            }
        }
    }

    private function buildArgs(): array
    {
        $args = [];
        foreach ($this->options as $flag => $value) {
            $args[] = $flag;
            if ($value !== true) {
                $args[] = (string) $value;
            }
        }
        return $args;
    }
}
