<?php

namespace App\Modules\Shared\Http\Pagination;

use Illuminate\Http\Request;

/**
 * Page size for cursor-paginated lists: default 25, max 100
 * (docs/blueprint/12-api-specification.md §12.1). List endpoints call
 * $query->cursorPaginate(PerPage::fromRequest($request)) and return a
 * resource collection, which produces data / links.next / meta.next_cursor.
 */
final class PerPage
{
    public const DEFAULT = 25;

    public const MAX = 100;

    public static function fromRequest(Request $request): int
    {
        $value = $request->query('per_page');

        if (! is_string($value) || preg_match('/^\d{1,4}$/', $value) !== 1) {
            return self::DEFAULT;
        }

        return max(1, min(self::MAX, (int) $value));
    }
}
