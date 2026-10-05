<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController
{
    public function __invoke(User $user, string $version): StreamedResponse
    {
        $path = $user->avatar_path;
        abort_unless($path && hash_equals(hash('sha256', $path), $version), 404);

        $disk = Storage::disk(config('automind.media.disk'));
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], disposition: 'inline');
    }
}
