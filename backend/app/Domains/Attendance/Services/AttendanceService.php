<?php

declare(strict_types=1);

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Models\AttendanceCorrection;
use App\Domains\Attendance\Models\AttendanceDay;
use App\Domains\Attendance\Models\PunchEvent;
use App\Domains\Identity\Services\AccessService;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workforce\Models\Employee;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private readonly AccessService $access,
        private readonly AuditLogger $audit,
    ) {}

    public function checkIn(Employee $employee, string $key, ?float $lat, ?float $lng): AttendanceDay
    {
        $existing = $this->existingPunch($key);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($employee, $key, $lat, $lng) {
                $now = now();
                $tenant = $this->tenant();
                $assignment = $this->coveringAssignment($employee, $now, $tenant);
                if (! $assignment) {
                    throw ValidationException::withMessages([
                        'check_in' => __('attendance.no_assignment'),
                    ]);
                }

                $this->assertLocation($tenant, $lat, $lng);
                $shift = $assignment->shift;
                $grace = $tenant->graceMinutes();
                $late = $now->gt($shift->starts_at->copy()->addMinutes($grace));

                $day = AttendanceDay::query()->firstOrCreate(
                    ['shift_assignment_id' => $assignment->id],
                    [
                        'employee_id' => $employee->id,
                        'site_id' => $shift->site_id,
                        'work_date' => $now->copy()->timezone($tenant->timezone)->toDateString(),
                        'status' => 'open',
                        'exception_type' => $late ? 'late' : null,
                        'check_in_at' => $now,
                    ],
                );

                if ($day->check_in_at && PunchEvent::query()->where('attendance_day_id', $day->id)->where('type', 'in')->exists()) {
                    throw ValidationException::withMessages([
                        'check_in' => __('attendance.already_open'),
                    ]);
                }

                $day->forceFill([
                    'status' => 'open',
                    'exception_type' => $late ? 'late' : $day->exception_type,
                    'check_in_at' => $now,
                ])->save();

                $this->punch($day, $employee, 'in', $now, $key, $lat, $lng);
                $this->audit->log('attendance.check_in', $day, ['late' => $late]);

                return $day->fresh();
            });
        } catch (QueryException $exception) {
            $replay = $this->existingPunch($key);
            if ($replay) {
                return $replay;
            }
            throw $exception;
        }
    }

    public function checkOut(Employee $employee, string $key, ?float $lat, ?float $lng): AttendanceDay
    {
        $existing = $this->existingPunch($key);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($employee, $key, $lat, $lng) {
                $now = now();
                $tenant = $this->tenant();
                $this->assertLocation($tenant, $lat, $lng);
                $day = AttendanceDay::query()
                    ->where('employee_id', $employee->id)
                    ->where('status', 'open')
                    ->whereNotNull('check_in_at')
                    ->latest('check_in_at')
                    ->lockForUpdate()
                    ->first();

                if (! $day) {
                    throw ValidationException::withMessages([
                        'check_out' => __('attendance.not_checked_in'),
                    ]);
                }

                $assignment = ShiftAssignment::query()->with('shift')->find($day->shift_assignment_id);
                $early = $assignment && $now->lt($assignment->shift->ends_at->copy()->subMinutes($tenant->graceMinutes()));
                $exception = $early ? 'early_departure' : $day->exception_type;

                $day->forceFill([
                    'check_out_at' => $now,
                    'status' => 'complete',
                    'exception_type' => $exception,
                ])->save();
                $this->punch($day, $employee, 'out', $now, $key, $lat, $lng);
                $this->audit->log('attendance.check_out', $day, ['early' => $early]);

                return $day->fresh();
            });
        } catch (QueryException $exception) {
            $replay = $this->existingPunch($key);
            if ($replay) {
                return $replay;
            }
            throw $exception;
        }
    }

    public function verify(AttendanceDay $day, string $userId): AttendanceDay
    {
        $this->access->assert('attendance.verify');
        if ($day->site_id) {
            $ids = $this->access->siteIds();
            if ($ids !== null && ! in_array($day->site_id, $ids, true)) {
                throw ValidationException::withMessages(['attendance' => __('attendance.outside_scope')]);
            }
        }

        $day->forceFill([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $userId,
        ])->save();
        $this->audit->log('attendance.verified', $day, ['exception_type' => $day->exception_type]);

        return $day->fresh();
    }

    public function requestCorrection(AttendanceDay $day, string $userId, string $reason, ?string $checkIn, ?string $checkOut): AttendanceCorrection
    {
        return AttendanceCorrection::query()->create([
            'attendance_day_id' => $day->id,
            'requested_by' => $userId,
            'reason' => $reason,
            'proposed_check_in' => $checkIn,
            'proposed_check_out' => $checkOut,
            'status' => 'pending',
        ]);
    }

    public function reviewCorrection(AttendanceCorrection $correction, string $userId, bool $approve, ?string $note): AttendanceCorrection
    {
        $this->access->assert('attendance.verify');
        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages(['correction' => __('attendance.correction_closed')]);
        }

        $correction->forceFill([
            'status' => $approve ? 'approved' : 'rejected',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        if ($approve) {
            $day = $correction->day ?? AttendanceDay::query()->findOrFail($correction->attendance_day_id);
            $day->forceFill([
                'check_in_at' => $correction->proposed_check_in ?? $day->check_in_at,
                'check_out_at' => $correction->proposed_check_out ?? $day->check_out_at,
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $userId,
            ])->save();
        }

        $this->audit->log('attendance.correction_reviewed', $correction, ['approved' => $approve]);

        return $correction->fresh();
    }

    public function scanMissingPunches(): int
    {
        $created = 0;
        $rows = ShiftAssignment::query()
            ->withoutGlobalScopes()
            ->with(['shift'])
            ->where('status', 'assigned')
            ->whereHas('shift', fn ($query) => $query->withoutGlobalScopes()->where('ends_at', '<', now())->where('status', '!=', 'cancelled'))
            ->whereDoesntHave('attendance')
            ->limit(500)
            ->get();

        foreach ($rows as $assignment) {
            $shift = $assignment->shift;
            if (! $shift) {
                continue;
            }
            $rosterPublished = DB::table('rosters')->where('id', $shift->roster_id)->value('status') === 'published';
            if (! $rosterPublished) {
                continue;
            }

            AttendanceDay::query()->withoutGlobalScopes()->create([
                'tenant_id' => $assignment->tenant_id,
                'employee_id' => $assignment->employee_id,
                'site_id' => $shift->site_id,
                'shift_assignment_id' => $assignment->id,
                'work_date' => $shift->ends_at->toDateString(),
                'status' => 'exception',
                'exception_type' => 'missing_punch',
            ]);
            $created++;
        }

        return $created;
    }

    private function coveringAssignment(Employee $employee, \Illuminate\Support\Carbon $now, Tenant $tenant): ?ShiftAssignment
    {
        $grace = $tenant->graceMinutes();

        return ShiftAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->where('status', 'assigned')
            ->whereHas('shift', function ($query) use ($now, $grace) {
                $query->where('starts_at', '<=', $now->copy()->addMinutes($grace))
                    ->where('ends_at', '>=', $now)
                    ->whereHas('roster', fn ($roster) => $roster->where('status', 'published'));
            })
            ->first();
    }

    private function punch(AttendanceDay $day, Employee $employee, string $type, \Illuminate\Support\Carbon $at, string $key, ?float $lat, ?float $lng): void
    {
        PunchEvent::query()->create([
            'attendance_day_id' => $day->id,
            'employee_id' => $employee->id,
            'type' => $type,
            'occurred_at' => $at,
            'idempotency_key' => $key,
            'latitude' => $lat,
            'longitude' => $lng,
            'source' => 'portal',
        ]);
    }

    private function existingPunch(string $key): ?AttendanceDay
    {
        $punch = PunchEvent::query()->where('idempotency_key', $key)->first();

        return $punch ? AttendanceDay::query()->find($punch->attendance_day_id) : null;
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(TenantContext::class)->tenantId);
    }

    private function assertLocation(Tenant $tenant, ?float $lat, ?float $lng): void
    {
        if (($tenant->settings['require_location'] ?? false) && ($lat === null || $lng === null)) {
            throw ValidationException::withMessages([
                'location' => __('attendance.location_required'),
            ]);
        }
    }
}
