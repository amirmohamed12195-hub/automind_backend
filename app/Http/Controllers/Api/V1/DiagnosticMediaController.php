<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\ObjectStorageProvider;
use App\Http\Requests\DiagnosticMediaRequest;
use App\Jobs\ProcessDiagnosticMedia;
use App\Models\DiagnosticMedia;
use App\Models\DiagnosticSession;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Process\Process;
use Throwable;

class DiagnosticMediaController
{
    public function store(DiagnosticMediaRequest $request, DiagnosticSession $diagnosis, ObjectStorageProvider $storage)
    {
        Gate::authorize('update', $diagnosis);
        if (! in_array($diagnosis->status, ['draft', 'uploading'], true)) {
            return ApiResponse::error('DIAGNOSIS_IMMUTABLE', __('api.diagnosis_immutable'), 409);
        }
        $kind = $request->input('kind');
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        $allowed = $kind === 'photo' ? ['image/jpeg', 'image/png', 'image/webp'] : ['audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/webm'];
        if (! in_array($mime, $allowed, true)) {
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
        $width = $height = $duration = null;
        if ($kind === 'photo') {
            $dimensions = @getimagesize($file->getRealPath());
            if (! $dimensions) {
                return ApiResponse::error('MALFORMED_MEDIA', __('api.malformed_media'), 422);
            }
            [$width, $height] = $dimensions;
            if (max($width, $height) > config('automind.media.max_image_dimension')) {
                return ApiResponse::error('IMAGE_DIMENSIONS_EXCEEDED', __('api.image_dimensions_exceeded'), 422);
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
        $media = DiagnosticMedia::query()->create(['diagnostic_session_id' => $diagnosis->id, 'media_kind' => $kind, 'storage_disk' => $stored['disk'], 'storage_path' => $stored['path'], 'original_filename' => mb_substr(basename($file->getClientOriginalName()), 0, 255), 'mime_type' => $mime, 'extension' => $stored['extension'], 'byte_size' => $stored['byteSize'], 'sha256' => $sha, 'width' => $width, 'height' => $height, 'duration_milliseconds' => $duration, 'upload_status' => 'uploaded', 'scan_status' => config('automind.media.clamav_command') ? 'pending' : 'not_configured', 'processing_status' => 'pending']);
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
        $process = new Process([(string) config('automind.media.ffprobe_path'), '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $path]);
        $process->setTimeout(15);
        $process->mustRun();
        $seconds = (float) trim($process->getOutput());
        if ($seconds <= 0 || $seconds > 600) {
            throw new \RuntimeException('Invalid audio duration.');
        }

        return (int) round($seconds * 1000);
    }
}
