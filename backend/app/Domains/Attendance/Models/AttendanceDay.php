<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Organization\Models\Site;
use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use App\Domains\Workforce\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDay extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'employee_id', 'site_id', 'shift_assignment_id', 'work_date', 'status', 'exception_type', 'check_in_at', 'check_out_at', 'verified_at', 'verified_by'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
