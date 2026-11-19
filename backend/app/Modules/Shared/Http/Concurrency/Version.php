<?php

namespace App\Modules\Shared\Http\Concurrency;

use App\Modules\Shared\Http\Problems\ApiProblemException;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Optimistic concurrency for PATCH (docs/blueprint/12 §12.1).
 *
 * A resource's `version` is its `updated_at` in epoch milliseconds, so no
 * extra column is needed. Clients send it back in `If-Match`. Without the
 * header the update goes ahead (last write wins); with a stale value it is
 * a 409 `version_conflict`.
 *
 * Call assertMatches() on a row loaded with lockForUpdate() inside the
 * update's transaction, so two concurrent writers cannot both pass.
 */
final class Version
{
    public const HEADER = 'If-Match';

    public static function of(Model $model): string
    {
        $updatedAt = $model->getAttribute($model->getUpdatedAtColumn() ?? 'updated_at');

        return $updatedAt instanceof DateTimeInterface ? $updatedAt->format('Uv') : '0';
    }

    public static function assertMatches(Request $request, Model $model): void
    {
        $header = $request->headers->get(self::HEADER);

        if ($header === null || trim($header) === '' || trim($header) === '*') {
            return;
        }

        // Accept the bare value, a quoted ETag, or a weak ETag (W/"…").
        $sent = trim((string) preg_replace('/^W\//', '', trim($header)), '"');

        if (! hash_equals(self::of($model), $sent)) {
            throw new ApiProblemException(409, 'version_conflict', meta: ['current_version' => self::of($model)]);
        }
    }
}
