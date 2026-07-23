<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ObjectStorageProvider
{
    public function storePrivate(UploadedFile $file, string $directory): array;

    public function temporaryUrl(string $disk, string $path): string;

    public function delete(string $disk, string $path): void;
}
