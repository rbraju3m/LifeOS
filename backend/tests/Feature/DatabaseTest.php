<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('runs the test suite on the MySQL test database', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('lifeos_test');
});

it('uses UTC and utf8mb4 with the 0900 collation', function () {
    $row = DB::selectOne('select @@session.time_zone as tz, @@character_set_database as cs, @@collation_database as co');

    expect($row->tz)->toBe('+00:00')
        ->and($row->cs)->toBe('utf8mb4')
        ->and($row->co)->toBe('utf8mb4_0900_ai_ci');
});

it('creates tables as InnoDB with the 0900 collation', function () {
    $table = DB::selectOne('select engine, table_collation from information_schema.tables where table_schema = database() and table_name = ?', ['users']);

    expect($table->ENGINE ?? $table->engine)->toBe('InnoDB')
        ->and($table->TABLE_COLLATION ?? $table->table_collation)->toBe('utf8mb4_0900_ai_ci');
});
