<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Models;

use App\Domains\Attendance\Models\AttendanceDay;
use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use App\Domains\Workforce\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShiftAssignment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'shift_id', 'employee_id', 'role', 'status'];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(AttendanceDay::class, 'shift_assignment_id');
    }
}
