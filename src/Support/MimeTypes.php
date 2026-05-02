<?php

namespace GoogleAgentPlatform\Support;

/**
 * MIME type utilities — extension mapping and auto-detection from file content.
 */
class MimeTypes
{
    /** @var array<string, string> MIME type → file extension */
    private const EXTENSION_MAP = [
        // Images
        'image/png'       => 'png',
        'image/jpeg'      => 'jpg',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'image/bmp'       => 'bmp',
        'image/tiff'      => 'tiff',
        // Audio
        'audio/mp3'       => 'mp3',
        'audio/mpeg'      => 'mp3',
        'audio/wav'       => 'wav',
        'audio/x-wav'     => 'wav',
        'audio/ogg'       => 'ogg',
        'audio/flac'      => 'flac',
        'audio/aac'       => 'aac',
        'audio/webm'      => 'weba',
        // Video
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
        'video/mpeg'      => 'mpeg',
        // Documents
        'application/pdf' => 'pdf',
        'text/plain'      => 'txt',
        'text/html'       => 'html',
        'text/csv'        => 'csv',
        'application/json'=> 'json',
    ];

    /**
     * Map a MIME type string to a file extension.
     * Returns 'bin' for unknown types.
     */
    public static function toExtension(string $mimeType): string
    {
        return self::EXTENSION_MAP[$mimeType] ?? 'bin';
    }

    /**
     * Detect the MIME type of a local file.
     *
     * Uses finfo if available (most reliable), falls back to
     * extension-based guessing, then defaults to 'application/octet-stream'.
     */
    public static function detect(string $filePath): string
    {
        // Prefer finfo (reads magic bytes)
        if (\function_exists('finfo_open')) {
            $finfo = \finfo_open(FILEINFO_MIME_TYPE);
            $mime  = \finfo_file($finfo, $filePath);
            \finfo_close($finfo);

            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        // Fall back to extension mapping
        $ext = \strtolower(\pathinfo($filePath, PATHINFO_EXTENSION));
        $map = \array_flip(self::EXTENSION_MAP);

        return $map[$ext] ?? 'application/octet-stream';
    }

    /**
     * Return all supported MIME types.
     *
     * @return string[]
     */
    public static function supported(): array
    {
        return \array_keys(self::EXTENSION_MAP);
    }
}
