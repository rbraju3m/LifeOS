<?php

use App\Modules\Audit\AuditCategory;

it('treats auth.* actions as security events', function (string $action, bool $security) {
    expect(AuditCategory::isSecurity($action))->toBe($security);
})->with([
    ['auth.login', true],
    ['auth.two_factor_disabled', true],
    ['export.requested', false],
    ['record.secret_revealed', false],
    ['authx.login', false],
]);
