<?php

namespace Tests\Unit;

use App\Support\DiagnosticMediaFormat;
use PHPUnit\Framework\TestCase;

class DiagnosticMediaFormatTest extends TestCase
{
    public function test_it_accepts_android_m4a_mime_aliases(): void
    {
        foreach (['audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4', 'application/x-m4a'] as $mime) {
            $this->assertTrue(DiagnosticMediaFormat::supports('engine_sound', $mime));
            $this->assertSame('m4a', DiagnosticMediaFormat::extension($mime, 'bin'));
            $this->assertSame('m4a', DiagnosticMediaFormat::openAiAudioFormat($mime));
        }
    }

    public function test_it_rejects_unsupported_content_types(): void
    {
        $this->assertFalse(DiagnosticMediaFormat::supports('engine_sound', 'text/plain'));
        $this->assertFalse(DiagnosticMediaFormat::supports('photo', 'application/octet-stream'));
    }
}
