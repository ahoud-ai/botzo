<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class InertiaAssetVersionTest extends TestCase
{
    public function test_asset_version_tracks_vite_manifest_even_when_asset_url_is_configured(): void
    {
        $originalPublicPath = public_path();
        $testPublicPath = storage_path('framework/testing-public/'.uniqid('inertia-version-', true));
        $manifestPath = $testPublicPath.'/build/manifest.json';

        app()->usePublicPath($testPublicPath);
        config(['app.asset_url' => 'https://cdn.botzo.test']);

        try {
            File::ensureDirectoryExists(dirname($manifestPath));
            File::put($manifestPath, json_encode([
                'resources/js/app.js' => ['file' => 'assets/app-old.js'],
            ], JSON_THROW_ON_ERROR));
            clearstatcache(true, $manifestPath);

            $middleware = app(HandleInertiaRequests::class);
            $firstVersion = $middleware->version(Request::create('/automation/flows/test', 'GET'));

            File::put($manifestPath, json_encode([
                'resources/js/app.js' => ['file' => 'assets/app-new.js'],
            ], JSON_THROW_ON_ERROR));
            clearstatcache(true, $manifestPath);

            $secondVersion = $middleware->version(Request::create('/automation/flows/test', 'GET'));

            $this->assertNotNull($firstVersion);
            $this->assertNotSame($firstVersion, $secondVersion);
        } finally {
            app()->usePublicPath($originalPublicPath);
            File::deleteDirectory($testPublicPath);
        }
    }
}
