<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Organization\Models\Site;
use App\Domains\Platform\Models\UsageRecord;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workforce\Models\Employee;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class RecordUsage extends Command
{
    protected $signature = 'kavach:record-usage';

    protected $description = 'Store daily workforce and site counts for each tenant';

    public function handle(): int
    {
        $context = app(TenantContext::class);
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($context) {
            $context->activate($tenant->id);
            UsageRecord::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'recorded_on' => now()->toDateString()],
                [
                    'employees' => Employee::query()->count(),
                    'sites' => Site::query()->count(),
                    'active_guards' => Employee::query()->where('kind', 'guard')->where('status', 'active')->count(),
                ],
            );
            $context->clear();
        });
        $this->info('Usage recorded.');

        return self::SUCCESS;
    }
}
