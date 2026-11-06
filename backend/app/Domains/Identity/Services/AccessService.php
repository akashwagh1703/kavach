<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AccessService
{
    public function allows(string $permission): bool
    {
        $context = app(TenantContext::class);
        $membership = $context->membership;

        if ($membership && $membership->status === 'active' && $membership->hasPermission($permission)) {
            return true;
        }

        $grant = $context->supportGrant;
        if ($grant && $grant->isOpen() && in_array($permission, $grant->permissions ?? [], true)) {
            return true;
        }

        return false;
    }

    public function assert(string $permission): void
    {
        if (! $this->allows($permission)) {
            throw new AuthorizationException('You do not have permission to perform this action.');
        }
    }

    /**
     * Null means every site in the active tenant. An empty list means none.
     *
     * @return list<string>|null
     */
    public function siteIds(): ?array
    {
        $context = app(TenantContext::class);
        $membership = $context->membership;
        if (! $membership) {
            return [];
        }

        if ($context->supportGrant) {
            return null;
        }

        if ($membership->portal === 'client') {
            return DB::table('client_site_access')
                ->where('membership_id', $membership->id)
                ->pluck('site_id')
                ->all();
        }

        $roleCodes = $membership->roles->pluck('code');
        $limited = $roleCodes->intersect(['site_manager', 'supervisor'])->isNotEmpty()
            && $roleCodes->intersect(['organization_owner', 'tenant_admin', 'operations_manager', 'hr_manager', 'auditor', 'finance_manager'])->isEmpty();

        if ($limited) {
            if (! $membership->employee_id) {
                return [];
            }

            return DB::table('supervisor_sites')
                ->where('employee_id', $membership->employee_id)
                ->pluck('site_id')
                ->all();
        }

        if ($membership->portal === 'guard') {
            if (! $membership->employee_id) {
                return [];
            }

            return DB::table('shift_assignments')
                ->join('shifts', 'shifts.id', '=', 'shift_assignments.shift_id')
                ->where('shift_assignments.employee_id', $membership->employee_id)
                ->where('shift_assignments.status', 'assigned')
                ->where('shifts.ends_at', '>=', now()->subDay())
                ->pluck('shifts.site_id')
                ->unique()
                ->values()
                ->all();
        }

        if ($this->allows('sites.view') || $this->allows('sites.manage') || $this->allows('client.sites.view')) {
            return null;
        }

        return [];
    }

    public function scopeSites(Builder $query, string $column = 'id'): Builder
    {
        $ids = $this->siteIds();
        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $ids);
    }

    public function employeeId(): ?string
    {
        return app(TenantContext::class)->membership?->employee_id;
    }

    public function clientId(): ?string
    {
        return app(TenantContext::class)->membership?->client_id;
    }
}
