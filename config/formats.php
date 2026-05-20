<?php

$timezones = [
    ['value' => 'Asia/Riyadh', 'label' => 'Asia/Riyadh'],
];

$countries = [
    ['value' => 'Saudi Arabia', 'label' => 'Saudi Arabia'],
];

$phoneCountries = [
    ['name' => 'Saudi Arabia', 'iso2' => 'SA', 'dialCode' => '966'],
];

$date_formats = [
    ['value' => 'Y-m-d', 'label' => date('Y-m-d')],
    ['value' => 'm/d/Y', 'label' => date('m/d/Y')],
    ['value' => 'd.m.Y', 'label' => date('d.m.Y')],
    ['value' => 'd-M-y', 'label' => date('d-M-y')],
    ['value' => 'F jS, Y', 'label' => date('F jS, Y')],
];

$time_formats = [
    ['value' => 'H:i', 'label' => date('H:i')],
    ['value' => 'h:i A', 'label' => date('h:i A')],
];

$placeholders = [
    ['value' => '{first_name}', 'label' => 'First name'],
    ['value' => '{last_name}', 'label' => 'Last name'],
    ['value' => '{full_name}', 'label' => 'Full name'],
    ['value' => '{email}', 'label' => 'Email'],
    ['value' => '{phone}', 'label' => 'Phone'],
    ['value' => '{group}', 'label' => 'Group'],
    ['value' => '{organization_name}', 'label' => 'Organization name'],
    ['value' => '{full_address}', 'label' => 'Address'],
    ['value' => '{street}', 'label' => 'Street'],
    ['value' => '{city}', 'label' => 'City'],
    ['value' => '{state}', 'label' => 'State'],
    ['value' => '{zip_code}', 'label' => 'Zip code'],
    ['value' => '{country}', 'label' => 'Country'],
    ['value' => '{url:first_name}', 'label' => 'First name (URL encoded)'],
    ['value' => '{url:last_name}', 'label' => 'Last name (URL encoded)'],
    ['value' => '{url:full_name}', 'label' => 'Full name (URL encoded)'],
    ['value' => '{url:email}', 'label' => 'Email (URL encoded)'],
    ['value' => '{url:phone}', 'label' => 'Phone (URL encoded)'],
    ['value' => '{url:group}', 'label' => 'Group (URL encoded)'],
    ['value' => '{url:organization_name}', 'label' => 'Organization name (URL encoded)'],
    ['value' => '{url:full_address}', 'label' => 'Address (URL encoded)'],
    ['value' => '{url:street}', 'label' => 'Street (URL encoded)'],
    ['value' => '{url:city}', 'label' => 'City (URL encoded)'],
    ['value' => '{url:state}', 'label' => 'State (URL encoded)'],
    ['value' => '{url:zip_code}', 'label' => 'Zip code (URL encoded)'],
    ['value' => '{url:country}', 'label' => 'Country (URL encoded)'],
];

return [
    'timezones' => $timezones,
    'countries' => $countries,
    'phone_countries' => $phoneCountries,
    'allowed_phone_country_codes' => array_map(fn ($country) => $country['iso2'], $phoneCountries),
    'date_formats' => $date_formats,
    'time_formats' => $time_formats,
    'placeholders' => $placeholders,
];
