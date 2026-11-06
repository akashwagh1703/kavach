<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCorrection extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'attendance_day_id', 'requested_by', 'reason', 'proposed_check_in', 'proposed_check_out', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return [
            'proposed_check_in' => 'datetime',
            'proposed_check_out' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }
}
