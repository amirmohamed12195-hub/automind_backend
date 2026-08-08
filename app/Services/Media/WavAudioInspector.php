<?php

namespace App\Services\Media;

use InvalidArgumentException;

class WavAudioInspector
{
    /** @return array{durationMilliseconds: int, sampleRate: int, channels: int, bitsPerSample: int} */
    public function inspect(string $path): array
    {
        $bytes = @file_get_contents($path);
        if (! is_string($bytes) || strlen($bytes) < 44) {
            throw new InvalidArgumentException('The WAV file is incomplete.');
        }
        if (substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WAVE') {
            throw new InvalidArgumentException('The file is not a RIFF/WAVE audio file.');
        }

        $length = strlen($bytes);
        $offset = 12;
        $format = null;
        $dataSize = null;

        while ($offset + 8 <= $length) {
            $chunk = substr($bytes, $offset, 4);
            $chunkSize = $this->uint32($bytes, $offset + 4);
            $chunkOffset = $offset + 8;
            if ($chunkSize > $length - $chunkOffset) {
                throw new InvalidArgumentException('The WAV file contains a truncated chunk.');
            }

            if ($chunk === 'fmt ') {
                if ($chunkSize < 16) {
                    throw new InvalidArgumentException('The WAV format chunk is malformed.');
                }
                $format = [
                    'audioFormat' => $this->uint16($bytes, $chunkOffset),
                    'channels' => $this->uint16($bytes, $chunkOffset + 2),
                    'sampleRate' => $this->uint32($bytes, $chunkOffset + 4),
                    'byteRate' => $this->uint32($bytes, $chunkOffset + 8),
                    'blockAlign' => $this->uint16($bytes, $chunkOffset + 12),
                    'bitsPerSample' => $this->uint16($bytes, $chunkOffset + 14),
                ];
            } elseif ($chunk === 'data') {
                $dataSize = $chunkSize;
            }

            if ($format !== null && $dataSize !== null) {
                break;
            }
            $offset = $chunkOffset + $chunkSize + ($chunkSize % 2);
        }

        if ($format === null || $dataSize === null || $dataSize <= 0) {
            throw new InvalidArgumentException('The WAV file has no playable audio data.');
        }
        if ($format['audioFormat'] !== 1 || $format['bitsPerSample'] !== 16) {
            throw new InvalidArgumentException('Only 16-bit PCM WAV audio is supported.');
        }
        if (! in_array($format['channels'], [1, 2], true)
            || $format['sampleRate'] < 8000
            || $format['sampleRate'] > 48000
            || $format['byteRate'] <= 0
            || $format['blockAlign'] <= 0
            || $dataSize % $format['blockAlign'] !== 0) {
            throw new InvalidArgumentException('The WAV audio parameters are invalid.');
        }

        $expectedByteRate = $format['sampleRate'] * $format['channels'] * 2;
        if ($format['byteRate'] !== $expectedByteRate || $format['blockAlign'] !== $format['channels'] * 2) {
            throw new InvalidArgumentException('The WAV audio layout is inconsistent.');
        }
        $duration = (int) round(($dataSize / $format['byteRate']) * 1000);
        if ($duration <= 0 || $duration > 600000) {
            throw new InvalidArgumentException('The WAV audio duration is invalid.');
        }

        return [
            'durationMilliseconds' => $duration,
            'sampleRate' => $format['sampleRate'],
            'channels' => $format['channels'],
            'bitsPerSample' => $format['bitsPerSample'],
        ];
    }

    private function uint16(string $bytes, int $offset): int
    {
        $value = unpack('vvalue', substr($bytes, $offset, 2));

        return (int) ($value['value'] ?? 0);
    }

    private function uint32(string $bytes, int $offset): int
    {
        $value = unpack('Vvalue', substr($bytes, $offset, 4));

        return (int) ($value['value'] ?? 0);
    }
}
