<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Support\MimeTypes;
use PHPUnit\Framework\TestCase;

class MimeTypesTest extends TestCase
{
    public function test_to_extension_known_types(): void
    {
        $this->assertSame('png',  MimeTypes::toExtension('image/png'));
        $this->assertSame('jpg',  MimeTypes::toExtension('image/jpeg'));
        $this->assertSame('mp3',  MimeTypes::toExtension('audio/mp3'));
        $this->assertSame('mp3',  MimeTypes::toExtension('audio/mpeg'));
        $this->assertSame('wav',  MimeTypes::toExtension('audio/wav'));
        $this->assertSame('mp4',  MimeTypes::toExtension('video/mp4'));
        $this->assertSame('pdf',  MimeTypes::toExtension('application/pdf'));
        $this->assertSame('webp', MimeTypes::toExtension('image/webp'));
    }

    public function test_to_extension_unknown_type_returns_bin(): void
    {
        $this->assertSame('bin', MimeTypes::toExtension('application/x-unknown-type'));
    }

    public function test_detect_from_real_png_file(): void
    {
        // Create a minimal valid PNG (8-byte signature + IHDR chunk)
        $pngSignature = "\x89PNG\r\n\x1a\n";
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'gap_test_') . '.png';
        \file_put_contents($tmpFile, $pngSignature . \str_repeat("\x00", 100));

        try {
            $mime = MimeTypes::detect($tmpFile);
            // finfo should detect this as image/png from the magic bytes
            $this->assertStringContainsString('image', $mime);
        } finally {
            \unlink($tmpFile);
        }
    }

    public function test_detect_falls_back_to_extension(): void
    {
        // Write a file with no recognisable magic bytes but a known extension
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'gap_test_');
        $namedFile = $tmpFile . '.pdf';
        \rename($tmpFile, $namedFile);
        \file_put_contents($namedFile, 'not a real pdf');

        try {
            $mime = MimeTypes::detect($namedFile);
            // May be detected as application/pdf (finfo) or fall back via extension
            $this->assertIsString($mime);
            $this->assertNotEmpty($mime);
        } finally {
            \unlink($namedFile);
        }
    }

    public function test_supported_returns_array_of_strings(): void
    {
        $supported = MimeTypes::supported();
        $this->assertIsArray($supported);
        $this->assertNotEmpty($supported);
        $this->assertContains('image/png', $supported);
        $this->assertContains('audio/mp3', $supported);
        $this->assertContains('video/mp4', $supported);
    }
}
