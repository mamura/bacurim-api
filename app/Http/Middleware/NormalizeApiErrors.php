<?php

namespace App\Http\Middleware;

use App\Http\Responses\ProblemDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class NormalizeApiErrors
{
    public function __construct(private ProblemDetails $problems) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api', 'api/*') && $response->getStatusCode() >= 400
            && ! str_starts_with((string) $response->headers->get('Content-Type'), 'application/problem+json')) {
            return $this->problems->render(new HttpException(
                $response->getStatusCode(), headers: $response->headers->all(),
            ), $request);
        }

        return $response;
    }
}
