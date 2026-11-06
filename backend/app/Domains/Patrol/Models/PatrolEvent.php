<?php

declare(strict_types=1);

namespace App\Domains\Patrol\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PatrolEvent extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'patrol_run_id', 'checkpoint_id', 'occurred_at', 'status', 'idempotency_key', 'notes'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
