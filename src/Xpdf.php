<?php

namespace Xpdf;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Main entry point for xpdf tools.
 *
 * Resolves binary paths (OS-aware) and provides a generic run() method
 * for executing any xpdf tool via Symfony Process.
 */
class Xpdf
{
    private static bool $initialized = false;

    /** Default process timeout in seconds */
    private static int $defaultTimeout = 30;

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

    /**
     * Set the default timeout for all tool executions.
     */
    public static function setDefaultTimeout(int $seconds): void
    {
        self::$defaultTimeout = $seconds;
    }

    /**
     * Get the default timeout.
     */
    public static function getDefaultTimeout(): int
    {
        return self::$defaultTimeout;
    }

    /**
     * Run an xpdf tool with the given arguments.
     *
     * Uses Symfony Process for proper timeout/error handling.
     *
     * @param string $tool Tool name (e.g. 'pdftotext')
     * @param array $args Command-line arguments
     * @param int|null $timeout Timeout in seconds (null = default)
     * @return XpdfResult
     * @throws \RuntimeException if tool binary is not available
     */
    public static function run(string $tool, array $args = [], ?int $timeout = null): XpdfResult
    {
        $toolPath = self::getToolPath($tool);
        if ($toolPath === '') {
            throw new \RuntimeException("xpdf tool '$tool' is not available");
        }

        $command = array_merge([$toolPath], $args);
        $process = new Process($command);
        $process->setTimeout($timeout ?? self::$defaultTimeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return new XpdfResult('', "Process timed out after {$process->getTimeout()}s", -1);
        }

        return new XpdfResult(
            $process->getOutput(),
            $process->getErrorOutput(),
            $process->getExitCode() ?? -1
        );
    }
}
