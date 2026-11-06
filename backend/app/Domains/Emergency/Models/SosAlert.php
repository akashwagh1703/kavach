<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SosAlert extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'employee_id', 'site_id', 'status', 'notification_status', 'notification_note', 'message', 'idempotency_key', 'opened_at', 'acknowledged_at', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
