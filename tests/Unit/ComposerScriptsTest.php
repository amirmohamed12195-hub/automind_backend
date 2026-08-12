<?php

namespace Tests\Unit;

use App\Support\ComposerScripts;
use PHPUnit\Framework\TestCase;

class ComposerScriptsTest extends TestCase
{
    public function test_package_manifest_is_generated_without_an_external_process(): void
    {
        ComposerScripts::discoverPackages();

        $manifestPath = dirname(__DIR__, 2).'/bootstrap/cache/packages.php';

        $this->assertFileExists($manifestPath);

        $manifest = require $manifestPath;

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('laravel/sanctum', $manifest);
    }
}
