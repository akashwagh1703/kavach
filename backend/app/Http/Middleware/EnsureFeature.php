<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Platform\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! app(EntitlementService::class)->allows($feature)) {
            abort(403, __('tenancy.feature_unavailable'));
        }

        return $next($request);
    }
}
