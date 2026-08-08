<?php

namespace App\Support;

final class DiagnosticMediaFormat
{
    private const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const AUDIO_MIME_TYPES = [
        'audio/mpeg',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
        'video/mp4',
        'application/mp4',
        'application/x-m4a',
        'audio/ogg',
        'audio/webm',
    ];

    public static function supports(string $kind, string $mime): bool
    {
        $allowed = $kind === 'photo' ? self::IMAGE_MIME_TYPES : self::AUDIO_MIME_TYPES;

        return in_array(strtolower(trim($mime)), $allowed, true);
    }

    public static function extension(string $mime, string $fallback): string
    {
        return match (strtolower(trim($mime))) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mp4', 'audio/x-m4a', 'video/mp4', 'application/mp4', 'application/x-m4a' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            default => strtolower($fallback),
        };
    }

    public static function isWav(string $mime): bool
    {
        return in_array(strtolower(trim($mime)), ['audio/wav', 'audio/x-wav'], true);
    }

    public static function openAiAudioFormat(string $mime): string
    {
        return match (strtolower(trim($mime))) {
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mpeg' => 'mp3',
            default => 'm4a',
        };
    }
}
