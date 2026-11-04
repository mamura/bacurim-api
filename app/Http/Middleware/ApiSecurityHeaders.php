<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponseHeaders;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApiSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        foreach (ApiResponseHeaders::forRequest($request) as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
