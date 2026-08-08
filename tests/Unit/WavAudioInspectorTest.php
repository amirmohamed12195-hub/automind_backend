<?php

namespace Tests\Unit;

use App\Services\Media\WavAudioInspector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WavAudioInspectorTest extends TestCase
{
    public function test_it_inspects_pcm_wav_without_external_media_tools(): void
    {
        $sampleRate = 16000;
        $samples = str_repeat("\0\0", $sampleRate);
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVE'
            .'fmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;
        $path = tempnam(sys_get_temp_dir(), 'automind-wav-test-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, $wav);

            self::assertSame([
                'durationMilliseconds' => 1000,
                'sampleRate' => 16000,
                'channels' => 1,
                'bitsPerSample' => 16,
            ], (new WavAudioInspector)->inspect($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_truncated_wav_data(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'automind-wav-test-');
        self::assertNotFalse($path);

        try {
            file_put_contents(
                $path,
                'RIFF'.pack('V', 136).'WAVE'
                    .'fmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16)
                    .'data'.pack('V', 100),
            );

            $this->expectException(InvalidArgumentException::class);
            (new WavAudioInspector)->inspect($path);
        } finally {
            @unlink($path);
        }
    }
}
