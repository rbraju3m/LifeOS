<?php

use App\Modules\Shared\Http\Pagination\PerPage;
use Illuminate\Http\Request;

it('clamps the requested page size', function (?string $input, int $expected) {
    $request = Request::create('/', 'GET', $input === null ? [] : ['per_page' => $input]);

    expect(PerPage::fromRequest($request))->toBe($expected);
})->with([
    'missing' => [null, 25],
    'normal' => ['10', 10],
    'zero' => ['0', 1],
    'too big' => ['5000', 100],
    'not a number' => ['abc', 25],
    'negative' => ['-5', 25],
]);
