<?php

namespace App\Modules\Audit\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * IPv4/IPv6 address stored as VARBINARY(16) (inet_pton), read as text.
 * An unparseable value is stored as null rather than failing the write.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class PackedIp implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $ip = @inet_ntop($value);

        return $ip === false ? null : $ip;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $packed = @inet_pton($value);

        return $packed === false ? null : $packed;
    }
}
