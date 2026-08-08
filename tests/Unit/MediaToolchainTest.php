<?php

namespace Tests\Unit;

use App\Services\Media\MediaToolchain;
use Tests\TestCase;

class MediaToolchainTest extends TestCase
{
    public function test_it_falls_back_to_path_when_the_configured_absolute_path_is_stale(): void
    {
        $directory = sys_get_temp_dir().'/automind-media-tools-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        $executable = $directory.'/ffprobe';
        file_put_contents($executable, "#!/bin/sh\nexit 0\n");
        chmod($executable, 0700);
        $originalPath = getenv('PATH');

        try {
            putenv('PATH='.$directory);
            config(['automind.media.ffprobe_path' => '/usr/bin/ffprobe']);

            $this->assertSame($executable, app(MediaToolchain::class)->ffprobe());
        } finally {
            putenv('PATH='.($originalPath === false ? '' : $originalPath));
            @unlink($executable);
            @rmdir($directory);
        }
    }
}
