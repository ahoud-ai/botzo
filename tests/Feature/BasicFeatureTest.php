<?php

namespace Tests\Feature;

use Tests\TestCase;

class BasicFeatureTest extends TestCase
{
    public function test_sanity_check(): void
    {
        $this->assertSame(4, 2 + 2);
    }
}
