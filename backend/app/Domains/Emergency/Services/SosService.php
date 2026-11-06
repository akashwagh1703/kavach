<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Services;

use App\Domains\Emergency\Models\SosAlert;
use App\Domains\Emergency\Models\SosEvent;
use App\Domains\Identity\Models\Membership;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Workforce\Models\Employee;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SosService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function raise(Employee $employee, string $key, ?string $message, ?string $siteId): SosAlert
    {
        $existing = SosAlert::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($employee, $key, $message, $siteId) {
                $alert = SosAlert::query()->create([
                    'employee_id' => $employee->id,
                    'site_id' => $siteId,
                    'status' => 'open',
                    'message' => $message,
                    'idempotency_key' => $key,
                    'opened_at' => now(),
                    'notification_status' => 'pending',
                ]);

                SosEvent::query()->create([
                    'sos_alert_id' => $alert->id,
                    'user_id' => $employee->user_id,
                    'type' => 'opened',
                    'body' => $message,
                ]);

                $recipients = $this->recipientUserIds($siteId);
                if ($recipients === []) {
                    $alert->forceFill([
                        'notification_status' => 'failed',
                        'notification_note' => 'no_recipients',
                    ])->save();
                } else {
                    foreach ($recipients as $userId) {
                        $this->notifications->notify(
                            $userId,
                            'sos',
                            'SOS alert',
                            $message ?: 'A guard raised an SOS alert.',
                            ['sos_alert_id' => $alert->id, 'site_id' => $siteId],
                        );
                    }
                    $alert->forceFill(['notification_status' => 'stored'])->save();
                }

                $this->audit->log('sos.raised', $alert, [
                    'notification_status' => $alert->notification_status,
                    'recipients' => count($recipients),
                ]);

                return $alert->fresh();
            });
        } catch (QueryException $exception) {
            $existing = SosAlert::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            throw $exception;
        }
    }

    public function transition(SosAlert $alert, string $type, string $userId, ?string $note): SosAlert
    {
        $next = match ($type) {
            'acknowledged' => 'acknowledged',
            'assigned' => 'responding',
            'resolved' => 'resolved',
            default => throw ValidationException::withMessages(['type' => __('emergency.invalid_transition')]),
        };

        $allowed = [
            'open' => ['acknowledged', 'responding', 'resolved'],
            'acknowledged' => ['responding', 'resolved'],
            'responding' => ['resolved'],
            'resolved' => [],
        ];

        if (! in_array($next, $allowed[$alert->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('emergency.invalid_transition')]);
        }

        return DB::transaction(function () use ($alert, $type, $next, $userId, $note) {
            $alert->forceFill([
                'status' => $next,
                'acknowledged_at' => $type === 'acknowledged' ? now() : $alert->acknowledged_at,
                'resolved_at' => $next === 'resolved' ? now() : $alert->resolved_at,
            ])->save();
            SosEvent::query()->create([
                'sos_alert_id' => $alert->id,
                'user_id' => $userId,
                'type' => $type,
                'body' => $note,
            ]);
            $this->audit->log('sos.'.$type, $alert);

            return $alert->fresh();
        });
    }

    /**
     * @return list<string>
     */
    private function recipientUserIds(?string $siteId): array
    {
        $tenantId = app(TenantContext::class)->tenantId;
        $query = Membership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereHas('roles.permissions', fn ($q) => $q->where('code', 'emergency.respond'));

        if ($siteId) {
            $supervisorIds = DB::table('supervisor_sites')->where('site_id', $siteId)->pluck('employee_id');
            $scoped = (clone $query)->whereIn('employee_id', $supervisorIds)->pluck('user_id');
            if ($scoped->isNotEmpty()) {
                return $scoped->unique()->values()->all();
            }
        }

        return $query->pluck('user_id')->unique()->values()->all();
    }
}
