<?php

namespace Restruct\Xpdf;

/**
 * Wrapper for the pdftotext tool — extract text content from PDF files.
 *
 * @see https://www.xpdfreader.com/pdftotext-man.html
 */
class PdfToText
{
    private string $pdfFile;
    private array $options = [];

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

    # --- Page range ---

    /** First page to convert. */
    public function firstPage(int $page): self
    {
        $this->options['-f'] = $page;
        return $this;
    }

    /** Last page to convert. */
    public function lastPage(int $page): self
    {
        $this->options['-l'] = $page;
        return $this;
    }

    /** Convenience: convert specific page range. */
    public function pages(int $first, int $last): self
    {
        return $this->firstPage($first)->lastPage($last);
    }

    # --- Layout modes (mutually exclusive) ---

    /** Maintain original physical layout. */
    public function layout(): self
    {
        $this->clearLayoutModes();
        $this->options['-layout'] = true;
        return $this;
    }

    /** Simple one-column layout (better horizontal spacing). */
    public function simple(): self
    {
        $this->clearLayoutModes();
        $this->options['-simple'] = true;
        return $this;
    }

    /** Like simple but handles slightly rotated text (eg OCR). */
    public function simple2(): self
    {
        $this->clearLayoutModes();
        $this->options['-simple2'] = true;
        return $this;
    }

    /** Table mode: keep rows and columns aligned. */
    public function table(): self
    {
        $this->clearLayoutModes();
        $this->options['-table'] = true;
        return $this;
    }

    /** Line printer mode: strict fixed-pitch layout. */
    public function linePrinter(): self
    {
        $this->clearLayoutModes();
        $this->options['-lineprinter'] = true;
        return $this;
    }

    /** Raw content stream order. */
    public function raw(): self
    {
        $this->clearLayoutModes();
        $this->options['-raw'] = true;
        return $this;
    }

    # --- Text options ---

    /** Set character pitch in points (for layout/table/lineprinter modes). */
    public function fixed(float $pitch): self
    {
        $this->options['-fixed'] = $pitch;
        return $this;
    }

    /** Set line spacing in points (for lineprinter mode). */
    public function lineSpacing(float $spacing): self
    {
        $this->options['-linespacing'] = $spacing;
        return $this;
    }

    /** Remove hidden/clipped text before layout. */
    public function clip(): self
    {
        $this->options['-clip'] = true;
        return $this;
    }

    /** Discard diagonal text (useful to skip watermarks). */
    public function noDiag(): self
    {
        $this->options['-nodiag'] = true;
        return $this;
    }

    /** Set text encoding (default: Latin1). Use 'UTF-8' for Unicode. */
    public function encoding(string $enc): self
    {
        $this->options['-enc'] = $enc;
        return $this;
    }

    /** Set end-of-line convention: 'unix', 'dos', or 'mac'. */
    public function eol(string $eol): self
    {
        $this->options['-eol'] = $eol;
        return $this;
    }

    /** Don't insert page break (form feed) between pages. */
    public function noPageBreak(): self
    {
        $this->options['-nopgbrk'] = true;
        return $this;
    }

    /** Insert Unicode BOM at start of output. */
    public function bom(): self
    {
        $this->options['-bom'] = true;
        return $this;
    }

    # --- Margins (discard text within margin, in points) ---

    /** Set all margins at once. */
    public function margins(float $left, float $top, float $right, float $bottom): self
    {
        $this->options['-marginl'] = $left;
        $this->options['-margint'] = $top;
        $this->options['-marginr'] = $right;
        $this->options['-marginb'] = $bottom;
        return $this;
    }

    # --- Password ---

    /** Owner password (bypasses security restrictions). */
    public function ownerPassword(string $password): self
    {
        $this->options['-opw'] = $password;
        return $this;
    }

    /** User password. */
    public function userPassword(string $password): self
    {
        $this->options['-upw'] = $password;
        return $this;
    }

    # --- Execution ---

    /**
     * Extract text and return as string.
     *
     * Outputs to stdout by default (using '-' as output file).
     *
     * @param int|null $timeout Timeout in seconds
     * @return string Extracted text, or empty string on failure
     */
    public function getText(?int $timeout = null): string
    {
        $result = $this->run('-', $timeout);

        return $result->isSuccessful() ? $result->output : '';
    }

    /**
     * Extract text to a file.
     *
     * @param string $outputFile Path to output text file
     * @param int|null $timeout Timeout in seconds
     * @return bool Whether extraction succeeded
     */
    public function toFile(string $outputFile, ?int $timeout = null): bool
    {
        return $this->run($outputFile, $timeout)->isSuccessful();
    }

    /**
     * Run pdftotext and return the full result.
     *
     * @param string $outputFile Output file path, or '-' for stdout
     * @param int|null $timeout Timeout in seconds
     * @return XpdfResult
     */
    public function run(string $outputFile = '-', ?int $timeout = null): XpdfResult
    {
        $args = $this->buildArgs();
        $args[] = $this->pdfFile;
        $args[] = $outputFile;

        return Xpdf::run('pdftotext', $args, $timeout);
    }

    # --- Convenience static methods ---

    /**
     * Quick text extraction with sensible defaults (UTF-8, layout preserved).
     *
     * @param string $pdfFile Path to PDF file
     * @param int|null $timeout Timeout in seconds
     * @return string Extracted text
     */
    public static function extract(string $pdfFile, ?int $timeout = null): string
    {
        return self::create($pdfFile)
            ->encoding('UTF-8')
            ->layout()
            ->getText($timeout);
    }

    # --- Internal ---

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

    private function clearLayoutModes(): void
    {
        unset(
            $this->options['-layout'],
            $this->options['-simple'],
            $this->options['-simple2'],
            $this->options['-table'],
            $this->options['-lineprinter'],
            $this->options['-raw'],
        );
    }
}
