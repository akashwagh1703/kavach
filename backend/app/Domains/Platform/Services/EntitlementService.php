<?php

declare(strict_types=1);

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Models\Subscription;
use App\Support\Tenancy\TenantContext;

class EntitlementService
{
    /** @var array<string, string> */
    private const MODULE_FEATURE = [
        'clients' => 'module.clients',
        'sites' => 'module.sites',
        'workforce' => 'module.workforce',
        'scheduling' => 'module.scheduling',
        'attendance' => 'module.attendance',
        'patrol' => 'module.patrol',
        'inspections' => 'module.inspections',
        'incidents' => 'module.incidents',
        'emergency' => 'module.emergency',
        'leave' => 'module.leave',
        'equipment' => 'module.equipment',
        'finance' => 'module.finance',
        'compliance' => 'module.compliance',
        'reports' => 'module.reports',
        'client' => 'module.client_portal',
    ];

    public function allows(string $featureCode): bool
    {
        $tenantId = app(TenantContext::class)->tenantId;
        if (! $tenantId) {
            return false;
        }

        $subscription = Subscription::query()->with('plan.features')->where('tenant_id', $tenantId)->first();
        if (! $subscription || ! $subscription->isUsable()) {
            return false;
        }

        return $subscription->plan->features->contains(fn ($feature) => $feature->code === $featureCode);
    }

    public function allowsModule(string $module): bool
    {
        $feature = self::MODULE_FEATURE[$module] ?? null;

        return $feature ? $this->allows($feature) : false;
    }
}
