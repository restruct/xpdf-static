<?php

/**
 * Bootstrap for restruct/xpdf-static
 *
 * OS-aware path resolver for xpdf tools (pdftotext, pdfinfo, etc.).
 * Safe to include multiple times — guarded by defined() check.
 *
 * Defines XPDF_BIN_DIR pointing to the directory containing the binaries.
 * Individual tools: XPDF_BIN_DIR . '/pdftotext', XPDF_BIN_DIR . '/pdfinfo', etc.
 *
 * Priority:
 * 1. Already defined XPDF_BIN_DIR constant
 * 2. XPDF_BIN_DIR environment variable
 * 3. macOS: bundled x64/mac binaries
 * 4. Linux: bundled x64/linux binaries
 */

if (!defined('XPDF_BIN_DIR')) {
    $binDir = null;

    # Check environment variable first
    $envPath = getenv('XPDF_BIN_DIR');
    if ($envPath !== false && $envPath !== '' && is_dir($envPath)) {
        $binDir = $envPath;
    }

    if ($binDir === null) {
        switch (PHP_OS_FAMILY) {
            case 'Darwin':
                $candidate = __DIR__ . '/x64/mac';
                if (is_dir($candidate) && is_executable($candidate . '/pdftotext')) {
                    $binDir = $candidate;
                }
                break;

            case 'Linux':
                $candidate = __DIR__ . '/x64/linux';
                if (is_dir($candidate) && is_executable($candidate . '/pdftotext')) {
                    $binDir = $candidate;
                }
                break;
        }
    }

    if ($binDir !== null) {
        define('XPDF_BIN_DIR', $binDir);
    }
}
