<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Concerns;

use App\Domains\Identity\Models\Membership;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AccessService;
use App\Domains\Platform\Models\Subscription;
use App\Support\Api\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait Responds
{
    protected function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->input('per_page', 20)));
    }

    protected function search(Request $request): ?string
    {
        $value = trim((string) $request->input('search', ''));

        return $value === '' ? null : addcslashes($value, '%_\\');
    }

    protected function page(LengthAwarePaginator $paginator, callable $map): JsonResponse
    {
        return ApiResponse::paginated($paginator, fn ($collection) => $collection->map($map)->values());
    }

    protected function permit(string $permission): void
    {
        app(AccessService::class)->assert($permission);
    }

    protected function permitAny(array $permissions): void
    {
        $access = app(AccessService::class);
        foreach ($permissions as $permission) {
            if ($access->allows($permission)) {
                return;
            }
        }
        $access->assert($permissions[0]);
    }

    protected function sessionPayload(User $user, Membership $membership): array
    {
        $membership->loadMissing('roles.permissions', 'tenant');
        $permissions = $membership->roles
            ->flatMap(fn ($role) => $role->permissions->pluck('code'))
            ->unique()
            ->values();
        $tenant = $membership->tenant;
        $subscription = $tenant ? Subscription::query()->with('plan.features')->where('tenant_id', $tenant->id)->first() : null;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'mfa_enabled' => $user->mfa_enabled,
                'email_verified_at' => $user->email_verified_at,
            ],
            'membership' => [
                'id' => $membership->id,
                'portal' => $membership->portal,
                'tenant_id' => $membership->tenant_id,
                'employee_id' => $membership->employee_id,
                'client_id' => $membership->client_id,
                'roles' => $membership->roles->pluck('code')->values(),
                'permissions' => $permissions,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'business_type' => $tenant->business_type,
                'status' => $tenant->status,
                'onboarding_step' => $tenant->onboarding_step,
                'onboarding_completed_at' => $tenant->onboarding_completed_at,
                'timezone' => $tenant->timezone,
                'settings' => $tenant->settings,
                'client_workflow_enabled' => $tenant->clientWorkflowEnabled(),
            ] : null,
            'features' => $subscription ? $subscription->plan->features->pluck('code')->values() : [],
            'subscription' => $subscription ? [
                'status' => $subscription->status,
                'plan' => $subscription->plan->code,
                'trial_ends_at' => $subscription->trial_ends_at,
            ] : null,
        ];
    }
}
