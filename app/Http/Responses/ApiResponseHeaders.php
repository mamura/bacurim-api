<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;

final class ApiResponseHeaders
{
    /** @return array<string,string> */
    public static function forRequest(Request $request): array
    {
        if (! $request->is('api', 'api/*', 'sanctum/csrf-cookie')) {
            return [];
        }
        $headers = ['X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'X-Frame-Options' => 'DENY'];
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return $headers;
    }
}
