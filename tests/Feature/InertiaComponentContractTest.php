<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class InertiaComponentContractTest extends TestCase
{
    public function test_static_inertia_pages_resolve_to_frontend_components(): void
    {
        $missing = [];

        foreach ($this->phpSourceFiles() as $file) {
            $contents = file_get_contents($file->getPathname());

            preg_match_all('/Inertia::render\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$component, $offset]) {
                $expected = $this->componentPath($component);

                if ($expected !== null && ! file_exists($expected)) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $missing[] = sprintf('%s:%d renders %s but %s does not exist', $file->getPathname(), $line, $component, $expected);
                }
            }
        }

        $this->assertSame([], $missing, implode(PHP_EOL, $missing));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpSourceFiles(): iterable
    {
        foreach ([base_path('app'), base_path('modules')] as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    yield $file;
                }
            }
        }
    }

    private function componentPath(string $component): ?string
    {
        if (str_contains($component, '::')) {
            [$module, $moduleComponent] = explode('::', $component, 2);

            return base_path('modules/' . $module . '/Pages/' . $moduleComponent . '.vue');
        }

        return resource_path('js/Pages/' . $component . '.vue');
    }
}
