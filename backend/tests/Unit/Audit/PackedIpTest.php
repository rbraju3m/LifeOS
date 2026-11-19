<?php

use App\Modules\Audit\Casts\PackedIp;
use App\Modules\Audit\Models\AuditLog;

it('round-trips IPv4 and IPv6 addresses through 4/16 bytes', function (string $ip, int $bytes) {
    $cast = new PackedIp;
    $model = new AuditLog;

    $packed = $cast->set($model, 'ip', $ip, []);

    expect(strlen((string) $packed))->toBe($bytes)
        ->and($cast->get($model, 'ip', $packed, []))->toBe($ip);
})->with([
    ['203.0.113.7', 4],
    ['2001:db8::1', 16],
]);

it('stores unparseable or empty values as null', function (mixed $value) {
    expect((new PackedIp)->set(new AuditLog, 'ip', $value, []))->toBeNull();
})->with(['not an ip', '', null]);
