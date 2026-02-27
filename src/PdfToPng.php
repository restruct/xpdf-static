<?php

namespace Restruct\Xpdf;

/**
 * Wrapper for the pdftopng tool — convert PDF pages to PNG images.
 *
 * @see https://www.xpdfreader.com/pdftopng-man.html
 */
class PdfToPng
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

    # --- Image options ---

    /**
     * Set resolution in DPI (default: 150).
     *
     * Common values: 72 (screen), 150 (default), 240 (print quality), 300 (high quality).
     */
    public function resolution(int $dpi): self
    {
        $this->options['-r'] = $dpi;
        return $this;
    }

    /** Generate monochrome (1-bit) image. */
    public function mono(): self
    {
        $this->options['-mono'] = true;
        return $this;
    }

    /** Generate grayscale image. */
    public function gray(): self
    {
        $this->options['-gray'] = true;
        return $this;
    }

    /** Include alpha channel (transparency). Cannot be combined with mono(). */
    public function alpha(): self
    {
        $this->options['-alpha'] = true;
        return $this;
    }

    /** Rotate pages by 0, 90, 180, or 270 degrees. */
    public function rotate(int $angle): self
    {
        if (!in_array($angle, [0, 90, 180, 270])) {
            throw new \InvalidArgumentException("Rotation must be 0, 90, 180, or 270 degrees");
        }
        $this->options['-rot'] = $angle;
        return $this;
    }

    # --- Rendering ---

    /** Enable/disable FreeType font rasterizer (default: yes). */
    public function freetype(bool $enable): self
    {
        $this->options['-freetype'] = $enable ? 'yes' : 'no';
        return $this;
    }

    /** Enable/disable font anti-aliasing (default: yes). */
    public function antiAlias(bool $enable): self
    {
        $this->options['-aa'] = $enable ? 'yes' : 'no';
        return $this;
    }

    /** Enable/disable vector anti-aliasing (default: yes). */
    public function vectorAntiAlias(bool $enable): self
    {
        $this->options['-aaVector'] = $enable ? 'yes' : 'no';
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
     * Convert PDF pages to PNG files.
     *
     * Output files are named: {pngRoot}-{nnnnnn}.png
     *
     * @param string $pngRoot Output path prefix (e.g. '/tmp/output' → /tmp/output-000001.png)
     * @param int|null $timeout Timeout in seconds
     * @return XpdfResult
     */
    public function run(string $pngRoot, ?int $timeout = null): XpdfResult
    {
        $args = $this->buildArgs();
        $args[] = $this->pdfFile;
        $args[] = $pngRoot;

        return Xpdf::run('pdftopng', $args, $timeout ?? 120);
    }

    /**
     * Convert and return list of generated PNG file paths.
     *
     * @param string $pngRoot Output path prefix
     * @param int|null $timeout Timeout in seconds
     * @return string[] Array of PNG file paths, or empty on failure
     */
    public function convert(string $pngRoot, ?int $timeout = null): array
    {
        $result = $this->run($pngRoot, $timeout);

        if (!$result->isSuccessful()) {
            return [];
        }

        # Find generated PNG files matching the root prefix
        $pattern = $pngRoot . '-*.png';
        $files = glob($pattern);
        sort($files);

        return $files ?: [];
    }

    # --- Convenience static methods ---

    /**
     * Quick conversion: render all pages at given DPI.
     *
     * @param string $pdfFile Input PDF
     * @param string $pngRoot Output prefix
     * @param int $dpi Resolution (default: 150)
     * @return string[] Generated PNG file paths
     */
    public static function render(string $pdfFile, string $pngRoot, int $dpi = 150): array
    {
        return self::create($pdfFile)
            ->resolution($dpi)
            ->convert($pngRoot);
    }

    /**
     * Render a single page to PNG.
     *
     * @param string $pdfFile Input PDF
     * @param int $page Page number (1-based)
     * @param string $pngRoot Output prefix
     * @param int $dpi Resolution (default: 150)
     * @return string|null PNG file path, or null on failure
     */
    public static function renderPage(string $pdfFile, int $page, string $pngRoot, int $dpi = 150): ?string
    {
        $files = self::create($pdfFile)
            ->pages($page, $page)
            ->resolution($dpi)
            ->convert($pngRoot);

        return $files[0] ?? null;
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
}
