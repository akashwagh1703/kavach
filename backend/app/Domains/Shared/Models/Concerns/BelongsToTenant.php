<?php

declare(strict_types=1);

namespace App\Domains\Shared\Models\Concerns;

use App\Domains\Tenancy\Models\Tenant;
use App\Support\Tenancy\TenantAccessDenied;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $context = app(TenantContext::class);
            if (! $context->active) {
                return;
            }

            if ($context->tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            /** @var Model $model */
            $model = $builder->getModel();
            $builder->where($model->qualifyColumn('tenant_id'), $context->tenantId);
        });

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            if (! $context->active || $context->tenantId === null) {
                return;
            }

            if (! empty($model->getAttribute('tenant_id')) && $model->getAttribute('tenant_id') !== $context->tenantId) {
                throw new TenantAccessDenied();
            }

            $model->setAttribute('tenant_id', $context->tenantId);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
