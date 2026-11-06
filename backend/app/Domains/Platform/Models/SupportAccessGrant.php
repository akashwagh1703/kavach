<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAccessGrant extends Model
{
    use HasUuids;

    protected $fillable = [
        'platform_user_id', 'tenant_id', 'purpose', 'permissions', 'starts_at', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isOpen(): bool
    {
        return $this->revoked_at === null
            && $this->starts_at->lte(now())
            && $this->expires_at->gt(now());
    }
}
