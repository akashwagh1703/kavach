<?php

$root = dirname(__DIR__).'/app/Domains';

function writeModel(string $root, string $relative, string $contents): void
{
    $path = $root.'/'.$relative;
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
}

function model(string $namespace, string $class, array $fillable, array $casts = [], string $extra = '', bool $tenant = true): string
{
    $uses = "use Illuminate\\Database\\Eloquent\\Concerns\\HasUuids;\nuse Illuminate\\Database\\Eloquent\\Model;\n";
    $traits = 'HasUuids';
    if ($tenant) {
        $uses = "use App\\Domains\\Shared\\Models\\Concerns\\BelongsToTenant;\n".$uses;
        $traits = 'BelongsToTenant, HasUuids';
    }
    $fill = implode("', '", $fillable);
    $castBody = '';
    if ($casts !== []) {
        $lines = [];
        foreach ($casts as $key => $type) {
            $lines[] = "            '{$key}' => '{$type}',";
        }
        $castBody = "\n\n    protected function casts(): array\n    {\n        return [\n".implode("\n", $lines)."\n        ];\n    }";
    }

    return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$uses}
class {$class} extends Model
{
    use {$traits};

    protected \$fillable = ['{$fill}'];{$castBody}{$extra}
}

PHP;
}

$defs = [
    ['Organization/Models/Branch.php', 'App\\Domains\\Organization\\Models', 'Branch', ['tenant_id', 'name', 'code', 'city', 'state', 'address', 'phone', 'status']],
    ['Organization/Models/Department.php', 'App\\Domains\\Organization\\Models', 'Department', ['tenant_id', 'branch_id', 'name']],
    ['Organization/Models/Client.php', 'App\\Domains\\Organization\\Models', 'Client', ['tenant_id', 'name', 'code', 'status', 'billing_email', 'phone', 'address']],
    ['Organization/Models/ClientContact.php', 'App\\Domains\\Organization\\Models', 'ClientContact', ['tenant_id', 'client_id', 'name', 'email', 'phone', 'designation', 'is_primary'], ['is_primary' => 'boolean']],
    ['Organization/Models/Contract.php', 'App\\Domains\\Organization\\Models', 'Contract', ['tenant_id', 'client_id', 'title', 'reference', 'starts_on', 'ends_on', 'status', 'service_details', 'billing_notes'], ['starts_on' => 'date', 'ends_on' => 'date']],
    ['Organization/Models/Site.php', 'App\\Domains\\Organization\\Models', 'Site', ['tenant_id', 'client_id', 'branch_id', 'name', 'code', 'address', 'city', 'state', 'latitude', 'longitude', 'instructions', 'requirements', 'status', 'shift_requirements'], ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'shift_requirements' => 'array']],
    ['Workforce/Models/Employee.php', 'App\\Domains\\Workforce\\Models', 'Employee', ['tenant_id', 'user_id', 'branch_id', 'employee_code', 'kind', 'name', 'phone', 'email', 'designation', 'status', 'date_of_joining', 'address'], ['date_of_joining' => 'date']],
    ['Workforce/Models/EmergencyContact.php', 'App\\Domains\\Workforce\\Models', 'EmergencyContact', ['tenant_id', 'employee_id', 'name', 'phone', 'relationship']],
    ['Workforce/Models/EmployeeDocument.php', 'App\\Domains\\Workforce\\Models', 'EmployeeDocument', ['tenant_id', 'employee_id', 'document_type', 'title', 'disk', 'path', 'original_name', 'mime', 'size', 'expires_on', 'verified_at'], ['expires_on' => 'date', 'verified_at' => 'datetime']],
    ['Scheduling/Models/ShiftTemplate.php', 'App\\Domains\\Scheduling\\Models', 'ShiftTemplate', ['tenant_id', 'name', 'start_time', 'end_time', 'crosses_midnight', 'grace_minutes'], ['crosses_midnight' => 'boolean']],
    ['Scheduling/Models/Roster.php', 'App\\Domains\\Scheduling\\Models', 'Roster', ['tenant_id', 'site_id', 'name', 'period_start', 'period_end', 'status', 'published_at', 'published_by'], ['period_start' => 'date', 'period_end' => 'date', 'published_at' => 'datetime']],
    ['Scheduling/Models/Shift.php', 'App\\Domains\\Scheduling\\Models', 'Shift', ['tenant_id', 'roster_id', 'site_id', 'template_id', 'name', 'starts_at', 'ends_at', 'required_guards', 'status'], ['starts_at' => 'datetime', 'ends_at' => 'datetime']],
    ['Scheduling/Models/ShiftAssignment.php', 'App\\Domains\\Scheduling\\Models', 'ShiftAssignment', ['tenant_id', 'shift_id', 'employee_id', 'role', 'status']],
    ['Scheduling/Models/ShiftReplacement.php', 'App\\Domains\\Scheduling\\Models', 'ShiftReplacement', ['tenant_id', 'assignment_id', 'replaced_employee_id', 'replacement_employee_id', 'replaced_by', 'reason']],
    ['Attendance/Models/AttendanceDay.php', 'App\\Domains\\Attendance\\Models', 'AttendanceDay', ['tenant_id', 'employee_id', 'site_id', 'shift_assignment_id', 'work_date', 'status', 'exception_type', 'check_in_at', 'check_out_at', 'verified_at', 'verified_by'], ['work_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime', 'verified_at' => 'datetime']],
    ['Attendance/Models/PunchEvent.php', 'App\\Domains\\Attendance\\Models', 'PunchEvent', ['tenant_id', 'attendance_day_id', 'employee_id', 'type', 'occurred_at', 'idempotency_key', 'latitude', 'longitude', 'source'], ['occurred_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7']],
    ['Attendance/Models/AttendanceCorrection.php', 'App\\Domains\\Attendance\\Models', 'AttendanceCorrection', ['tenant_id', 'attendance_day_id', 'requested_by', 'reason', 'proposed_check_in', 'proposed_check_out', 'status', 'reviewed_by', 'reviewed_at', 'review_note'], ['proposed_check_in' => 'datetime', 'proposed_check_out' => 'datetime', 'reviewed_at' => 'datetime']],
    ['Patrol/Models/PatrolPlan.php', 'App\\Domains\\Patrol\\Models', 'PatrolPlan', ['tenant_id', 'site_id', 'name', 'status']],
    ['Patrol/Models/Checkpoint.php', 'App\\Domains\\Patrol\\Models', 'Checkpoint', ['tenant_id', 'patrol_plan_id', 'name', 'code', 'sequence', 'token_hash']],
    ['Patrol/Models/PatrolRun.php', 'App\\Domains\\Patrol\\Models', 'PatrolRun', ['tenant_id', 'patrol_plan_id', 'employee_id', 'shift_assignment_id', 'scheduled_for', 'status', 'started_at', 'completed_at'], ['scheduled_for' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime']],
    ['Patrol/Models/PatrolEvent.php', 'App\\Domains\\Patrol\\Models', 'PatrolEvent', ['tenant_id', 'patrol_run_id', 'checkpoint_id', 'occurred_at', 'status', 'idempotency_key', 'notes'], ['occurred_at' => 'datetime']],
    ['Inspections/Models/InspectionTemplate.php', 'App\\Domains\\Inspections\\Models', 'InspectionTemplate', ['tenant_id', 'name', 'items'], ['items' => 'array']],
    ['Inspections/Models/Inspection.php', 'App\\Domains\\Inspections\\Models', 'Inspection', ['tenant_id', 'template_id', 'site_id', 'inspector_employee_id', 'status', 'scheduled_for', 'completed_at', 'notes'], ['scheduled_for' => 'datetime', 'completed_at' => 'datetime']],
    ['Inspections/Models/InspectionResponse.php', 'App\\Domains\\Inspections\\Models', 'InspectionResponse', ['inspection_id', 'item_key', 'result', 'notes'], [], '', false],
    ['Inspections/Models/CorrectiveAction.php', 'App\\Domains\\Inspections\\Models', 'CorrectiveAction', ['tenant_id', 'inspection_id', 'description', 'status', 'due_on'], ['due_on' => 'date']],
    ['Incidents/Models/IncidentCategory.php', 'App\\Domains\\Incidents\\Models', 'IncidentCategory', ['tenant_id', 'name', 'default_severity']],
    ['Incidents/Models/Incident.php', 'App\\Domains\\Incidents\\Models', 'Incident', ['tenant_id', 'site_id', 'category_id', 'reported_by', 'assigned_to_employee_id', 'title', 'description', 'priority', 'status', 'occurred_at', 'resolved_at', 'closed_at'], ['occurred_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime']],
    ['Incidents/Models/IncidentUpdate.php', 'App\\Domains\\Incidents\\Models', 'IncidentUpdate', ['incident_id', 'user_id', 'body', 'status_after'], [], '', false],
    ['Emergency/Models/SosAlert.php', 'App\\Domains\\Emergency\\Models', 'SosAlert', ['tenant_id', 'employee_id', 'site_id', 'status', 'notification_status', 'notification_note', 'message', 'idempotency_key', 'opened_at', 'acknowledged_at', 'resolved_at'], ['opened_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime']],
    ['Emergency/Models/SosEvent.php', 'App\\Domains\\Emergency\\Models', 'SosEvent', ['sos_alert_id', 'user_id', 'type', 'body'], [], '', false],
    ['Leave/Models/LeaveType.php', 'App\\Domains\\Leave\\Models', 'LeaveType', ['tenant_id', 'name', 'code', 'paid', 'annual_quota'], ['paid' => 'boolean']],
    ['Leave/Models/LeaveBalance.php', 'App\\Domains\\Leave\\Models', 'LeaveBalance', ['tenant_id', 'employee_id', 'leave_type_id', 'year', 'allocated', 'used'], ['allocated' => 'decimal:1', 'used' => 'decimal:1']],
    ['Leave/Models/LeaveRequest.php', 'App\\Domains\\Leave\\Models', 'LeaveRequest', ['tenant_id', 'employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'reason', 'status', 'reviewed_by', 'reviewed_at'], ['starts_on' => 'date', 'ends_on' => 'date', 'reviewed_at' => 'datetime']],
    ['Equipment/Models/EquipmentItem.php', 'App\\Domains\\Equipment\\Models', 'EquipmentItem', ['tenant_id', 'name', 'asset_code', 'status', 'notes'], [], "\n\n    protected \$table = 'equipment';"],
    ['Equipment/Models/EquipmentAllocation.php', 'App\\Domains\\Equipment\\Models', 'EquipmentAllocation', ['tenant_id', 'equipment_id', 'employee_id', 'allocated_at', 'returned_at', 'notes'], ['allocated_at' => 'datetime', 'returned_at' => 'datetime']],
    ['Finance/Models/Invoice.php', 'App\\Domains\\Finance\\Models', 'Invoice', ['tenant_id', 'client_id', 'number', 'status', 'issue_date', 'due_date', 'currency', 'subtotal', 'tax', 'total'], ['issue_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2']],
    ['Finance/Models/InvoiceItem.php', 'App\\Domains\\Finance\\Models', 'InvoiceItem', ['invoice_id', 'description', 'quantity', 'unit_price', 'amount'], ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'], '', false],
    ['Finance/Models/Payment.php', 'App\\Domains\\Finance\\Models', 'Payment', ['tenant_id', 'invoice_id', 'amount', 'paid_on', 'method', 'reference', 'notes'], ['amount' => 'decimal:2', 'paid_on' => 'date']],
    ['Finance/Models/Expense.php', 'App\\Domains\\Finance\\Models', 'Expense', ['tenant_id', 'category', 'amount', 'spent_on', 'description', 'status'], ['amount' => 'decimal:2', 'spent_on' => 'date']],
    ['Finance/Models/Payslip.php', 'App\\Domains\\Finance\\Models', 'Payslip', ['tenant_id', 'employee_id', 'period_start', 'period_end', 'status', 'gross', 'deductions', 'net', 'components'], ['period_start' => 'date', 'period_end' => 'date', 'gross' => 'decimal:2', 'deductions' => 'decimal:2', 'net' => 'decimal:2', 'components' => 'array']],
    ['Compliance/Models/ComplianceRecord.php', 'App\\Domains\\Compliance\\Models', 'ComplianceRecord', ['tenant_id', 'subject_type', 'subject_id', 'title', 'status', 'due_on', 'notes'], ['due_on' => 'date']],
    ['Notifications/Models/AppNotification.php', 'App\\Domains\\Notifications\\Models', 'AppNotification', ['tenant_id', 'user_id', 'category', 'title', 'body', 'data', 'delivery_status', 'failure_reason', 'read_at'], ['data' => 'array', 'read_at' => 'datetime']],
    ['Identity/Models/Invitation.php', 'App\\Domains\\Identity\\Models', 'Invitation', ['tenant_id', 'email', 'name', 'portal', 'role_code', 'client_id', 'employee_id', 'token_hash', 'expires_at', 'accepted_at'], ['expires_at' => 'datetime', 'accepted_at' => 'datetime']],
    ['Identity/Models/LoginAttempt.php', 'App\\Domains\\Identity\\Models', 'LoginAttempt', ['email', 'portal', 'ip', 'successful', 'reason', 'created_at'], ['successful' => 'boolean', 'created_at' => 'datetime'], "\n\n    public \$timestamps = false;", false],
    ['Identity/Models/MfaChallenge.php', 'App\\Domains\\Identity\\Models', 'MfaChallenge', ['user_id', 'portal', 'membership_id', 'token_hash', 'expires_at', 'consumed_at'], ['expires_at' => 'datetime', 'consumed_at' => 'datetime'], '', false],
    ['Identity/Models/VerificationCode.php', 'App\\Domains\\Identity\\Models', 'VerificationCode', ['user_id', 'purpose', 'code_hash', 'expires_at', 'consumed_at'], ['expires_at' => 'datetime', 'consumed_at' => 'datetime'], '', false],
    ['Organization/Models/ServiceRequest.php', 'App\\Domains\\Organization\\Models', 'ServiceRequest', ['tenant_id', 'client_id', 'site_id', 'user_id', 'subject', 'body', 'status']],
];

foreach ($defs as $def) {
    $relative = $def[0];
    $namespace = $def[1];
    $class = $def[2];
    $fillable = $def[3];
    $casts = $def[4] ?? [];
    $extra = $def[5] ?? '';
    $tenant = $def[6] ?? true;
    writeModel($root, $relative, model($namespace, $class, $fillable, $casts, $extra, (bool) $tenant));
    echo $relative, PHP_EOL;
}
