<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureActiveTenant;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsurePrivilegedMfa;
use App\Http\Middleware\EstablishContext;
use App\Http\Middleware\RequireSupportGrant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\Tenancy\TenantAccessDenied;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->append(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->api(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'context' => EstablishContext::class,
            'tenant.active' => EnsureActiveTenant::class,
            'feature' => EnsureFeature::class,
            'mfa' => EnsurePrivilegedMfa::class,
            'support.grant' => RequireSupportGrant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $json = function (Request $request, string $message, int $status, array $errors = []) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $message,
                'errors' => (object) $errors,
                'request_id' => $request->attributes->get('request_id'),
            ], $status);
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($json) {
            return $json($request, $e->getMessage(), $e->status, $e->errors());
        });
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($json) {
            return $json($request, __('auth.session_expired'), 401);
        });
        $exceptions->render(function (AuthorizationException $e, Request $request) use ($json) {
            return $json($request, $e->getMessage() ?: __('auth.forbidden'), 403);
        });
        $exceptions->render(function (ModelNotFoundException|TenantAccessDenied $e, Request $request) use ($json) {
            return $json($request, __('records.not_found'), 404);
        });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($json) {
            return $json($request, $e->getMessage() ?: 'Request could not be completed.', $e->getStatusCode());
        });
        $exceptions->render(function (Throwable $e, Request $request) use ($json) {
            if (! $request->is('api/*') || config('app.debug')) {
                return null;
            }

            report($e);

            return $json($request, __('errors.unexpected'), 500);
        });
    })->create();
