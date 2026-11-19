<?php

namespace Tests\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

/**
 * Stands in for api.pwnedpasswords.com in tests. Nothing is breached unless a
 * test calls FakePwnedPasswords::breach('the password').
 */
final class FakePwnedPasswords
{
    /** @var list<string> */
    private static array $breached = [];

    public static function install(): void
    {
        self::$breached = [];

        Http::fake(['api.pwnedpasswords.com/*' => fn (): PromiseInterface => Http::response(
            implode("\r\n", array_map(fn (string $p): string => strtoupper(substr(sha1($p), 5)).':42', self::$breached)),
        )]);
    }

    public static function breach(string $password): void
    {
        self::$breached[] = $password;
    }
}
