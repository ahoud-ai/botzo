<?php

namespace App\Resolvers;

use App\Contracts\PaymentGatewayContract;
use App\Support\SupportedPaymentProcessors;

class PaymentPlatformResolver
{
    public function resolveService($paymentPlatform)
    {
        $name = SupportedPaymentProcessors::normalizeName((string) $paymentPlatform);

        if (!SupportedPaymentProcessors::isSupported($name)) {
            throw new \InvalidArgumentException(__('The selected payment processor is not supported.'));
        }

        $service = config("services.{$name}.class");
        
        if ($service) {
            $resolved = resolve($service);

            if (!$resolved instanceof PaymentGatewayContract) {
                throw new \RuntimeException(__('The selected payment processor does not implement the expected gateway contract.'));
            }

            return $resolved;
        }

        throw new \Exception(__('The selected platform is not in the configuration file'));
    }
}
