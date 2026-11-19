<?php

use Illuminate\Support\Facades\Http;
use Tests\Support\FakePwnedPasswords;
use Tests\TestCase;

/*
| Feature tests boot the Laravel application; unit tests stay framework-free.
|
| Every feature request is sent like the SPA would send it (an Origin from
| SANCTUM_STATEFUL_DOMAINS, so Sanctum starts a session), and no test may
| reach the network. The breached-password API answers "not breached" unless
| a test calls FakePwnedPasswords::breach().
*/
pest()->extend(TestCase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();
        FakePwnedPasswords::install();
        $this->withHeader('Origin', (string) config('app.url'));
    })
    ->in('Feature');
