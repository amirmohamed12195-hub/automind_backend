<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;

final class ComposerScripts
{
    /**
     * Discover Laravel packages without spawning an external PHP process.
     *
     * Some managed deployment environments disable proc_open. Composer's
     * conventional "@php artisan package:discover" hook cannot run there,
     * even though Laravel itself works normally. Calling the console kernel
     * in-process preserves package discovery without requiring proc_open.
     */
    public static function discoverPackages(): void
    {
        $basePath = dirname(__DIR__, 2);
        $manifest = new PackageManifest(
            new Filesystem,
            $basePath,
            $basePath.'/bootstrap/cache/packages.php',
        );

        $manifest->build();
        fwrite(STDOUT, "Laravel package manifest generated without a child process.\n");
    }
}
