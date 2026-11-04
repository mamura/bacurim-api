<?php

use App\Http\Middleware\EnsureValidJson;
use App\Http\Middleware\NormalizeApiErrors;
use App\Http\Responses\ProblemDetails;
use App\Modules\IdentityAccess\Interfaces\Http\Middleware\EnsureSessionValidity;
use App\Modules\IdentityAccess\Interfaces\Http\Middleware\GuardSpaOrigin;
use App\Modules\IdentityAccess\Interfaces\Http\Middleware\NoStoreAuthentication;
use App\Modules\IdentityAccess\Interfaces\Http\Middleware\ResolveTenant;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(NormalizeApiErrors::class);
        $middleware->statefulApi();
        $middleware->trimStrings(except: ['new_password']);
        $middleware->trustProxies(headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);
        $middleware->alias(['auth.session.valid' => EnsureSessionValidity::class, 'tenant.context' => ResolveTenant::class]);
        $middleware->append(NoStoreAuthentication::class);
        $middleware->append(GuardSpaOrigin::class);
        $middleware->api(prepend: [EnsureValidJson::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([ApplicationFailure::class]);
        $exceptions->dontFlash(['new_password']);
        $exceptions->render(fn (Throwable $exception, Request $request) => (new ProblemDetails)->render($exception, $request));
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $exception): bool {
            return $request->is('api', 'api/*') || $request->expectsJson();
        });
    })->create();
