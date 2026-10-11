<?php

namespace Restruct\Xpdf\Tests;

use PHPUnit\Framework\TestCase;
use Restruct\Xpdf\PdfInfo;
use Restruct\Xpdf\Xpdf;

/**
 * PdfInfo (issue #1): failures throw instead of reporting 0 pages, the parser keeps the first
 * occurrence and drops empty values, and paper sizes match within a tolerance on corrected keys.
 *
 * Tests named *WithPdfinfo run the real pdfinfo binary on small PDFs generated here, and are
 * skipped when it is not available. The others feed canned pdfinfo output to the parser.
 */
class PdfInfoTest extends TestCase
{
    /** Files this test created, removed in tearDown() */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    # --- Failure handling (real binary) ---

    public function testDamagedFileThrowsInsteadOfZeroPagesWithPdfinfo(): void
    {
        $this->requirePdfinfo();
        $file = $this->tempFile('not a pdf');

        # Before the fix: getPageCount() returned 0 and getAll() returned []
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/pdfinfo failed for .* \(exit 1\)/');
        PdfInfo::create($file)->getPageCount();
    }

    public function testGetAllThrowsOnFailureWithPdfinfo(): void
    {
        $this->requirePdfinfo();
        $file = $this->tempFile('not a pdf');

        $this->expectException(\RuntimeException::class);
        PdfInfo::info($file);
    }

    public function testValidFileWithPdfinfo(): void
    {
        $this->requirePdfinfo();
        $info = PdfInfo::create($this->pdf(595.28, 841.89, 0, 2));

        $this->assertSame(2, $info->getPageCount());
        $this->assertSame('A4', $info->getPageDimensions()->standard);
        # No Info dictionary at all, so no Title line
        $this->assertNull($info->getTitle());
    }

    public function testEmptyTitleIsNullWithPdfinfo(): void
    {
        $this->requirePdfinfo();
        $info = PdfInfo::create($this->pdf(595.28, 841.89, 0, 1, ''));

        $this->assertArrayNotHasKey('Title', $info->getAll());
        $this->assertNull($info->getTitle());
    }

    public function testTrueA3LandscapeIsRecognisedWithPdfinfo(): void
    {
        $this->requirePdfinfo();
        # 1190.55 x 841.89 is more than 1 pt off xpdf's own A3, so pdfinfo prints no "(A3)" label,
        # and it rounds to 1191x842, which the old exact key '842x1190' never matched
        $info = PdfInfo::create($this->pdf(1190.55, 841.89));
        $this->assertStringNotContainsString('(A3)', (string) $info->getPageSizeRaw());

        $dims = $info->getPageDimensions();
        $this->assertSame('A3', $dims->standard);
        $this->assertSame('landscape', $dims->orientation);
        $this->assertSame('A3', $dims->nearestDIN);
    }

    public function testRotationIsReadFromPageSizeWithPdfinfo(): void
    {
        $this->requirePdfinfo();
        $info = PdfInfo::create($this->pdf(595.28, 841.89, 90));

        # xpdf prints no "Page rot" line; before the fix this returned 0
        $this->assertSame(90, $info->getPageRotation());
        $this->assertSame(90, $info->getPageDimensions()->rotation);
    }

    # --- Parser (canned output) ---

    public function testFirstOccurrenceWins(): void
    {
        $parsed = $this->parse(
            "Title:          Real title\n"
            . "Pages:          3\n"
            . "Metadata:\n"
            . "Title:          from the XMP stream\n"
        );

        $this->assertSame('Real title', $parsed['Title']);
        $this->assertSame('3', $parsed['Pages']);
    }

    public function testEmptyAndPaddedValuesAreAbsent(): void
    {
        $parsed = $this->parse("Title:          \nAuthor:\nPages:          1   \r\n");

        $this->assertArrayNotHasKey('Title', $parsed);
        $this->assertArrayNotHasKey('Author', $parsed);
        $this->assertSame('1', $parsed['Pages']);
    }

    public function testValueMayContainColons(): void
    {
        $parsed = $this->parse("CreationDate:   Sun Oct 11 17:02:05 2026\nTitle:          Part 2: the rest\n");

        $this->assertSame('Sun Oct 11 17:02:05 2026', $parsed['CreationDate']);
        $this->assertSame('Part 2: the rest', $parsed['Title']);
    }

    # --- Paper sizes (canned output) ---

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function pageSizeProvider(): array
    {
        return [
            'labelled A4'              => ['595.276 x 841.89 pts (A4) (rotated 0 degrees)', 'A4'],
            'unlabelled A4, short'     => ['595.2 x 841.68 pts (rotated 0 degrees)', 'A4'],
            'unlabelled A4, landscape' => ['841 x 595 pts (rotated 0 degrees)', 'A4'],
            'A3 rounded off by one'    => ['841.89 x 1190.55 pts (rotated 0 degrees)', 'A3'],
            'A2'                       => ['1190.55 x 1683.78 pts (rotated 0 degrees)', 'A2'],
            'A1'                       => ['1683.78 x 2383.94 pts (rotated 0 degrees)', 'A1'],
            'A0'                       => ['2383.94 x 3370.39 pts (rotated 0 degrees)', 'A0'],
            'A8'                       => ['147.4 x 209.76 pts (rotated 0 degrees)', 'A8'],
            'C2 (key was wrong)'       => ['1298.27 x 1836.85 pts (rotated 0 degrees)', 'C2'],
            'C3 (key was wrong)'       => ['918.43 x 1298.27 pts (rotated 0 degrees)', 'C3'],
            'C4 portrait'              => ['649.13 x 918.43 pts (rotated 0 degrees)', 'C4'],
            'Letter portrait'          => ['612 x 792 pts (letter) (rotated 0 degrees)', 'Letter'],
            'Letter landscape'         => ['792 x 612 pts (letter) (rotated 0 degrees)', 'Letter'],
            'Tabloid is portrait'      => ['792 x 1224 pts (rotated 0 degrees)', 'Tabloid'],
            'Ledger is landscape'      => ['1224 x 792 pts (rotated 0 degrees)', 'Ledger'],
            '5 pt off A4 is no size'   => ['590 x 836 pts (rotated 0 degrees)', null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageSizeProvider')]
    public function testStandardSize(string $pageSize, ?string $expected): void
    {
        $this->assertSame($expected, $this->withPageSize($pageSize)->getPageDimensions()->standard);
    }

    public function testRotatedA4IsLandscape(): void
    {
        $dims = $this->withPageSize('595.28 x 841.89 pts (A4) (rotated 90 degrees)')->getPageDimensions();

        $this->assertSame(90, $dims->rotation);
        $this->assertSame('landscape', $dims->orientation);
        $this->assertSame([595.0, 842.0], [$dims->width, $dims->height]);
    }

    public function testToleranceCannotPickTheWrongSize(): void
    {
        # Two entries closer than twice the tolerance in BOTH dimensions could both match one page.
        # The only allowed overlaps are the same size under the same name.
        $sizes = (new \ReflectionClassConstant(PdfInfo::class, 'PAPER_SIZES'))->getValue();
        $tolerance = (new \ReflectionClassConstant(PdfInfo::class, 'STANDARD_SIZE_TOLERANCE'))->getValue();

        $entries = [];
        foreach ($sizes as $key => $name) {
            [$w, $h] = array_map('floatval', explode('x', $key));
            # Each orientation, since matching tries both
            $entries[] = [$w, $h, $name];
            $entries[] = [$h, $w, $name];
        }

        $clashes = [];
        foreach ($entries as $i => [$w1, $h1, $n1]) {
            foreach (array_slice($entries, $i + 1) as [$w2, $h2, $n2]) {
                if ($n1 !== $n2 && abs($w1 - $w2) <= 2 * $tolerance && abs($h1 - $h2) <= 2 * $tolerance) {
                    $clashes[] = "$n1 ~ $n2";
                }
            }
        }

        # Tabloid and Ledger are one sheet in two orientations: matching tries the page as-is first
        $clashes = array_diff(array_unique($clashes), ['Tabloid ~ Ledger', 'Ledger ~ Tabloid']);
        $this->assertSame([], array_values($clashes));
    }

    # --- Helpers ---

    private function requirePdfinfo(): void
    {
        if (Xpdf::getToolPath('pdfinfo') === '') {
            $this->markTestSkipped('pdfinfo is not available');
        }
    }

    private function parse(string $output): array
    {
        $method = new \ReflectionMethod(PdfInfo::class, 'parseOutput');

        return $method->invoke(null, $output);
    }

    /**
     * A PdfInfo whose parsed output is a single "Page size" line, without running pdfinfo.
     */
    private function withPageSize(string $pageSize): PdfInfo
    {
        $info = PdfInfo::create($this->tempFile('unused'));
        $parsed = new \ReflectionProperty(PdfInfo::class, 'parsed');
        $parsed->setValue($info, ['Page size' => $pageSize]);

        return $info;
    }

    private function tempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'xpdf-static-test-');
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }

    /**
     * Write a minimal valid PDF: $pages empty pages of $width x $height points with /Rotate $rotate,
     * and an Info dictionary with that Title when $title is given.
     */
    private function pdf(float $width, float $height, int $rotate = 0, int $pages = 1, ?string $title = null): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        for ($i = 0; $i < $pages; $i++) {
            $kids[] = (3 + $i) . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $pages >>";
        for ($i = 0; $i < $pages; $i++) {
            $objects[3 + $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $width $height] /Rotate $rotate >>";
        }
        $infoRef = '';
        if ($title !== null) {
            $infoId = count($objects) + 1;
            $objects[$infoId] = "<< /Title ($title) >>";
            $infoRef = " /Info $infoId 0 R";
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$body\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n" . '0 ' . (count($objects) + 1) . "\n" . "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer' . "\n" . '<< /Size ' . (count($objects) + 1) . " /Root 1 0 R$infoRef >>\n"
            . "startxref\n$xref\n%%EOF\n";

        return $this->tempFile($pdf);
    }
}
