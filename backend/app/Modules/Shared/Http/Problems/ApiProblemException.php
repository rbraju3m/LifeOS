<?php

namespace App\Modules\Shared\Http\Problems;

use RuntimeException;

/**
 * Throw from domain/actions code to return a specific API error, e.g.
 * new ApiProblemException(403, 'entitlement_exceeded', meta: ['feature' => 'records.max_count']).
 *
 * The detail is shown to the user, so it must never contain internal data.
 */
class ApiProblemException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        public readonly ?string $detail = null,
        public readonly array $meta = [],
    ) {
        parent::__construct($detail ?? $errorCode);
    }
}
