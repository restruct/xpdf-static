<?php

namespace Xpdf;

/**
 * Wrapper for the pdfimages tool — extract images from PDF files.
 *
 * Extracts images embedded in a PDF and saves them as individual files.
 * By default images are saved in PBM/PGM/PPM format; use jpeg() or raw()
 * to save in native formats.
 *
 * @see https://www.xpdfreader.com/pdfimages-man.html
 */
class PdfImages
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

    /** First page to scan. */
    public function firstPage(int $page): self
    {
        $this->options['-f'] = $page;
        return $this;
    }

    /** Last page to scan. */
    public function lastPage(int $page): self
    {
        $this->options['-l'] = $page;
        return $this;
    }

    /** Convenience: scan specific page range. */
    public function pages(int $first, int $last): self
    {
        return $this->firstPage($first)->lastPage($last);
    }

    # --- Output format ---

    /**
     * Save DCT-encoded images as JPEG files.
     *
     * Non-DCT images still saved as PBM/PGM/PPM.
     * Inline images always saved as PBM/PGM/PPM.
     */
    public function jpeg(): self
    {
        $this->options['-j'] = true;
        return $this;
    }

    /**
     * Save JPX/JPEG 2000 images as JP2 files.
     *
     * Similar to jpeg() but for JPEG 2000 encoded images.
     */
    public function jpeg2000(): self
    {
        $this->options['-J'] = true;
        return $this;
    }

    /**
     * Save all images in their native PDF format.
     *
     * Most formats are non-standard; primarily useful for PDF generation tools.
     */
    public function raw(): self
    {
        $this->options['-raw'] = true;
        return $this;
    }

    # --- Filtering ---

    /**
     * Write only unique images (skip duplicates within the PDF).
     *
     * Uniqueness is determined by image object reuse in the PDF.
     */
    public function unique(): self
    {
        $this->options['-u'] = true;
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
     * Extract images to files.
     *
     * Output files are named: {imageRoot}-{nnn}.{ext}
     *
     * @param string $imageRoot Output path prefix (e.g. '/tmp/img' → /tmp/img-000.ppm, etc.)
     * @param int|null $timeout Timeout in seconds
     * @return XpdfResult
     */
    public function run(string $imageRoot, ?int $timeout = null): XpdfResult
    {
        $args = $this->buildArgs();
        $args[] = $this->pdfFile;
        $args[] = $imageRoot;

        return Xpdf::run('pdfimages', $args, $timeout ?? 60);
    }

    /**
     * Extract images and return list of generated file paths.
     *
     * @param string $imageRoot Output path prefix
     * @param int|null $timeout Timeout in seconds
     * @return string[] Array of image file paths, or empty on failure
     */
    public function extract(string $imageRoot, ?int $timeout = null): array
    {
        $result = $this->run($imageRoot, $timeout);

        if (!$result->isSuccessful()) {
            return [];
        }

        # Find generated files matching the root prefix (various extensions)
        $dir = dirname($imageRoot);
        $prefix = basename($imageRoot);
        $files = glob($dir . '/' . $prefix . '-*');
        sort($files);

        return $files ?: [];
    }

    /**
     * List images in the PDF without extracting them.
     *
     * Returns parsed image metadata: page, width, height, color space, bpc, etc.
     *
     * @param int|null $timeout Timeout in seconds
     * @return array<int, array{page: int, num: int, type: string, width: int, height: int, color: string, comp: int, bpc: int, enc: string, interp: string, objectID: string, xDpi: float, yDpi: float, size: string, ratio: string}>
     */
    public function listImages(?int $timeout = null): array
    {
        $args = $this->buildArgs();
        $args[] = '-list';
        $args[] = $this->pdfFile;

        $result = Xpdf::run('pdfimages', $args, $timeout);

        if (!$result->isSuccessful()) {
            return [];
        }

        return self::parseListOutput($result->output);
    }

    /**
     * List images without extracting — static convenience method.
     *
     * @return array Same format as listImages()
     */
    public static function list(string $pdfFile): array
    {
        return self::create($pdfFile)->listImages();
    }

    # --- Convenience static methods ---

    /**
     * Quick extraction with JPEG format for DCT images.
     *
     * @param string $pdfFile Input PDF
     * @param string $imageRoot Output prefix
     * @return string[] Extracted image file paths
     */
    public static function extractAll(string $pdfFile, string $imageRoot): array
    {
        return self::create($pdfFile)
            ->jpeg()
            ->jpeg2000()
            ->extract($imageRoot);
    }

    /**
     * Quick extraction of unique images only, in native formats.
     *
     * @param string $pdfFile Input PDF
     * @param string $imageRoot Output prefix
     * @return string[] Extracted image file paths
     */
    public static function extractUnique(string $pdfFile, string $imageRoot): array
    {
        return self::create($pdfFile)
            ->jpeg()
            ->jpeg2000()
            ->unique()
            ->extract($imageRoot);
    }

    # --- Internal ---

    /**
     * Parse the -list output into structured data.
     *
     * Output format (columns separated by whitespace):
     * page  num  type  width  height  color  comp  bpc  enc  interp  object ID  x-dpi  y-dpi  size  ratio
     */
    private static function parseListOutput(string $output): array
    {
        $images = [];
        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim($line);
            # Skip header lines and empty lines
            if ($line === '' || str_starts_with($line, 'page') || str_starts_with($line, '---')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            if (count($parts) < 14) {
                continue;
            }

            $images[] = [
                'page'     => (int) $parts[0],
                'num'      => (int) $parts[1],
                'type'     => $parts[2],
                'width'    => (int) $parts[3],
                'height'   => (int) $parts[4],
                'color'    => $parts[5],
                'comp'     => (int) $parts[6],
                'bpc'      => (int) $parts[7],
                'enc'      => $parts[8],
                'interp'   => $parts[9],
                'objectID' => $parts[10] . ' ' . $parts[11],
                'xDpi'     => (float) $parts[12],
                'yDpi'     => (float) $parts[13],
                'size'     => $parts[14] ?? '',
                'ratio'    => $parts[15] ?? '',
            ];
        }

        return $images;
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
