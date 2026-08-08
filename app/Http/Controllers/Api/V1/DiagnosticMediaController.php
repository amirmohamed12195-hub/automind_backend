<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ObjectStorageProvider;
use App\Http\Requests\DiagnosticMediaRequest;
use App\Jobs\ProcessDiagnosticMedia;
use App\Models\DiagnosticMedia;
use App\Models\DiagnosticSession;
use App\Services\Media\MediaToolchain;
use App\Services\Media\WavAudioInspector;
use App\Support\ApiResponse;
use App\Support\DiagnosticMediaFormat;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Process\Process;
use Throwable;

class DiagnosticMediaController
{
    public function __construct(
        private readonly MediaToolchain $toolchain,
        private readonly WavAudioInspector $wavInspector,
    ) {}

    public function store(DiagnosticMediaRequest $request, DiagnosticSession $diagnosis, ObjectStorageProvider $storage)
    {
        Gate::authorize('update', $diagnosis);
        if (! in_array($diagnosis->status, ['draft', 'uploading'], true)) {
            return ApiResponse::error('DIAGNOSIS_IMMUTABLE', __('api.diagnosis_immutable'), 409);
        }
        $kind = $request->input('kind');
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        if (! DiagnosticMediaFormat::supports($kind, $mime)) {
            return ApiResponse::error('UNSUPPORTED_MEDIA', __('api.validation_failed'), 422, ['file' => [__('api.unsupported_media')]]);
        }
        $active = $diagnosis->media()->whereNull('deleted_at');
        if ($kind === 'photo' && (clone $active)->where('media_kind', 'photo')->count() >= 6) {
            return ApiResponse::error('MEDIA_LIMIT_REACHED', __('api.media_limit'), 409);
        }
        if ($kind !== 'photo' && (clone $active)->where('media_kind', $kind)->exists()) {
            return ApiResponse::error('MEDIA_LIMIT_REACHED', __('api.media_limit'), 409);
        }
        $sha = hash_file('sha256', $file->getRealPath());
        if ((clone $active)->where('sha256', $sha)->exists()) {
            return ApiResponse::error('DUPLICATE_MEDIA', __('api.duplicate_media'), 409);
        }
        $width = $height = $duration = $sampleRate = $channels = null;
        if ($kind === 'photo') {
            $dimensions = @getimagesize($file->getRealPath());
            if (! $dimensions) {
                return ApiResponse::error('MALFORMED_MEDIA', __('api.malformed_media'), 422);
            }
            [$width, $height] = $dimensions;
            if (max($width, $height) > config('automind.media.max_image_dimension')) {
                return ApiResponse::error('IMAGE_DIMENSIONS_EXCEEDED', __('api.image_dimensions_exceeded'), 422);
            }
        } elseif (DiagnosticMediaFormat::isWav($mime)) {
            try {
                $metadata = $this->wavInspector->inspect($file->getRealPath());
                $duration = $metadata['durationMilliseconds'];
                $sampleRate = $metadata['sampleRate'];
                $channels = $metadata['channels'];
            } catch (Throwable) {
                return ApiResponse::error('MALFORMED_MEDIA', __('api.malformed_media'), 422);
            }
            if ($kind === 'engine_sound' && $duration > 30000) {
                return ApiResponse::error('AUDIO_TOO_LONG', __('api.audio_too_long'), 422);
            }
        } else {
            try {
                $duration = $this->durationMilliseconds($file->getRealPath());
            } catch (Throwable) {
                return ApiResponse::error('AUDIO_VALIDATION_UNAVAILABLE', __('api.audio_validation_unavailable'), 503);
            }
            if ($kind === 'engine_sound' && $duration > 30000) {
                return ApiResponse::error('AUDIO_TOO_LONG', __('api.audio_too_long'), 422);
            }
        }
        $stored = $storage->storePrivate($file, "diagnostics/{$diagnosis->id}");
        $media = DiagnosticMedia::query()->create(['diagnostic_session_id' => $diagnosis->id, 'media_kind' => $kind, 'storage_disk' => $stored['disk'], 'storage_path' => $stored['path'], 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => $mime, 'extension' => $stored['extension'], 'byte_size' => $stored['byteSize'], 'sha256' => $sha, 'width' => $width, 'height' => $height, 'duration_milliseconds' => $duration, 'sample_rate' => $sampleRate, 'channels' => $channels, 'upload_status' => 'uploaded', 'scan_status' => config('automind.media.clamav_command') ? 'pending' : 'not_configured', 'processing_status' => 'pending']);
        $diagnosis->update(['status' => 'uploading']);
        ProcessDiagnosticMedia::dispatch($media->id)->afterCommit();

        return ApiResponse::success(['id' => (string) $media->id, 'kind' => $media->media_kind, 'mimeType' => $mime, 'byteSize' => (int) $media->byte_size, 'width' => $width, 'height' => $height, 'durationMilliseconds' => $duration, 'sha256' => $sha], 201);
    }

    public function destroy(DiagnosticSession $diagnosis, DiagnosticMedia $media, ObjectStorageProvider $storage)
    {
        Gate::authorize('update', $diagnosis);
        if ($media->diagnostic_session_id !== $diagnosis->id) {
            abort(404);
        }
        if (! in_array($diagnosis->status, ['draft', 'uploading'], true)) {
            return ApiResponse::error('DIAGNOSIS_IMMUTABLE', __('api.diagnosis_immutable'), 409);
        }
        $storage->delete($media->storage_disk, $media->storage_path);
        $media->update(['deleted_at' => now(), 'processing_status' => 'deleted']);

        return response()->noContent();
    }

    private function durationMilliseconds(string $path): int
    {
        $process = new Process([$this->toolchain->ffprobe(), '-v', 'error', '-select_streams', 'a:0', '-show_entries', 'stream=codec_type:format=duration', '-of', 'json', $path]);
        $process->setTimeout(15);
        $process->mustRun();
        $probe = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        if (($probe['streams'][0]['codec_type'] ?? null) !== 'audio') {
            throw new \RuntimeException('The uploaded file does not contain an audio stream.');
        }
        $seconds = (float) ($probe['format']['duration'] ?? 0);
        if ($seconds <= 0 || $seconds > 600) {
            throw new \RuntimeException('Invalid audio duration.');
        }

        return (int) round($seconds * 1000);
    }
}
