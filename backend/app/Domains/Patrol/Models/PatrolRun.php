<?php

declare(strict_types=1);

namespace App\Domains\Patrol\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PatrolRun extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'patrol_plan_id', 'employee_id', 'shift_assignment_id', 'scheduled_for', 'status', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
