<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Attendance\Models\AttendanceCorrection;
use App\Domains\Attendance\Models\AttendanceDay;
use App\Domains\Attendance\Services\AttendanceService;
use App\Domains\Emergency\Models\SosAlert;
use App\Domains\Emergency\Services\SosService;
use App\Domains\Equipment\Models\EquipmentAllocation;
use App\Domains\Equipment\Models\EquipmentItem;
use App\Domains\Finance\Models\Expense;
use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\Payment;
use App\Domains\Finance\Models\Payslip;
use App\Domains\Finance\Services\FinanceService;
use App\Domains\Identity\Services\AccessService;
use App\Domains\Incidents\Models\Incident;
use App\Domains\Incidents\Models\IncidentCategory;
use App\Domains\Incidents\Models\IncidentUpdate;
use App\Domains\Inspections\Models\CorrectiveAction;
use App\Domains\Inspections\Models\Inspection;
use App\Domains\Inspections\Models\InspectionResponse;
use App\Domains\Inspections\Models\InspectionTemplate;
use App\Domains\Leave\Models\LeaveRequest;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Notifications\Models\AppNotification;
use App\Domains\Organization\Models\ServiceRequest;
use App\Domains\Patrol\Models\PatrolPlan;
use App\Domains\Patrol\Models\PatrolRun;
use App\Domains\Patrol\Services\PatrolService;
use App\Domains\Scheduling\Models\Roster;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\ShiftTemplate;
use App\Domains\Scheduling\Services\SchedulingService;
use App\Domains\Workforce\Models\Employee;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Security\Idempotency;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    use Responds;

    public function templates(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'scheduling.view' : 'scheduling.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i'],
                'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            ]);
            $crosses = $data['end_time'] <= $data['start_time'];

            return ApiResponse::data(ShiftTemplate::query()->create($data + ['crosses_midnight' => $crosses]), [], null, 201);
        }

        return ApiResponse::data(ShiftTemplate::query()->orderBy('name')->get());
    }

    public function rosters(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'scheduling.view' : 'scheduling.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'site_id' => ['required', 'uuid'],
                'name' => ['required', 'string', 'max:160'],
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            ]);

            return ApiResponse::data(Roster::query()->create($data + ['status' => 'draft']), [], null, 201);
        }
        $query = app(AccessService::class)->scopeSites(Roster::query(), 'site_id')->latest();

        return $this->page($query->paginate($this->perPage($request)), fn (Roster $roster) => $roster);
    }

    public function shifts(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'scheduling.view' : 'scheduling.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'roster_id' => ['required', 'uuid'],
                'site_id' => ['required', 'uuid'],
                'template_id' => ['nullable', 'uuid'],
                'name' => ['required', 'string', 'max:120'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['required', 'date', 'after:starts_at'],
                'required_guards' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            return ApiResponse::data(Shift::query()->create($data + ['status' => 'scheduled']), [], null, 201);
        }
        $query = app(AccessService::class)->scopeSites(Shift::query()->with('assignments'), 'site_id')->orderBy('starts_at');
        if ($request->filled('from')) {
            $query->where('starts_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('starts_at', '<=', $request->date('to'));
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Shift $shift) => $shift);
    }

    public function publishRoster(Roster $roster): JsonResponse
    {
        $this->permit('scheduling.manage');
        if ($roster->status !== 'draft') {
            throw ValidationException::withMessages(['roster' => __('scheduling.not_draft')]);
        }
        $roster->forceFill(['status' => 'published', 'published_at' => now(), 'published_by' => request()->user()->id])->save();

        return ApiResponse::data($roster);
    }

    public function assign(Request $request, Shift $shift, SchedulingService $scheduling): JsonResponse
    {
        $this->permit('scheduling.manage');
        $data = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'role' => ['nullable', 'in:guard,supervisor'],
        ]);
        $employee = Employee::query()->where('status', 'active')->findOrFail($data['employee_id']);

        return ApiResponse::data($scheduling->assign($shift, $employee, $data['role'] ?? 'guard'), [], null, 201);
    }

    public function replace(Request $request, ShiftAssignment $assignment, SchedulingService $scheduling): JsonResponse
    {
        $this->permit('scheduling.manage');
        $data = $request->validate([
            'employee_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $employee = Employee::query()->where('status', 'active')->findOrFail($data['employee_id']);

        return ApiResponse::data($scheduling->replace($assignment, $employee, $request->user()->id, $data['reason'] ?? null));
    }

    public function myShift(): JsonResponse
    {
        $this->permit('attendance.punch');
        $employeeId = app(TenantContext::class)->membership->employee_id;
        $now = now();
        $current = ShiftAssignment::query()->with('shift.site')
            ->where('employee_id', $employeeId)
            ->where('status', 'assigned')
            ->whereHas('shift', fn ($q) => $q->where('ends_at', '>=', $now->copy()->subHours(2))->where('starts_at', '<=', $now->copy()->addHours(12)))
            ->get();
        $upcoming = ShiftAssignment::query()->with('shift.site')
            ->where('employee_id', $employeeId)
            ->where('status', 'assigned')
            ->whereHas('shift', fn ($q) => $q->where('starts_at', '>', $now)->whereHas('roster', fn ($r) => $r->where('status', 'published')))
            ->limit(10)
            ->get();

        return ApiResponse::data(['current' => $current, 'upcoming' => $upcoming]);
    }

    public function checkIn(Request $request, AttendanceService $attendance): JsonResponse
    {
        $this->permit('attendance.punch');
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $employee = Employee::query()->findOrFail(app(TenantContext::class)->membership->employee_id);
        $day = $attendance->checkIn($employee, Idempotency::key($request), isset($data['latitude']) ? (float) $data['latitude'] : null, isset($data['longitude']) ? (float) $data['longitude'] : null);

        return ApiResponse::data($day, [], __('attendance.recorded'));
    }

    public function checkOut(Request $request, AttendanceService $attendance): JsonResponse
    {
        $this->permit('attendance.punch');
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $employee = Employee::query()->findOrFail(app(TenantContext::class)->membership->employee_id);
        $day = $attendance->checkOut($employee, Idempotency::key($request), isset($data['latitude']) ? (float) $data['latitude'] : null, isset($data['longitude']) ? (float) $data['longitude'] : null);

        return ApiResponse::data($day, [], __('attendance.recorded'));
    }

    public function attendance(Request $request): JsonResponse
    {
        $this->permitAny(['attendance.view', 'client.attendance.view']);
        $query = AttendanceDay::query()->latest('work_date');
        $membership = app(TenantContext::class)->membership;
        if ($membership->portal === 'guard') {
            $query->where('employee_id', $membership->employee_id);
        } else {
            $query = app(AccessService::class)->scopeSites($query, 'site_id');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $query->with([
            'employee:id,name,employee_code,kind,email',
            'site:id,name,code,city',
        ]);

        return $this->page($query->paginate($this->perPage($request)), fn (AttendanceDay $day) => $day);
    }

    public function verifyAttendance(AttendanceDay $attendance, AttendanceService $service): JsonResponse
    {
        return ApiResponse::data($service->verify($attendance, request()->user()->id));
    }

    public function correct(Request $request, AttendanceDay $attendance, AttendanceService $service): JsonResponse
    {
        $this->permit('attendance.correct');
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'proposed_check_in' => ['nullable', 'date'],
            'proposed_check_out' => ['nullable', 'date'],
        ]);

        return ApiResponse::data($service->requestCorrection($attendance, $request->user()->id, $data['reason'], $data['proposed_check_in'] ?? null, $data['proposed_check_out'] ?? null), [], null, 201);
    }

    public function reviewCorrection(Request $request, AttendanceCorrection $correction, AttendanceService $service): JsonResponse
    {
        $data = $request->validate([
            'approve' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::data($service->reviewCorrection($correction, $request->user()->id, (bool) $data['approve'], $data['note'] ?? null));
    }

    public function patrolPlans(Request $request, PatrolService $patrols): JsonResponse
    {
        if ($request->isMethod('get')) {
            $this->permitAny(['patrol.view', 'client.patrols.view']);
        } else {
            $this->permit('patrol.manage');
        }
        if ($request->isMethod('post')) {
            $data = $request->validate(['site_id' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:160']]);

            return ApiResponse::data(PatrolPlan::query()->create($data + ['status' => 'active']), [], null, 201);
        }

        return ApiResponse::data(app(AccessService::class)->scopeSites(PatrolPlan::query(), 'site_id')->get());
    }

    public function checkpoint(Request $request, PatrolPlan $plan, PatrolService $patrols): JsonResponse
    {
        $this->permit('patrol.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'code' => ['required', 'string', 'max:40'],
            'sequence' => ['required', 'integer', 'min:1'],
        ]);
        $created = $patrols->addCheckpoint($plan, $data['name'], $data['code'], (int) $data['sequence']);

        return ApiResponse::data([
            'checkpoint' => $created['checkpoint'],
            'token' => $created['token'],
        ], [], __('patrol.token_once'), 201);
    }

    public function patrolRuns(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'patrol.view' : 'patrol.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'patrol_plan_id' => ['required', 'uuid'],
                'employee_id' => ['required', 'uuid'],
                'scheduled_for' => ['nullable', 'date'],
            ]);

            return ApiResponse::data(PatrolRun::query()->create($data + ['status' => 'pending']), [], null, 201);
        }
        $query = PatrolRun::query()->latest();
        if (app(TenantContext::class)->membership->portal === 'guard') {
            $query->where('employee_id', app(TenantContext::class)->membership->employee_id);
        }

        return $this->page($query->paginate($this->perPage($request)), fn (PatrolRun $run) => $run);
    }

    public function patrolEvent(Request $request, PatrolRun $run, PatrolService $patrols): JsonResponse
    {
        $this->permit('patrol.complete');
        $data = $request->validate([
            'token' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $employee = Employee::query()->findOrFail(app(TenantContext::class)->membership->employee_id);
        $event = $patrols->recordCheckpoint($run, $employee, $data['token'], Idempotency::key($request), $data['notes'] ?? null);

        return ApiResponse::data($event, [], __('patrol.recorded'));
    }

    public function inspectionTemplates(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'inspections.view' : 'inspections.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.key' => ['required', 'string', 'max:60'],
                'items.*.label' => ['required', 'string', 'max:180'],
            ]);

            return ApiResponse::data(InspectionTemplate::query()->create($data), [], null, 201);
        }

        return ApiResponse::data(InspectionTemplate::query()->orderBy('name')->get());
    }

    public function inspections(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'inspections.view' : 'inspections.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'template_id' => ['required', 'uuid'],
                'site_id' => ['required', 'uuid'],
                'scheduled_for' => ['nullable', 'date'],
            ]);

            return ApiResponse::data(Inspection::query()->create($data + [
                'status' => 'scheduled',
                'inspector_employee_id' => app(TenantContext::class)->membership->employee_id,
            ]), [], null, 201);
        }

        return $this->page(
            app(AccessService::class)->scopeSites(Inspection::query(), 'site_id')->latest()->paginate($this->perPage($request)),
            fn (Inspection $inspection) => $inspection
        );
    }

    public function completeInspection(Request $request, Inspection $inspection): JsonResponse
    {
        $this->permit('inspections.manage');
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'responses' => ['required', 'array', 'min:1'],
            'responses.*.item_key' => ['required', 'string'],
            'responses.*.result' => ['required', 'in:pass,fail,na'],
            'responses.*.notes' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
        ]);
        DB::transaction(function () use ($inspection, $data) {
            foreach ($data['responses'] as $response) {
                InspectionResponse::query()->updateOrCreate(
                    ['inspection_id' => $inspection->id, 'item_key' => $response['item_key']],
                    ['result' => $response['result'], 'notes' => $response['notes'] ?? null],
                );
            }
            if (! empty($data['corrective_action'])) {
                CorrectiveAction::query()->create([
                    'inspection_id' => $inspection->id,
                    'description' => $data['corrective_action'],
                    'status' => 'open',
                ]);
            }
            $inspection->forceFill(['status' => 'completed', 'completed_at' => now(), 'notes' => $data['notes'] ?? null])->save();
        });

        return ApiResponse::data($inspection->fresh());
    }

    public function incidents(Request $request): JsonResponse
    {
        $report = $request->isMethod('post') && app(TenantContext::class)->membership->portal === 'guard';
        if ($request->isMethod('get')) {
            $this->permitAny(['incidents.view', 'client.incidents.view']);
        } else {
            $this->permit($report ? 'incidents.report' : 'incidents.manage');
        }
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'site_id' => ['nullable', 'uuid'],
                'category_id' => ['nullable', 'uuid'],
                'title' => ['required', 'string', 'max:180'],
                'description' => ['required', 'string'],
                'priority' => ['required', 'in:low,medium,high,critical'],
                'occurred_at' => ['nullable', 'date'],
            ]);
            $incident = Incident::query()->create($data + [
                'reported_by' => $request->user()->id,
                'status' => 'open',
                'occurred_at' => $data['occurred_at'] ?? now(),
            ]);
            IncidentUpdate::query()->create([
                'incident_id' => $incident->id,
                'user_id' => $request->user()->id,
                'body' => $data['description'],
                'status_after' => 'open',
            ]);

            return ApiResponse::data($incident, [], null, 201);
        }
        $query = Incident::query()->latest('occurred_at');
        $membership = app(TenantContext::class)->membership;
        if ($membership->portal === 'guard') {
            $query->where('reported_by', $request->user()->id);
        } else {
            $query = app(AccessService::class)->scopeSites($query, 'site_id');
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Incident $incident) => $incident);
    }

    public function incidentStatus(Request $request, Incident $incident): JsonResponse
    {
        $this->permit('incidents.manage');
        $data = $request->validate([
            'status' => ['required', 'in:assigned,investigating,resolved,closed'],
            'note' => ['required', 'string'],
            'assigned_to_employee_id' => ['nullable', 'uuid'],
        ]);
        $incident->forceFill([
            'status' => $data['status'],
            'assigned_to_employee_id' => $data['assigned_to_employee_id'] ?? $incident->assigned_to_employee_id,
            'resolved_at' => $data['status'] === 'resolved' ? now() : $incident->resolved_at,
            'closed_at' => $data['status'] === 'closed' ? now() : $incident->closed_at,
        ])->save();
        IncidentUpdate::query()->create([
            'incident_id' => $incident->id,
            'user_id' => $request->user()->id,
            'body' => $data['note'],
            'status_after' => $data['status'],
        ]);

        return ApiResponse::data($incident->fresh());
    }

    public function categories(): JsonResponse
    {
        $this->permit('incidents.view');

        return ApiResponse::data(IncidentCategory::query()->orderBy('name')->get());
    }

    public function sos(Request $request, SosService $sos): JsonResponse
    {
        if ($request->isMethod('post')) {
            $this->permit('emergency.raise');
            $data = $request->validate([
                'message' => ['nullable', 'string', 'max:500'],
                'site_id' => ['nullable', 'uuid'],
            ]);
            $employee = Employee::query()->findOrFail(app(TenantContext::class)->membership->employee_id);
            $alert = $sos->raise($employee, Idempotency::key($request), $data['message'] ?? null, $data['site_id'] ?? null);
            $message = $alert->notification_status === 'stored'
                ? __('emergency.recorded')
                : __('emergency.recorded_without_recipients');

            return ApiResponse::data($alert, [], $message, 201);
        }
        $this->permit('emergency.view');
        $query = SosAlert::query()->latest('opened_at');
        if (app(TenantContext::class)->membership->portal === 'guard') {
            $query->where('employee_id', app(TenantContext::class)->membership->employee_id);
        }

        return $this->page($query->paginate($this->perPage($request)), fn (SosAlert $alert) => $alert);
    }

    public function sosTransition(Request $request, SosAlert $alert, SosService $sos): JsonResponse
    {
        $this->permit('emergency.respond');
        $data = $request->validate([
            'type' => ['required', 'in:acknowledged,assigned,resolved'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return ApiResponse::data($sos->transition($alert, $data['type'], $request->user()->id, $data['note'] ?? null));
    }

    public function leave(Request $request): JsonResponse
    {
        $membership = app(TenantContext::class)->membership;
        if ($request->isMethod('post')) {
            $this->permit('leave.request');
            $data = $request->validate([
                'leave_type_id' => ['required', 'uuid'],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            return ApiResponse::data(LeaveRequest::query()->create($data + [
                'employee_id' => $membership->employee_id,
                'status' => 'pending',
            ]), [], null, 201);
        }
        $this->permit('leave.view');
        $query = LeaveRequest::query()->latest();
        if ($membership->portal === 'guard') {
            $query->where('employee_id', $membership->employee_id);
        }

        return $this->page($query->paginate($this->perPage($request)), fn (LeaveRequest $leave) => $leave);
    }

    public function reviewLeave(Request $request, LeaveRequest $leave): JsonResponse
    {
        $this->permit('leave.approve');
        $data = $request->validate(['approve' => ['required', 'boolean']]);
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['leave' => __('leave.closed')]);
        }
        $leave->forceFill([
            'status' => $data['approve'] ? 'approved' : 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        return ApiResponse::data($leave);
    }

    public function leaveTypes(): JsonResponse
    {
        $this->permit('leave.view');

        return ApiResponse::data(LeaveType::query()->orderBy('name')->get());
    }

    public function equipment(Request $request): JsonResponse
    {
        $this->permit('equipment.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'asset_code' => ['required', 'string', 'max:60'],
                'notes' => ['nullable', 'string'],
            ]);

            return ApiResponse::data(EquipmentItem::query()->create($data + ['status' => 'in_stock']), [], null, 201);
        }

        return ApiResponse::data(EquipmentItem::query()->orderBy('name')->get());
    }

    public function allocateEquipment(Request $request, EquipmentItem $equipment): JsonResponse
    {
        $this->permit('equipment.manage');
        $data = $request->validate(['employee_id' => ['required', 'uuid'], 'notes' => ['nullable', 'string']]);
        if ($equipment->status !== 'in_stock') {
            throw ValidationException::withMessages(['equipment' => __('equipment.unavailable')]);
        }
        $allocation = EquipmentAllocation::query()->create([
            'equipment_id' => $equipment->id,
            'employee_id' => $data['employee_id'],
            'allocated_at' => now(),
            'notes' => $data['notes'] ?? null,
        ]);
        $equipment->forceFill(['status' => 'allocated'])->save();

        return ApiResponse::data($allocation, [], null, 201);
    }

    public function returnEquipment(EquipmentItem $equipment): JsonResponse
    {
        $this->permit('equipment.manage');
        EquipmentAllocation::query()->where('equipment_id', $equipment->id)->whereNull('returned_at')->update(['returned_at' => now()]);
        $equipment->forceFill(['status' => 'in_stock'])->save();

        return ApiResponse::data($equipment);
    }

    public function invoices(Request $request, FinanceService $finance): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'finance.view' : 'finance.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'client_id' => ['nullable', 'uuid'],
                'number' => ['required', 'string', 'max:40'],
                'issue_date' => ['required', 'date'],
                'due_date' => ['nullable', 'date'],
                'tax' => ['required', 'numeric', 'min:0'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.description' => ['required', 'string'],
                'items.*.quantity' => ['required', 'numeric', 'gt:0'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            ]);
            $invoice = $finance->createInvoice([
                'client_id' => $data['client_id'] ?? null,
                'number' => $data['number'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => 'INR',
            ], $data['items'], number_format((float) $data['tax'], 2, '.', ''));

            return ApiResponse::data($invoice, [], null, 201);
        }

        return $this->page(Invoice::query()->latest('issue_date')->paginate($this->perPage($request)), fn (Invoice $invoice) => $invoice);
    }

    public function payment(Request $request): JsonResponse
    {
        $this->permit('finance.manage');
        $data = $request->validate([
            'invoice_id' => ['nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);
        $payment = Payment::query()->create($data);
        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::query()->find($data['invoice_id']);
            $paid = Payment::query()->where('invoice_id', $data['invoice_id'])->sum('amount');
            if ($invoice && bccomp((string) $paid, (string) $invoice->total, 2) >= 0) {
                $invoice->forceFill(['status' => 'paid'])->save();
            }
        }

        return ApiResponse::data($payment, [], null, 201);
    }

    public function expense(Request $request): JsonResponse
    {
        $this->permit('finance.manage');
        $data = $request->validate([
            'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'spent_on' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        return ApiResponse::data(Expense::query()->create($data + ['status' => 'recorded']), [], null, 201);
    }

    public function payslips(Request $request, FinanceService $finance): JsonResponse
    {
        $membership = app(TenantContext::class)->membership;
        if ($request->isMethod('post')) {
            $this->permit('finance.manage');
            $data = $request->validate([
                'employee_id' => ['required', 'uuid'],
                'period_start' => ['required', 'date'],
                'period_end' => ['required', 'date', 'after_or_equal:period_start'],
                'gross' => ['required', 'numeric', 'min:0'],
                'deductions' => ['required', 'numeric', 'min:0'],
                'net' => ['required', 'numeric', 'min:0'],
                'components' => ['required', 'array'],
            ]);

            $payslip = $finance->createPayslip($data, $data['components'])->load('employee:id,name,employee_code,kind');

            return ApiResponse::data($payslip, [], 'Payslip draft created.', 201);
        }
        $query = Payslip::query()->with('employee:id,name,employee_code,kind')->latest('period_end');
        if ($membership->portal === 'guard') {
            $this->permit('attendance.view');
            $query->where('employee_id', $membership->employee_id)->whereIn('status', ['approved', 'paid']);
        } else {
            $this->permit('finance.view');
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Payslip $payslip) => $payslip);
    }

    public function approvePayslip(Payslip $payslip): JsonResponse
    {
        $this->permit('finance.manage');
        $payslip->forceFill(['status' => 'approved'])->save();

        return ApiResponse::data($payslip->load('employee:id,name,employee_code,kind'), [], 'Payslip approved.');
    }

    public function notifications(Request $request): JsonResponse
    {
        $this->permit('notifications.view');
        $query = AppNotification::query()->where('user_id', $request->user()->id)->latest();

        return $this->page($query->paginate($this->perPage($request)), fn (AppNotification $notification) => $notification);
    }

    public function readNotification(AppNotification $notification): JsonResponse
    {
        $this->permit('notifications.view');
        abort_unless($notification->user_id === request()->user()->id, 404);
        $notification->forceFill(['read_at' => now()])->save();

        return ApiResponse::data($notification);
    }

    public function report(Request $request, string $type): JsonResponse|StreamedResponse
    {
        $this->permit($request->boolean('export') ? 'reports.export' : 'reports.view');
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfDay();
        $rows = match ($type) {
            'attendance' => AttendanceDay::query()->whereBetween('work_date', [$from, $to])->get(),
            'incidents' => Incident::query()->whereBetween('occurred_at', [$from, $to])->get(),
            'patrols' => PatrolRun::query()->whereBetween('created_at', [$from, $to])->get(),
            'workforce' => Employee::query()->get(),
            'finance' => Invoice::query()->whereBetween('issue_date', [$from, $to])->get(),
            default => throw ValidationException::withMessages(['type' => __('reports.unknown')]),
        };
        if ($request->boolean('export')) {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                if ($rows->isEmpty()) {
                    fputcsv($out, ['empty']);
                    fclose($out);

                    return;
                }
                fputcsv($out, array_keys($rows->first()->toArray()));
                foreach ($rows as $row) {
                    fputcsv($out, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row->toArray()));
                }
                fclose($out);
            }, $type.'.csv', ['Content-Type' => 'text/csv']);
        }

        return ApiResponse::data([
            'type' => $type,
            'count' => $rows->count(),
            'rows' => $rows->take(200)->values(),
        ]);
    }

    public function compliance(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'compliance.view' : 'compliance.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:180'],
                'subject_type' => ['required', 'string', 'max:80'],
                'subject_id' => ['nullable', 'uuid'],
                'due_on' => ['nullable', 'date'],
                'notes' => ['nullable', 'string'],
            ]);

            return ApiResponse::data(\App\Domains\Compliance\Models\ComplianceRecord::query()->create($data + ['status' => 'open']), [], null, 201);
        }

        return ApiResponse::data(\App\Domains\Compliance\Models\ComplianceRecord::query()->orderBy('due_on')->get());
    }

    public function serviceRequests(Request $request): JsonResponse
    {
        $membership = app(TenantContext::class)->membership;
        if ($request->isMethod('post')) {
            $this->permit('client.requests.manage');
            $data = $request->validate([
                'site_id' => ['nullable', 'uuid'],
                'subject' => ['required', 'string', 'max:180'],
                'body' => ['required', 'string'],
            ]);
            $allowed = app(AccessService::class)->siteIds();
            if (! empty($data['site_id']) && $allowed !== null && ! in_array($data['site_id'], $allowed, true)) {
                abort(404);
            }

            return ApiResponse::data(ServiceRequest::query()->create($data + [
                'client_id' => $membership->client_id,
                'user_id' => $request->user()->id,
                'status' => 'open',
            ]), [], null, 201);
        }
        $this->permit('client.requests.manage');
        $query = ServiceRequest::query()->where('client_id', $membership->client_id)->latest();

        return $this->page($query->paginate($this->perPage($request)), fn (ServiceRequest $item) => $item);
    }
}
