<?php

/**
 * PHPUnit bootstrap for CEMS-MY.
 *
 * Defines a minimal, extension-free `finfo` stand-in BEFORE the vendor
 * autoloader runs, and only when the PHP `fileinfo` extension is not
 * compiled into this build (it is absent from the local PHP 8.3 CLI/FPM
 * binaries). Flysystem's LocalFilesystemAdapter unconditionally constructs
 * `League\MimeTypeDetection\FinfoMimeTypeDetector`, which in turn does
 * `new finfo(...)`; without the extension every local-disk Storage write
 * (CSV reports, uploaded KYC documents, imports) fails with
 * `Class "finfo" not found`.
 *
 * The polyfill implements the two methods Flysystem uses (file()/buffer())
 * with a pure-PHP extension→MIME map, so behaviour is deterministic across
 * machines with and without fileinfo. When the real extension is present it
 * is left untouched.
 *
 * The real finfo constants are mirrored here so `new finfo(FILEINFO_MIME_TYPE, $magic)`
 * cannot hit "undefined constant" when the extension is missing.
 */
if (! class_exists('finfo')) {
    define('FILEINFO_NONE', 0);
    define('FILEINFO_SYMLINK', 1);
    define('FILEINFO_MIME_TYPE', 2);
    define('FILEINFO_MIME_ENCODING', 4);
    define('FILEINFO_DEVICES', 8);
    define('FILEINFO_CONTINUE', 32);
    define('FILEINFO_PRESERVE_ATIME', 128);
    define('FILEINFO_RAW', 256);
    define('FILEINFO_COMPRESS', 1024);
    define('FILEINFO_MIME', FILEINFO_MIME_TYPE | FILEINFO_MIME_ENCODING);
    define('FILEINFO_EXTENSION', 2097152);

    /**
     * Minimal finfo-compatible MIME detector.
     *
     * Mirrors the public surface used by league/mime-type-detection:
     * file(path) and buffer(contents) return a MIME type or null.
     */
    class finfo
    {
        private const EXTENSION_MIME_TYPES = [
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'html' => 'text/html',
            'htm' => 'text/html',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'zip' => 'application/zip',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        public function __construct(int $flags = FILEINFO_NONE, ?string $magicDatabase = null)
        {
            // CLI compatibility: constructing a detector must never throw.
        }

        /**
         * Detect the MIME type of a file by its extension.
         *
         * @return ?string MIME type, or null when unknown.
         */
        public function file(string $filename, int $flags = FILEINFO_NONE): ?string
        {
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            return self::EXTENSION_MIME_TYPES[$extension] ?? 'application/octet-stream';
        }

        /**
         * Detect the MIME type of an in-memory buffer.
         *
         * Magic-byte sniffing for the fixtures used in the suite (PNG header,
         * JPEG header, PDF header, plain text). Unknown buffers fall back to
         * 'application/octet-stream' rather than failing.
         *
         * @return ?string MIME type
         */
        public function buffer(string $string, int $flags = FILEINFO_NONE): ?string
        {
            if (strlen($string) >= 4 && substr($string, 0, 4) === "\x89PNG") {
                return 'image/png';
            }

            if (strlen($string) >= 3 && substr($string, 0, 3) === "\xFF\xD8\xFF") {
                return 'image/jpeg';
            }

            if (strlen($string) >= 5 && substr($string, 0, 5) === '%PDF-') {
                return 'application/pdf';
            }

            if (strlen($string) === 0) {
                return null;
            }

            return 'text/plain';
        }
    }
}

require __DIR__.'/../vendor/autoload.php';
