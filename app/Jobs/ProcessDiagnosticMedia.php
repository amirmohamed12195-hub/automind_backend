<?php

namespace App\Jobs;

use App\Models\DiagnosticMedia;
use App\Services\Media\MediaToolchain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessDiagnosticMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 90;

    public function __construct(public readonly string $mediaId)
    {
        $this->onQueue('media-processing');
    }

    public function backoff(): array
    {
        return [random_int(25, 35), random_int(165, 195)];
    }

    public function handle(MediaToolchain $toolchain): void
    {
        $media = DiagnosticMedia::query()->findOrFail($this->mediaId);
        if ($media->deleted_at) {
            return;
        }
        $input = tempnam(sys_get_temp_dir(), 'automind-media-');
        if ($input === false) {
            throw new \RuntimeException('Temporary media allocation failed.');
        }
        try {
            file_put_contents($input, Storage::disk($media->storage_disk)->get($media->storage_path));
            if ($scanner = config('automind.media.clamav_command')) {
                $scan = new Process([(string) $scanner, '--no-summary', $input]);
                $scan->setTimeout(30);
                $scan->run();
                if ($scan->getExitCode() === 1) {
                    Storage::disk($media->storage_disk)->delete($media->storage_path);
                    $media->update(['scan_status' => 'infected', 'processing_status' => 'failed', 'failure_code' => 'malware_detected']);

                    return;
                } if (! $scan->isSuccessful()) {
                    throw new \RuntimeException('Malware scanner failed.');
                } $media->update(['scan_status' => 'clean']);
            } else {
                $media->update(['scan_status' => 'not_configured']);
            }
            if ($media->media_kind !== 'photo') {
                $this->normalizeAudio($media, $input, $toolchain);
            }
            $media->update(['processing_status' => 'ready', 'failure_code' => null]);
        } catch (Throwable $e) {
            $media->update(['processing_status' => 'failed', 'failure_code' => 'media_processing_failed']);
            throw $e;
        } finally {
            @unlink($input);
        }
    }

    private function normalizeAudio(DiagnosticMedia $media, string $input, MediaToolchain $toolchain): void
    {
        $output = tempnam(sys_get_temp_dir(), 'automind-audio-');
        if ($output === false) {
            throw new \RuntimeException('Temporary audio allocation failed.');
        } @unlink($output);
        $output .= '.wav';
        try {
            $process = new Process([$toolchain->ffmpeg(), '-nostdin', '-y', '-i', $input, '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'pcm_s16le', $output]);
            $process->setTimeout(60);
            $process->mustRun();
            $newPath = preg_replace('/\.[^.]+$/', '.normalized.wav', $media->storage_path);
            Storage::disk($media->storage_disk)->put($newPath, file_get_contents($output), ['visibility' => 'private']);
            Storage::disk($media->storage_disk)->delete($media->storage_path);
            $media->update(['storage_path' => $newPath, 'mime_type' => 'audio/wav', 'extension' => 'wav', 'byte_size' => filesize($output), 'sample_rate' => 16000, 'channels' => 1]);
        } finally {
            @unlink($output);
        }
    }
}
