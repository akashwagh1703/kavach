<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivilegedMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->attributes->get('membership');
        $user = $request->user();
        if (! $membership || ! $user || $user->mfa_enabled) {
            return $next($request);
        }

        $privileged = $membership->portal === 'platform'
            || $membership->roles->pluck('code')->intersect(['organization_owner', 'tenant_admin'])->isNotEmpty();

        if (! $privileged) {
            return $next($request);
        }

        $path = $request->path();
        $allowed = str_contains($path, 'auth/mfa')
            || str_contains($path, 'auth/logout')
            || str_contains($path, 'auth/me')
            || str_contains($path, 'onboarding');

        if ($allowed) {
            return $next($request);
        }

        // Local development can open operational routes before MFA is enrolled.
        // Production and staging still require enrollment for privileged roles.
        if (app()->environment('local') && ! filter_var(env('KAVACH_REQUIRE_MFA', false), FILTER_VALIDATE_BOOL)) {
            return $next($request);
        }

        return response()->json([
            'message' => __('auth.mfa_enrollment_required'),
            'errors' => ['mfa' => [__('auth.mfa_enrollment_required')]],
            'code' => 'mfa_enrollment_required',
            'request_id' => $request->attributes->get('request_id'),
        ], 403);
    }
}
