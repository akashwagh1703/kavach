<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Models\Membership;
use App\Domains\Platform\Services\EntitlementService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstablishContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $membershipId = $request->session()->get('kavach.membership_id');
        $portal = $request->session()->get('kavach.portal');

        if (! $user || ! $membershipId || ! $portal) {
            abort(401, __('auth.session_expired'));
        }

        $membership = Membership::query()
            ->with(['roles.permissions', 'tenant'])
            ->where('id', $membershipId)
            ->where('user_id', $user->id)
            ->where('portal', $portal)
            ->where('status', 'active')
            ->first();

        if (! $membership) {
            abort(401, __('auth.session_expired'));
        }

        if ($membership->tenant && $membership->tenant->status === 'suspended') {
            abort(403, __('tenancy.suspended'));
        }

        $context = app(TenantContext::class);
        if ($membership->tenant_id) {
            $context->activate($membership->tenant_id, $membership);
        } else {
            $context->usePlatform($membership);
        }
        if ($membership->portal === 'client' && $membership->tenant?->status === 'active') {
            if (! app(EntitlementService::class)->allows('module.client_portal')) {
                abort(403, __('tenancy.feature_unavailable'));
            }
        }
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
