<?php

namespace Tests\Unit;

use App\Models\WebWidget;
use Tests\TestCase;

class WebWidgetTest extends TestCase
{
    public function test_domains_are_normalized_to_bare_hosts(): void
    {
        $this->assertSame('www.example.com', WebWidget::normalizeDomain(' https://www.Example.com/path?x=1 '));
        $this->assertSame('shop.example.co', WebWidget::normalizeDomain('shop.example.co:8443'));
        $this->assertNull(WebWidget::normalizeDomain('localhost'));
        $this->assertNull(WebWidget::normalizeDomain('not a domain'));
        $this->assertNull(WebWidget::normalizeDomain(''));
    }

    public function test_only_allowed_hosts_with_or_without_www_may_use_the_widget(): void
    {
        $widget = new WebWidget(['allowed_domains' => ['example.com']]);

        $this->assertTrue($widget->allowsOrigin('https://example.com'));
        $this->assertTrue($widget->allowsOrigin('https://www.example.com'));
        $this->assertFalse($widget->allowsOrigin('https://evil-example.com'));
        $this->assertFalse($widget->allowsOrigin('https://example.com.evil.net'));
        $this->assertFalse($widget->allowsOrigin('https://shop.example.com'));
        $this->assertFalse($widget->allowsOrigin(null));
        $this->assertFalse($widget->allowsOrigin('null'));
    }

    public function test_settings_fall_back_to_defaults_and_ignore_unknown_keys(): void
    {
        $widget = new WebWidget(['settings' => ['accent' => '#112233', 'injected' => 'x']]);
        $settings = $widget->settingsWithDefaults();

        $this->assertSame('#112233', $settings['accent']);
        $this->assertSame('right', $settings['position']);
        $this->assertArrayNotHasKey('injected', $settings);
    }

    public function test_public_keys_are_long_and_unique(): void
    {
        $first = WebWidget::generatePublicKey();

        $this->assertStringStartsWith('wgt_', $first);
        $this->assertSame(44, strlen($first));
        $this->assertNotSame($first, WebWidget::generatePublicKey());
    }
}
