<?php

namespace App\Http\Responses;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ProblemDetails
{
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api', 'api/*')) {
            return null;
        }

        $status = match (true) {
            $exception instanceof ApplicationFailure => match ($exception->kind) {
                FailureKind::Validation => 422,
                FailureKind::Conflict => 409,
                FailureKind::NotFound => 404,
                FailureKind::Forbidden => 403,
            },
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            $exception instanceof AuthenticationException => 401,
            $exception instanceof ValidationException => $exception->status,
            $exception instanceof HttpResponseException => $exception->getResponse()->getStatusCode(),
            default => 500,
        };

        if ($status < 400) {
            return null;
        }

        $sourceHeaders = match (true) {
            $exception instanceof HttpExceptionInterface => $exception->getHeaders(),
            $exception instanceof HttpResponseException => $exception->getResponse()->headers->all(),
            default => [],
        };
        $headers = [];
        foreach ($sourceHeaders as $name => $value) {
            if (in_array(strtolower($name), ['allow', 'retry-after', 'www-authenticate'], true)) {
                $headers[$name] = $value;
            }
        }

        $problem = [
            'type' => 'about:blank',
            'title' => Response::$statusTexts[$status] ?? 'HTTP Error',
            'status' => $status,
            'detail' => match ($status) {
                401 => 'Authentication is required.',
                403 => 'This operation is not permitted.',
                404 => 'The requested resource was not found.',
                405 => 'The HTTP method is not allowed for this resource.',
                422 => 'The submitted data is invalid.',
                429 => 'Too many requests. Please try again later.',
                default => $status >= 500 ? 'An unexpected error occurred.' : 'The request could not be completed.',
            },
            'instance' => $request->getBaseUrl().$request->getPathInfo(),
        ];

        if ($exception instanceof ValidationException) {
            $problem['type'] = 'urn:bacurim:problem:validation_failed';
            $problem['code'] = 'validation_failed';
            $problem['errors'] = $exception->errors();
        }

        if ($exception instanceof ApplicationFailure) {
            $problem['type'] = 'urn:bacurim:problem:'.$exception->errorCode;
            $problem['code'] = $exception->errorCode;
            $problem['detail'] = $exception->publicMessage;
        }

        return new JsonResponse($problem, $status, [...$headers, 'Content-Type' => 'application/problem+json']);
    }
}
