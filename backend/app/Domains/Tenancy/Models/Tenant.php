<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Models;

use App\Domains\Organization\Models\Branch;
use App\Domains\Platform\Models\Subscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'legal_name', 'slug', 'business_type', 'status', 'onboarding_step',
        'onboarding_completed_at', 'email', 'phone', 'timezone', 'city', 'state',
        'address', 'settings', 'branding',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'branding' => 'array',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function clientWorkflowEnabled(): bool
    {
        if ($this->business_type === 'inhouse') {
            return false;
        }

        return (bool) ($this->settings['client_workflow_enabled'] ?? true);
    }

    public function graceMinutes(): int
    {
        return (int) ($this->settings['attendance_grace_minutes'] ?? 15);
    }
}