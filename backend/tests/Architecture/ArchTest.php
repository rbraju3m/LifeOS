<?php

/*
| Architecture rules from docs/blueprint/09 and 19. Module dependency rules
| live in deptrac.yaml; these cover code hygiene that Deptrac can't express.
| The "controllers never query unscoped" rule is added with the first
| module (slice 1.5), when the visibleTo scope exists to test against.
*/

arch('no debugging calls are left in the code')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('env() is only read inside config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('controllers do not use the query builder facade directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('only the Audit module touches the audit_logs model; others go through AuditLogger')
    ->expect('App\Modules\Audit\Models\AuditLog')
    ->toOnlyBeUsedIn('App\Modules\Audit');

arch('feature modules use Tags only through its contracts')
    ->expect('App\Modules\Tasks')
    ->not->toUse('App\Modules\Tags\Models');
