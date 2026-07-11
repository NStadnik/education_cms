<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;

final class LcloudConfig
{
    public static function get(): array
    {
        $defaults = (array) (Container::get('config')['app']['lcloud'] ?? []);
        $rows = Container::get('db')->fetchAll("select name,value from settings where name in ('lcloud_enabled','lcloud_issuer','lcloud_audience','lcloud_sso_secret','lcloud_api_key','lcloud_allowed_origin')");
        $stored = array_column($rows, 'value', 'name');

        return [
            'enabled' => array_key_exists('lcloud_enabled', $stored)
                ? $stored['lcloud_enabled'] === '1'
                : (bool) ($defaults['enabled'] ?? false),
            'issuer' => (string) ($stored['lcloud_issuer'] ?? $defaults['issuer'] ?? 'lcloud'),
            'audience' => (string) ($stored['lcloud_audience'] ?? $defaults['audience'] ?? 'education-cms'),
            'sso_secret' => (string) ($stored['lcloud_sso_secret'] ?? $defaults['sso_secret'] ?? ''),
            'api_key' => (string) ($stored['lcloud_api_key'] ?? $defaults['api_key'] ?? ''),
            'allowed_origin' => (string) ($stored['lcloud_allowed_origin'] ?? $defaults['allowed_origin'] ?? ''),
        ];
    }
}
