<?php

namespace App\Modules\Shared\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * ULID columns as CHAR(26) ascii/ascii_bin (docs/adr/0005-ulid-primary-keys.md).
 *
 * Always create ULID primary and foreign key columns through this helper:
 * MySQL requires a foreign key column to have the same charset and collation
 * as the column it references.
 */
final class Ulid
{
    public static function primary(Blueprint $table, string $column = 'id'): ColumnDefinition
    {
        return self::column($table, $column)->primary();
    }

    public static function column(Blueprint $table, string $column): ColumnDefinition
    {
        return $table->char($column, 26)->charset('ascii')->collation('ascii_bin');
    }

    /**
     * A ULID foreign key column with its constraint. Call ->nullable() on the
     * result for optional references (use $onDelete = 'set null' then).
     */
    public static function foreign(
        Blueprint $table,
        string $column,
        string $on,
        string $onDelete = 'cascade',
        bool $nullable = false,
    ): ColumnDefinition {
        $definition = self::column($table, $column)->nullable($nullable);
        $table->foreign($column)->references('id')->on($on)->onDelete($onDelete);

        return $definition;
    }
}
