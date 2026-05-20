<?php

namespace App\Helpers;
use Cache;

use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\Addon;
use App\Models\Setting;
use App\Services\AddonStateService;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use NumberFormatter;
use Symfony\Component\Mime\Part\HtmlPart;

class CustomHelper {
    public static function config($key){
        $config = Setting::where('key', $key)->first();

        if($config){
            return $config->value;
        } else {
            return NULL;
        }
    }

    public static function isModuleEnabled($module, $organizationId = NULL){
        $orgId = $organizationId != NULL ? $organizationId : session()->get('current_organization');
        return app(AddonStateService::class)->isModuleEnabledForOrganization($module, $orgId ? (int) $orgId : null);
    }

    private static function normalizeAddonFlag($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
