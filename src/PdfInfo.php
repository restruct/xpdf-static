<?php

namespace Restruct\Xpdf;

/**
 * Wrapper for the pdfinfo tool — extract PDF document metadata.
 *
 * Replaces howtomakeaturn/pdfinfo with a direct implementation.
 * The page dimension/paper size detection started as a copy of an in-house PdfInfo wrapper;
 * since 1.0.2 this class holds the reconciled version (see CHANGELOG).
 *
 * A failed pdfinfo run (unreadable, damaged or user-password-protected file, timeout) throws a
 * RuntimeException from every parsed getter, rather than reporting 0 pages.
 *
 * @see https://www.xpdfreader.com/pdfinfo-man.html
 */
class PdfInfo
{
    private string $pdfFile;
    private array $options = [];

    /** Parsed info (lazy-loaded) */
    private ?array $parsed = null;

    # Standard paper sizes in points (width x height, portrait unless the name is orientation-specific)
    # Source: ISO 216, ANSI, GhostView
    # Keys are the true size rounded to whole points (mm * 72 / 25.4). Several used to be off by
    # one (A0-A3, A8, C4) or plain wrong (C2, C3), so those sizes never matched; corrected in 1.0.2.
    # Matching is within STANDARD_SIZE_TOLERANCE, so the rounding itself no longer decides a match.
    private const PAPER_SIZES = [
        // ISO A series
        '2384x3370' => 'A0',  '1684x2384' => 'A1',  '1191x1684' => 'A2',
        '842x1191'  => 'A3',  '595x842'   => 'A4',  '420x595'   => 'A5',
        '298x420'   => 'A6',  '210x298'   => 'A7',  '147x210'   => 'A8',
        // ISO B series
        '2835x4008' => 'B0',  '2004x2835' => 'B1',  '1417x2004' => 'B2',
        '1001x1417' => 'B3',  '709x1001'  => 'B4',  '499x709'   => 'B5',
        '354x499'   => 'B6',  '249x354'   => 'B7',  '176x249'   => 'B8',
        '125x176'   => 'B9',  '88x125'    => 'B10',
        // ISO C series (envelopes)
        '1298x1837' => 'C2',  '918x1298'  => 'C3',
        '649x918'   => 'C4',  '459x649'   => 'C5',  '323x459'  => 'C6',
        // ANSI / American
        '612x792'   => 'Letter',   '792x612'   => 'Letter',
        '612x1008'  => 'Legal',    '792x1224'  => 'Tabloid',
        '1224x792'  => 'Ledger',   '522x756'   => 'Executive',
        '396x612'   => 'Statement',
        '1584x1224' => 'ANSI-C',   '2448x1584' => 'ANSI-D',   '3168x2448' => 'ANSI-E',
        // Misc
        '612x936'   => 'Folio',    '610x780'   => 'Quarto',    '720x1008'  => '10x14',
    ];

    /**
     * Points of slack when matching a page against PAPER_SIZES. Real pages are rarely a whole
     * number of points (a true A3 is 841.89 x 1190.55), and producers round differently
     * (e.g. 595.2 x 841.68, 1190.52 x 841.92). Before 1.0.2 only exact rounded keys matched,
     * so such pages came out as standard=null. The two closest entries (B4 and 10x14) are
     * 11 pt apart, so 2 pt cannot match the wrong one; PdfInfoTest guards that.
     */
    private const STANDARD_SIZE_TOLERANCE = 2.0;

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
     * Keys are pdfinfo's labels ("Pages", "Page size", ...). A label with an empty value
     * (e.g. "Title:" on a document without a title) is left out, so its getter returns null.
     *
     * @return array<string, string>
     * @throws \RuntimeException if pdfinfo fails
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

    /**
     * Get total page count.
     *
     * @throws \RuntimeException if pdfinfo fails (never 0 for an unreadable file)
     */
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
        # xpdf's pdfinfo has no "Page rot" line (that is poppler's); it appends
        # "(rotated N degrees)" to "Page size" instead, so before 1.0.2 this always returned 0
        $pageRot = $this->get('Page rot');
        if ($pageRot !== null) {
            return (int) $pageRot;
        }

        return $this->getPageDimensions()->rotation ?? 0;
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

        # Exact sizes for paper-size matching, rounded ones for the returned width/height
        $exactWidth = (float) ($sizeValues[0] ?? 0);
        $exactHeight = (float) ($sizeValues[1] ?? 0);
        $ptsWidth = round($exactWidth);
        $ptsHeight = round($exactHeight);
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
        # xpdf only labels A0-A6 and letter, and only within 1 pt of its own idea of the size,
        # so e.g. a true A3 (1190.55 x 841.89) comes through unlabelled
        if (!$standardSize) {
            // $standardSize = self::PAPER_SIZES["{$ptsWidth}x{$ptsHeight}"]
            //              ?? self::PAPER_SIZES["{$ptsHeight}x{$ptsWidth}"]
            //              ?? null;
            $standardSize = self::matchPaperSize($exactWidth, $exactHeight);
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
     * @return array<string, string>
     * @throws \RuntimeException if pdfinfo fails
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

    /**
     * Run pdfinfo once and cache the parsed output.
     *
     * @throws \RuntimeException if pdfinfo exits non-zero
     */
    private function ensureParsed(): void
    {
        if ($this->parsed !== null) {
            return;
        }

        $result = $this->run();

        # Before 1.0.2 a failed run parsed as [] and getPageCount() quietly returned 0, a plausible
        # wrong number for a damaged or unreadable PDF. howtomakeaturn/pdfinfo threw here too.
        # $this->parsed stays null, so a later call runs pdfinfo again.
        if (!$result->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                "pdfinfo failed for '%s' (exit %d): %s",
                $this->pdfFile,
                $result->exitCode,
                trim($result->errorOutput)
            ));
        }

        $this->parsed = self::parseOutput($result->output);
    }

    /**
     * Parse pdfinfo output into label => value.
     *
     * - Anchored at the start of the line, so a label can't match inside another one.
     * - Lines are right-trimmed first: pdfinfo pads an empty value ("Title:          "),
     *   which must read as absent (null), not as ''.
     * - The FIRST occurrence of a label wins. With -meta, pdfinfo appends the XMP stream
     *   after the Info fields, and its lines must not override them. (Before 1.0.2 the
     *   last one won.)
     *
     * @return array<string, string>
     */
    private static function parseOutput(string $output): array
    {
        $parsed = [];

        foreach (explode("\n", $output) as $line) {
            if (preg_match('/^(.+?):\s+(.+)$/', rtrim($line), $m)) {
                $parsed[trim($m[1])] ??= trim($m[2]);
            }
        }

        return $parsed;
    }

    /**
     * First PAPER_SIZES name within STANDARD_SIZE_TOLERANCE of $width x $height.
     *
     * The page is tried as it is first, then turned a quarter, so orientation-specific
     * names keep working (792 x 1224 is Tabloid, 1224 x 792 is Ledger).
     */
    private static function matchPaperSize(float $width, float $height): ?string
    {
        foreach ([[$width, $height], [$height, $width]] as [$w, $h]) {
            foreach (self::PAPER_SIZES as $key => $name) {
                [$keyWidth, $keyHeight] = array_map('floatval', explode('x', $key));
                if (abs($w - $keyWidth) <= self::STANDARD_SIZE_TOLERANCE
                    && abs($h - $keyHeight) <= self::STANDARD_SIZE_TOLERANCE
                ) {
                    return $name;
                }
            }
        }

        return null;
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
