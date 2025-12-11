<?php

namespace App\Services;

use App\Models\SecretKey;

class SecretManager
{
    public static function get(string $name, string $environment = 'production')
    {
        return SecretKey::active()
            ->where('name', $name)
            ->where('environment', $environment)
            ->first()?->value;
    }

    public static function set(string $name, string $value, string $environment = 'production', string $description = null)
    {
        SecretKey::updateOrCreate(
            ['name' => $name, 'environment' => $environment],
            [
                'value' => $value,
                'description' => $description,
                'is_active' => true,
            ]
        );
    }
}