<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PunchEvent extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'attendance_day_id', 'employee_id', 'type', 'occurred_at', 'idempotency_key', 'latitude', 'longitude', 'source'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
