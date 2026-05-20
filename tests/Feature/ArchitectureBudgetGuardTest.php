<?php

namespace Tests\Feature;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ArchitectureBudgetGuardTest extends TestCase
{
    public function test_previous_hotspots_do_not_grow_beyond_their_current_caps(): void
    {
        /** @var array<string, int> $caps */
        $caps = config('architecture.budgets.previous_caps', []);

        foreach ($caps as $relativePath => $lineCap) {
            $absolutePath = base_path($relativePath);

            $this->assertFileExists($absolutePath, "Missing ratcheted file: {$relativePath}");
            $this->assertLessThanOrEqual(
                $lineCap,
                $this->lineCount($absolutePath),
                "{$relativePath} exceeded its ratcheted size cap of {$lineCap} lines."
            );
        }
    }

    public function test_modular_files_and_route_slices_respect_their_budgets(): void
    {
        $moduleBudgets = config('architecture.budgets.modules', []);
        $routeBudget = (int) config('architecture.budgets.route_file', 150);

        foreach ($this->filesUnder(base_path('app/Modules')) as $path) {
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
            $cap = $this->moduleBudgetForPath($relativePath, $moduleBudgets);

            $this->assertLessThanOrEqual(
                $cap,
                $this->lineCount($path),
                "{$relativePath} exceeded its module budget of {$cap} lines."
            );
        }

        foreach ($this->filesUnder(base_path('routes')) as $path) {
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            $this->assertLessThanOrEqual(
                $routeBudget,
                $this->lineCount($path),
                "{$relativePath} exceeded the route budget of {$routeBudget} lines."
            );
        }
    }

    public function test_built_asset_chunks_stay_within_their_ratchets_when_manifest_exists(): void
    {
        $manifestPath = public_path('build/manifest.json');

        if (! file_exists($manifestPath)) {
            $this->markTestSkipped('Build manifest is not present; run npm build before checking asset chunk budgets.');
        }

        /** @var array<string, int> $chunkCaps */
        $chunkCaps = config('architecture.budgets.build_chunks', []);
        $maxJsKilobytes = (int) config('architecture.budgets.build_assets.max_js_kb', 550);
        $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);

        foreach ($this->javascriptAssetsFromManifest($manifest) as $asset) {
            $assetPath = public_path('build/'.$asset);
            $this->assertFileExists($assetPath, "Missing build asset: {$asset}");

            $sizeKilobytes = filesize($assetPath) / 1024;

            $this->assertLessThanOrEqual(
                $maxJsKilobytes,
                $sizeKilobytes,
                sprintf(
                    '%s is %.2f kB and exceeded the max JS asset budget of %d kB.',
                    $asset,
                    $sizeKilobytes,
                    $maxJsKilobytes
                )
            );
        }

        foreach ($chunkCaps as $chunkName => $maxKilobytes) {
            $asset = $this->findChunkAsset($manifest, $chunkName);

            $this->assertNotNull($asset, "Missing build chunk matching '{$chunkName}'.");

            $assetPath = public_path('build/'.$asset);
            $this->assertFileExists($assetPath, "Missing build asset for '{$chunkName}': {$asset}");

            $sizeKilobytes = filesize($assetPath) / 1024;

            $this->assertLessThanOrEqual(
                $maxKilobytes,
                $sizeKilobytes,
                sprintf(
                    '%s is %.2f kB and exceeded its ratcheted budget of %d kB.',
                    $asset,
                    $sizeKilobytes,
                    $maxKilobytes
                )
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function filesUnder(string $root): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /**
     * @param  array<string, int>  $budgets
     */
    private function moduleBudgetForPath(string $relativePath, array $budgets): int
    {
        return match (true) {
            str_contains($relativePath, DIRECTORY_SEPARATOR.'Application'.DIRECTORY_SEPARATOR) => (int) ($budgets['application'] ?? 200),
            str_contains($relativePath, DIRECTORY_SEPARATOR.'Domain'.DIRECTORY_SEPARATOR) => (int) ($budgets['domain'] ?? 150),
            str_contains($relativePath, DIRECTORY_SEPARATOR.'Infrastructure'.DIRECTORY_SEPARATOR) => (int) ($budgets['infrastructure'] ?? 250),
            str_contains($relativePath, DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR) => (int) ($budgets['controller'] ?? 250),
            default => (int) ($budgets['support'] ?? 200),
        };
    }

    private function lineCount(string $absolutePath): int
    {
        $contents = file_get_contents($absolutePath);

        return count(preg_split('/\R/u', $contents ?: '') ?: []);
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function javascriptAssetsFromManifest(array $manifest): array
    {
        $assets = [];

        foreach ($manifest as $entry) {
            if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
                continue;
            }

            if (str_ends_with($entry['file'], '.js')) {
                $assets[] = $entry['file'];
            }
        }

        return array_values(array_unique($assets));
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function findChunkAsset(array $manifest, string $chunkName): ?string
    {
        foreach ($manifest as $entry) {
            if (! is_array($entry) || ! isset($entry['file']) || ! is_string($entry['file'])) {
                continue;
            }

            if (preg_match('/assets\/'.preg_quote($chunkName, '/').'-[^\/]+\.js$/', $entry['file']) === 1) {
                return $entry['file'];
            }
        }

        return null;
    }
}
