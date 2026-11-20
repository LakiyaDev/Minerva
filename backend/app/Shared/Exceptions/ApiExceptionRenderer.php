<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Shared\Contracts\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?Response
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null;
        }

        $requestId = (string) ($request->attributes->get('request_id') ?? Str::ulid());

        if ($e instanceof ApiError) {
            return self::error(
                status: $e->status(),
                message: $e->message(),
                code: $e->errorCode(),
                errors: $e->errors(),
                requestId: $requestId,
                headers: $e->headers(),
            );
        }

        if ($e instanceof ValidationException) {
            return self::error(
                status: 422,
                message: 'The submitted data is invalid.',
                code: 'validation_failed',
                errors: $e->errors(),
                requestId: $requestId,
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::error(401, 'Unauthenticated.', 'unauthenticated', requestId: $requestId);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
            return self::error(403, 'This action is unauthorized.', 'forbidden', requestId: $requestId);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return self::error(404, 'Resource not found.', 'not_found', requestId: $requestId);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return self::error(405, 'Method not allowed.', 'method_not_allowed', requestId: $requestId);
        }

        if ($e instanceof ThrottleRequestsException) {
            $retryAfter = (string) ($e->getHeaders()['Retry-After'] ?? '60');

            return self::error(
                status: 429,
                message: 'Too many requests. Please retry later.',
                code: 'rate_limited',
                requestId: $requestId,
                headers: ['Retry-After' => $retryAfter],
            );
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::error(
                status: $e->getStatusCode(),
                message: $e->getMessage() !== '' ? $e->getMessage() : (Response::$statusTexts[$e->getStatusCode()] ?? 'Request failed.'),
                code: 'http_error',
                requestId: $requestId,
                headers: $e->getHeaders(),
            );
        }

        Log::error('Unhandled API exception', [
            'request_id' => $requestId,
            'exception' => $e,
        ]);

        $message = app()->hasDebugModeEnabled() ? $e->getMessage() : 'Server error.';

        return self::error(500, $message, 'server_error', requestId: $requestId);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, string>  $headers
     */
    private static function error(
        int $status,
        string $message,
        string $code,
        array $errors = [],
        string $requestId = '',
        array $headers = [],
    ): Response {
        $payload = [
            'message' => $message,
            'code' => $code,
            'request_id' => $requestId,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        // Exception rendering happens outside the middleware pipeline, so the
        // RequestId middleware cannot attach this header to error responses.
        // Set it here to keep tracing consistent for success and failure.
        $headers['X-Request-ID'] = $requestId;

        // Build the response directly instead of through the response() helper:
        // the helper resolves the response factory, which in turn resolves the
        // 'view' service. When the application failed during boot, that binding
        // does not exist and the error handler would fatal while reporting the
        // original error.
        return new JsonResponse($payload, $status, $headers);
    }
}
