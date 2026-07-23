<?php

namespace App\Services\Storage;

use App\Contracts\ObjectStorageProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LaravelObjectStorageProvider implements ObjectStorageProvider
{
    public function storePrivate(UploadedFile $file, string $directory): array
    {
        $disk = (string) config('automind.media.disk');
        $mime = (string) $file->getMimeType();
        $extension = match ($mime) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'audio/mpeg' => 'mp3', 'audio/wav', 'audio/x-wav' => 'wav', 'audio/mp4', 'audio/x-m4a' => 'm4a', 'audio/ogg' => 'ogg', 'audio/webm' => 'webm', default => strtolower($file->getClientOriginalExtension())
        };
        $temporary = null;
        if (str_starts_with($mime, 'image/')) {
            $temporary = $this->stripImageMetadata($file, $mime, $extension);
            $file = $temporary;
        }
        try {
            $name = bin2hex(random_bytes(20)).'.'.$extension;
            $path = Storage::disk($disk)->putFileAs($directory, $file, $name, ['visibility' => 'private']);
            if (! is_string($path)) {
                throw new RuntimeException('Private file storage failed.');
            }

            return ['disk' => $disk, 'path' => $path, 'mimeType' => $mime, 'extension' => $extension, 'byteSize' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())];
        } finally {
            if ($temporary) {
                @unlink($temporary->getRealPath());
            }
        }
    }

    public function temporaryUrl(string $disk, string $path): string
    {
        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(config('automind.media.signed_url_ttl_minutes')));
    }

    public function delete(string $disk, string $path): void
    {
        Storage::disk($disk)->delete($path);
    }

    private function stripImageMetadata(UploadedFile $file, string $mime, string $extension): UploadedFile
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()), 'image/png' => @imagecreatefrompng($file->getRealPath()), 'image/webp' => @imagecreatefromwebp($file->getRealPath()), default => false
        };
        if (! $image) {
            throw new RuntimeException('Image decoding failed.');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $image = match ($orientation) {
                3 => imagerotate($image, 180, 0), 6 => imagerotate($image, -90, 0), 8 => imagerotate($image, 90, 0), default => $image
            };
        }
        $maxDimension = (int) config('automind.media.provider_image_max_dimension');
        if ($maxDimension > 0 && max(imagesx($image), imagesy($image)) > $maxDimension) {
            $scale = $maxDimension / max(imagesx($image), imagesy($image));
            $resized = imagescale($image, max(1, (int) round(imagesx($image) * $scale)), max(1, (int) round(imagesy($image) * $scale)));
            if ($resized === false) {
                imagedestroy($image);
                throw new RuntimeException('Image resizing failed.');
            }
            imagedestroy($image);
            $image = $resized;
        }
        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        $path = tempnam(sys_get_temp_dir(), 'automind-image-');
        if ($path === false) {
            throw new RuntimeException('Temporary image allocation failed.');
        }
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $path, 88), 'image/png' => imagepng($image, $path, 6), 'image/webp' => imagewebp($image, $path, 88), default => false
        };
        imagedestroy($image);
        if (! $saved) {
            @unlink($path);
            throw new RuntimeException('Image sanitization failed.');
        }

        return new UploadedFile($path, 'sanitized.'.$extension, $mime, null, true);
    }
}
