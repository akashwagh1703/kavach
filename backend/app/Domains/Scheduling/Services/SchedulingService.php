<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Services;

use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\ShiftReplacement;
use App\Domains\Workforce\Models\Employee;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchedulingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assign(Shift $shift, Employee $employee, string $role = 'guard'): ShiftAssignment
    {
        return DB::transaction(function () use ($shift, $employee, $role) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            $this->assertNoConflict($shift, $employee);

            $assignment = ShiftAssignment::query()->create([
                'shift_id' => $shift->id,
                'employee_id' => $employee->id,
                'role' => $role,
                'status' => 'assigned',
            ]);
            $this->audit->log('scheduling.assigned', $assignment, [
                'shift_id' => $shift->id,
                'employee_id' => $employee->id,
            ]);

            return $assignment;
        });
    }

    public function replace(ShiftAssignment $assignment, Employee $replacement, string $userId, ?string $reason): ShiftAssignment
    {
        return DB::transaction(function () use ($assignment, $replacement, $userId, $reason) {
            $assignment->load('shift');
            if ($assignment->status !== 'assigned') {
                throw ValidationException::withMessages(['assignment' => __('scheduling.not_active')]);
            }
            Employee::query()->whereKey($replacement->id)->lockForUpdate()->first();
            $this->assertNoConflict($assignment->shift, $replacement);

            $assignment->forceFill(['status' => 'replaced'])->save();
            $created = ShiftAssignment::query()->create([
                'shift_id' => $assignment->shift_id,
                'employee_id' => $replacement->id,
                'role' => $assignment->role,
                'status' => 'assigned',
            ]);
            ShiftReplacement::query()->create([
                'assignment_id' => $assignment->id,
                'replaced_employee_id' => $assignment->employee_id,
                'replacement_employee_id' => $replacement->id,
                'replaced_by' => $userId,
                'reason' => $reason,
            ]);
            $this->audit->log('scheduling.replaced', $created, ['replaced_assignment' => $assignment->id]);

            return $created;
        });
    }

    private function assertNoConflict(Shift $shift, Employee $employee): void
    {
        $overlap = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'assigned')
            ->whereHas('shift', function ($query) use ($shift) {
                $query->where('id', '!=', $shift->id)
                    ->where('starts_at', '<', $shift->ends_at)
                    ->where('ends_at', '>', $shift->starts_at);
            })
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'employee_id' => __('scheduling.conflict'),
            ]);
        }
    }
}
