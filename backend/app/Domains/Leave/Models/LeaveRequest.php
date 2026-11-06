<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveRequest extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'reason', 'status', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }
}
