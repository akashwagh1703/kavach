<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app(TenantContext::class)->membership?->tenant;
        if (! $tenant || $tenant->status !== 'active') {
            abort(403, __('tenancy.not_active'));
        }

        return $next($request);
    }
}
