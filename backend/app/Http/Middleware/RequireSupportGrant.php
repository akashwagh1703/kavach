<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Platform\Models\SupportAccessGrant;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSupportGrant
{
    public function handle(Request $request, Closure $next): Response
    {
        $grantId = (string) $request->header('X-Support-Grant');
        if ($grantId === '') {
            abort(403, __('tenancy.support_denied'));
        }

        $grant = SupportAccessGrant::query()
            ->where('id', $grantId)
            ->where('platform_user_id', $request->user()->id)
            ->first();

        $tenantId = (string) $request->route('tenant');
        if (! $grant || ! $grant->isOpen() || $grant->tenant_id !== $tenantId) {
            abort(403, __('tenancy.support_denied'));
        }

        $context = app(TenantContext::class);
        $context->activate($grant->tenant_id, $context->membership, $grant);
        app(AuditLogger::class)->log('support.access', $grant, ['purpose' => $grant->purpose], $grant->tenant_id);

        return $next($request);
    }
}
