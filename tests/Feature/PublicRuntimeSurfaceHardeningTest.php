<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicRuntimeSurfaceHardeningTest extends TestCase
{
    public function test_setup_entrypoints_are_not_registered(): void
    {
        $retiredSetupName = 'in'.'stall';

        $this->assertNull(app('router')->getRoutes()->getByName($retiredSetupName));
        $this->assertNull(app('router')->getRoutes()->getByName($retiredSetupName.'.update'));

        $this->get('/'.$retiredSetupName.'/server')->assertNotFound();
        $this->get('/update')->assertNotFound();
    }

    public function test_removed_previous_public_routes_return_not_found(): void
    {
        $this->get('/process-campaign')->assertNotFound();
        $this->post('/process-campaign')->assertNotFound();
        $this->get('/migrate-upgrade')->assertNotFound();
    }
}
