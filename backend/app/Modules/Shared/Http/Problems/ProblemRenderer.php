<?php

namespace App\Modules\Shared\Http\Problems;

use App\Modules\Shared\Http\Middleware\AssignRequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders API errors as RFC 9457 problem+json (docs/blueprint/12-api-specification.md §12.1).
 *
 * Exception messages are never copied into the response (they can contain
 * model names, SQL or file paths); only ValidationException field messages
 * and ApiProblemException details, which are written for users, are shown.
 */
class ProblemRenderer
{
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        413 => 'payload_too_large',
        415 => 'unsupported_media_type',
        419 => 'csrf_mismatch',
        422 => 'validation_failed',
        423 => 'account_locked',
        429 => 'rate_limited',
        500 => 'server_error',
        503 => 'service_unavailable',
    ];

    public function render(Throwable $e, Request $request): ?JsonResponse
    {
        if ($e instanceof HttpResponseException) {
            return null; // already carries an explicit response
        }

        $headers = [];
        $extra = [];
        $detail = null;

        if ($e instanceof ApiProblemException) {
            $status = $e->status;
            $code = $e->errorCode;
            $detail = $e->detail;
            if ($e->meta !== []) {
                $extra['meta'] = $e->meta;
            }
        } elseif ($e instanceof ValidationException) {
            $status = $e->status;
            $code = 'validation_failed';
            $extra['errors'] = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $status = 401;
            $code = 'unauthenticated';
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $code = self::CODES[$status] ?? 'http_error';
            $headers = $e->getHeaders();
        } else {
            $status = 500;
            $code = 'server_error';
            if (config('app.debug') === true) {
                $extra['debug'] = ['exception' => $e::class, 'message' => $e->getMessage()];
            }
        }

        return self::response($status, $code, $detail, $extra, $request, $headers);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string|list<string>>  $headers
     */
    public static function response(
        int $status,
        string $code,
        ?string $detail = null,
        array $extra = [],
        ?Request $request = null,
        array $headers = [],
    ): JsonResponse {
        $appUrl = config('app.url');
        $body = [
            'type' => rtrim(is_string($appUrl) ? $appUrl : '', '/').'/errors/'.$code,
            'title' => self::title($code),
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            ...$extra,
            'request_id' => $request !== null ? AssignRequestId::idFor($request) : null,
        ];

        return new JsonResponse($body, $status, [
            ...$headers,
            'Content-Type' => 'application/problem+json',
        ]);
    }

    private static function title(string $code): string
    {
        $key = 'errors.'.$code;
        $title = __($key);

        return is_string($title) && $title !== $key ? $title : (string) __('errors.http_error');
    }
}
