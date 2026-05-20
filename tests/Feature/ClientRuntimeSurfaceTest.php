<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClientRuntimeSurfaceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_retired_extension_endpoints_are_not_registered(): void
    {
        $routes = collect(app('router')->getRoutes());

        foreach (['admin/'.'addons/update', 'admin/'.'addons/'.'in'.'stall'] as $retiredRoute) {
            $this->assertFalse($routes->contains(
                fn ($route): bool => in_array('POST', $route->methods(), true)
                    && $route->uri() === $retiredRoute
            ));
        }
    }

    public function test_retired_admin_extension_files_are_absent(): void
    {
        $this->assertFileDoesNotExist(resource_path(implode(DIRECTORY_SEPARATOR, ['js', 'Pages', 'Admin', 'Add'.'ons', 'Index.vue'])));
        $this->assertFileDoesNotExist(resource_path(implode(DIRECTORY_SEPARATOR, ['js', 'Pages', 'Admin', 'Add'.'ons', 'Show.vue'])));
        $this->assertFileDoesNotExist(resource_path('js/Components/Tables/AddonTable.vue'));

        $adminMenu = file_get_contents(resource_path('js/Pages/Admin/Layout/Menu.vue'));
        $this->assertIsString($adminMenu);
        $this->assertStringNotContainsString('/admin/'.'addons', $adminMenu);
        $this->assertStringNotContainsString('Add-ons Marketplace', $adminMenu);
    }
}
