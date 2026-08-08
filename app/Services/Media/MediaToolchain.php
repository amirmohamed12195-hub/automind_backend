<?php

namespace App\Services\Media;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class MediaToolchain
{
    public function ffmpeg(): string
    {
        return $this->resolve('ffmpeg', (string) config('automind.media.ffmpeg_path'));
    }

    public function ffprobe(): string
    {
        return $this->resolve('ffprobe', (string) config('automind.media.ffprobe_path'));
    }

    public function assertAvailable(): void
    {
        foreach ([$this->ffmpeg(), $this->ffprobe()] as $executable) {
            $process = new Process([$executable, '-version']);
            $process->setTimeout(5);
            $process->mustRun();
        }
    }

    private function resolve(string $name, string $configuredPath): string
    {
        $configuredPath = trim($configuredPath);
        if ($configuredPath !== '' && is_file($configuredPath) && is_executable($configuredPath)) {
            return $configuredPath;
        }

        $finder = new ExecutableFinder;
        $candidates = array_values(array_unique(array_filter([
            $configuredPath !== '' ? basename($configuredPath) : null,
            $name,
        ])));

        foreach ($candidates as $candidate) {
            $resolved = $finder->find($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw new RuntimeException(
            sprintf('%s is unavailable. Install it or configure %s_PATH with an executable path.', $name, strtoupper($name)),
        );
    }
}
