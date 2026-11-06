<?php

declare(strict_types=1);

namespace App\Domains\Patrol\Services;

use App\Domains\Patrol\Models\Checkpoint;
use App\Domains\Patrol\Models\PatrolEvent;
use App\Domains\Patrol\Models\PatrolPlan;
use App\Domains\Patrol\Models\PatrolRun;
use App\Domains\Workforce\Models\Employee;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PatrolService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{checkpoint: Checkpoint, token: string}
     */
    public function addCheckpoint(PatrolPlan $plan, string $name, string $code, int $sequence): array
    {
        $token = Str::random(40);
        $checkpoint = Checkpoint::query()->create([
            'patrol_plan_id' => $plan->id,
            'name' => $name,
            'code' => $code,
            'sequence' => $sequence,
            'token_hash' => hash('sha256', $token),
        ]);

        return ['checkpoint' => $checkpoint, 'token' => $token];
    }

    public function recordCheckpoint(PatrolRun $run, Employee $employee, string $token, string $key, ?string $notes): PatrolEvent
    {
        if ($run->employee_id !== $employee->id) {
            throw ValidationException::withMessages(['patrol' => __('patrol.not_assigned')]);
        }

        $existing = PatrolEvent::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        $checkpoint = Checkpoint::query()
            ->where('patrol_plan_id', $run->patrol_plan_id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $checkpoint) {
            throw ValidationException::withMessages(['token' => __('patrol.token_invalid')]);
        }

        try {
            return DB::transaction(function () use ($run, $checkpoint, $key, $notes) {
                $now = now();
                $status = 'on_time';
                if ($run->scheduled_for && $now->gt($run->scheduled_for->copy()->addMinutes(15))) {
                    $status = 'late';
                }

                $event = PatrolEvent::query()->create([
                    'patrol_run_id' => $run->id,
                    'checkpoint_id' => $checkpoint->id,
                    'occurred_at' => $now,
                    'status' => $status,
                    'idempotency_key' => $key,
                    'notes' => $notes,
                ]);

                if (! $run->started_at) {
                    $run->forceFill(['started_at' => $now, 'status' => 'in_progress'])->save();
                }

                $expected = Checkpoint::query()->where('patrol_plan_id', $run->patrol_plan_id)->count();
                $done = PatrolEvent::query()->where('patrol_run_id', $run->id)->count();
                if ($expected > 0 && $done >= $expected) {
                    $run->forceFill(['status' => 'completed', 'completed_at' => $now])->save();
                }

                $this->audit->log('patrol.checkpoint', $event, ['status' => $status]);

                return $event;
            });
        } catch (QueryException $exception) {
            $existing = PatrolEvent::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            throw $exception;
        }
    }
}
