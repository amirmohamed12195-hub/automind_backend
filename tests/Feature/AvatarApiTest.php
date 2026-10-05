<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AvatarApiTest extends ApiTestCase
{
    public function test_uploaded_avatar_is_visible_without_storage_temporary_url_support(): void
    {
        config(['filesystems.disks.local.serve' => false]);
        Storage::fake('local');
        $user = $this->actingAsUser();

        $upload = $this->post('/api/v1/me/avatar', [
            'avatar' => UploadedFile::fake()->image('portrait.png', 120, 120),
        ], ['Accept' => 'application/json'])->assertOk();

        $url = $upload->json('data.avatarUrl');
        $this->assertIsString($url);
        $this->assertNotEmpty($url);
        Storage::disk('local')->assertExists($user->fresh()->avatar_path);

        $this->app['auth']->forgetGuards();
        $image = $this->get($url)->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $image->headers->get('Cache-Control'));
        $this->assertSame(Storage::disk('local')->get($user->fresh()->avatar_path), $image->streamedContent());
    }

    public function test_avatar_url_requires_its_original_signature_and_user(): void
    {
        Storage::fake('local');
        $user = $this->actingAsUser();
        $url = $this->uploadAvatar();
        $path = parse_url($url, PHP_URL_PATH);

        $this->get($path)->assertForbidden();
        $this->get($url.'&extra=1')->assertForbidden();
        $otherUser = User::factory()->create();
        $this->get(str_replace($user->id, $otherUser->id, $url))->assertForbidden();
    }

    public function test_expired_avatar_url_is_rejected_and_profile_returns_a_fresh_url(): void
    {
        Storage::fake('local');
        $this->actingAsUser();
        $url = $this->uploadAvatar();

        $this->travel(config('automind.media.signed_url_ttl_minutes') + 1)->minutes();
        $this->get($url)->assertForbidden();
        $freshUrl = $this->getJson('/api/v1/me')->assertOk()->json('data.avatarUrl');
        $this->assertNotSame($url, $freshUrl);
        $this->get($freshUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_replacement_changes_avatar_url_and_revokes_previous_image(): void
    {
        Storage::fake('local');
        $user = $this->actingAsUser();
        $oldUrl = $this->uploadAvatar();
        $oldPath = $user->fresh()->avatar_path;

        $newUrl = $this->uploadAvatar();
        $this->assertNotSame($oldUrl, $newUrl);
        Storage::disk('local')->assertMissing($oldPath);
        $this->get($oldUrl)->assertNotFound();
        $this->get($newUrl)->assertOk();
    }

    public function test_deleting_avatar_removes_file_and_revokes_its_url(): void
    {
        Storage::fake('local');
        $user = $this->actingAsUser();
        $url = $this->uploadAvatar();
        $path = $user->fresh()->avatar_path;

        $this->deleteJson('/api/v1/me/avatar')->assertNoContent();
        Storage::disk('local')->assertMissing($path);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.avatarUrl', null);
        $this->get($url)->assertNotFound();
    }

    public function test_removed_account_and_missing_files_do_not_expose_an_image(): void
    {
        Storage::fake('local');
        $user = $this->actingAsUser();
        $url = $this->uploadAvatar();
        Storage::disk('local')->delete($user->fresh()->avatar_path);
        $this->get($url)->assertNotFound();

        $url = $this->uploadAvatar();
        $user->delete();
        $this->get($url)->assertNotFound();
    }

    private function uploadAvatar(): string
    {
        return $this->post('/api/v1/me/avatar', [
            'avatar' => UploadedFile::fake()->image('portrait.jpg', 120, 120),
        ], ['Accept' => 'application/json'])->assertOk()->json('data.avatarUrl');
    }
}
