<?php

namespace XpdfStatic;

/**
 * Wrapper class for the xpdf-static bootstrap.
 *
 * Provides a clean API for resolving xpdf tool paths.
 */
class XpdfStatic
{
    private static bool $initialized = false;

    /** Available xpdf tools */
    private const TOOLS = [
        'pdftotext',
        'pdfinfo',
        'pdffonts',
        'pdfimages',
        'pdftohtml',
        'pdftopng',
        'pdftoppm',
        'pdftops',
        'pdfdetach',
    ];

    /**
     * Include the bootstrap file (once).
     */
    public static function init(): void
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/../bootstrap.php';
            self::$initialized = true;
        }
    }

    /**
     * Get the resolved bin directory containing xpdf tools.
     *
     * @return string The directory path, or empty string if not resolved
     */
    public static function getBinDir(): string
    {
        self::init();

        return defined('XPDF_BIN_DIR') ? XPDF_BIN_DIR : '';
    }

    /**
     * Get the path to a specific xpdf tool.
     *
     * @param string $tool Tool name (e.g. 'pdftotext', 'pdfinfo')
     * @return string Full path to the tool, or empty string if unavailable
     */
    public static function getToolPath(string $tool): string
    {
        $binDir = self::getBinDir();
        if ($binDir === '') {
            return '';
        }

        $path = $binDir . '/' . $tool;

        return is_executable($path) ? $path : '';
    }

    /**
     * Check whether xpdf tools are available.
     */
    public static function isAvailable(): bool
    {
        return self::getToolPath('pdftotext') !== '';
    }

    /**
     * List available tool names.
     *
     * @return string[] Tool names that have executable binaries
     */
    public static function availableTools(): array
    {
        $binDir = self::getBinDir();
        if ($binDir === '') {
            return [];
        }

        $available = [];
        foreach (self::TOOLS as $tool) {
            if (is_executable($binDir . '/' . $tool)) {
                $available[] = $tool;
            }
        }

        return $available;
    }
}
