<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\Invitation;
use App\Domains\Organization\Models\Branch;
use App\Domains\Organization\Models\Client;
use App\Domains\Organization\Models\ClientContact;
use App\Domains\Organization\Models\Contract;
use App\Domains\Organization\Models\Site;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmergencyContact;
use App\Domains\Workforce\Services\DocumentService;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DirectoryController extends Controller
{
    use Responds;

    public function branches(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'organization.view' : 'branches.manage');
        if ($request->isMethod('post')) {
            $tenantId = app(TenantContext::class)->tenantId;
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('branches', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                ],
                'city' => ['nullable', 'string', 'max:120'],
                'state' => ['nullable', 'string', 'max:120'],
                'address' => ['nullable', 'string', 'max:500'],
                'phone' => ['nullable', 'string', 'max:20'],
            ]);

            return ApiResponse::data(Branch::query()->create($data + ['status' => 'active']), [], 'Branch added.', 201);
        }
        $query = Branch::query()->orderBy('name');
        if ($search = $this->search($request)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Branch $branch) => $branch);
    }

    public function departments(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'organization.view' : 'departments.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'branch_id' => ['nullable', 'uuid'],
            ]);

            return ApiResponse::data(\App\Domains\Organization\Models\Department::query()->create($data), [], null, 201);
        }

        return ApiResponse::data(\App\Domains\Organization\Models\Department::query()->orderBy('name')->get());
    }

    public function clients(Request $request): JsonResponse
    {
        $this->assertClientWorkflow();
        $this->permit($request->isMethod('get') ? 'clients.view' : 'clients.manage');
        if ($request->isMethod('post')) {
            $tenantId = app(TenantContext::class)->tenantId;
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('clients', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                ],
                'billing_email' => ['nullable', 'email'],
                'phone' => ['nullable', 'string', 'max:20'],
                'address' => ['nullable', 'string', 'max:500'],
            ]);

            return ApiResponse::data(Client::query()->create($data + ['status' => 'active']), [], 'Client added.', 201);
        }
        $query = Client::query()->orderBy('name');
        if ($search = $this->search($request)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Client $client) => $client);
    }

    public function clientContact(Request $request, Client $client): JsonResponse
    {
        $this->assertClientWorkflow();
        $this->permit('clients.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        return ApiResponse::data(ClientContact::query()->create($data + ['client_id' => $client->id]), [], null, 201);
    }

    public function contracts(Request $request): JsonResponse
    {
        $this->assertClientWorkflow();
        $this->permit('contracts.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'client_id' => ['required', 'uuid'],
                'title' => ['required', 'string', 'max:180'],
                'reference' => ['nullable', 'string', 'max:80'],
                'starts_on' => ['required', 'date'],
                'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
                'service_details' => ['nullable', 'string'],
                'billing_notes' => ['nullable', 'string'],
                'status' => ['nullable', 'in:draft,active,expired'],
            ]);

            return ApiResponse::data(Contract::query()->create($data), [], null, 201);
        }

        return $this->page(Contract::query()->latest()->paginate($this->perPage($request)), fn (Contract $contract) => $contract);
    }

    public function sites(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            $this->permitAny(['sites.view', 'client.sites.view']);
        } else {
            $this->permit('sites.manage');
        }
        if ($request->isMethod('post')) {
            $tenantId = app(TenantContext::class)->tenantId;
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('sites', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                ],
                'client_id' => ['nullable', 'uuid'],
                'branch_id' => ['nullable', 'uuid'],
                'address' => ['nullable', 'string', 'max:500'],
                'city' => ['nullable', 'string', 'max:120'],
                'state' => ['nullable', 'string', 'max:120'],
                'instructions' => ['nullable', 'string'],
                'requirements' => ['nullable', 'string'],
            ]);
            if (! empty($data['client_id'])) {
                $this->assertClientWorkflow();
            }

            return ApiResponse::data(Site::query()->create($data + ['status' => 'active']), [], 'Site added.', 201);
        }
        $query = app(\App\Domains\Identity\Services\AccessService::class)->scopeSites(Site::query())->orderBy('name');
        $membership = app(TenantContext::class)->membership;
        if ($membership?->portal === 'client' && $membership->client_id) {
            $query->where('client_id', $membership->client_id);
        }
        if ($search = $this->search($request)) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Site $site) => $site);
    }

    public function employees(Request $request): JsonResponse
    {
        $this->permit($request->isMethod('get') ? 'workforce.view' : 'workforce.manage');
        if ($request->isMethod('post')) {
            $tenantId = app(TenantContext::class)->tenantId;
            $data = $request->validate([
                'name' => ['required', 'string', 'max:160'],
                'employee_code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('employees', 'employee_code')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
                ],
                'kind' => ['required', 'in:guard,supervisor,staff'],
                'phone' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email'],
                'designation' => ['nullable', 'string', 'max:120'],
                'branch_id' => ['nullable', 'uuid'],
                'date_of_joining' => ['nullable', 'date'],
                'emergency_contact_name' => ['nullable', 'string', 'max:120'],
                'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            ]);
            $employee = DB::transaction(function () use ($data) {
                $employee = Employee::query()->create([
                    'name' => $data['name'],
                    'employee_code' => $data['employee_code'],
                    'kind' => $data['kind'],
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'designation' => $data['designation'] ?? null,
                    'branch_id' => $data['branch_id'] ?? null,
                    'date_of_joining' => $data['date_of_joining'] ?? null,
                    'status' => 'active',
                ]);
                if (! empty($data['emergency_contact_name']) && ! empty($data['emergency_contact_phone'])) {
                    EmergencyContact::query()->create([
                        'employee_id' => $employee->id,
                        'name' => $data['emergency_contact_name'],
                        'phone' => $data['emergency_contact_phone'],
                    ]);
                }

                return $employee;
            });

            return ApiResponse::data($employee, [], 'Person saved. Invite them next so they can sign in.', 201);
        }
        $query = Employee::query()->orderBy('name');
        if ($kind = $request->string('kind')->toString()) {
            $query->where('kind', $kind);
        }
        if ($search = $this->search($request)) {
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('employee_code', 'ilike', "%{$search}%"));
        }

        $paginator = $query->paginate($this->perPage($request));
        $pending = Invitation::query()
            ->whereIn('employee_id', $paginator->getCollection()->pluck('id')->filter())
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->unique('employee_id')
            ->keyBy('employee_id');

        return $this->page($paginator, function (Employee $employee) use ($pending) {
            $invite = $pending->get($employee->id);
            $payload = $employee->toArray();
            $payload['invite_status'] = $employee->user_id
                ? 'active'
                : ($invite ? 'pending' : 'none');
            $payload['invite_expires_at'] = $invite?->expires_at;

            return $payload;
        });
    }

    public function assignSupervisorSites(Request $request, Employee $employee): JsonResponse
    {
        $this->permit('sites.manage');
        if ($employee->kind !== 'supervisor') {
            throw ValidationException::withMessages(['employee' => __('workforce.not_supervisor')]);
        }
        $data = $request->validate(['site_ids' => ['required', 'array'], 'site_ids.*' => ['uuid']]);
        $valid = Site::query()->whereIn('id', $data['site_ids'])->pluck('id');
        DB::table('supervisor_sites')->where('employee_id', $employee->id)->delete();
        foreach ($valid as $siteId) {
            DB::table('supervisor_sites')->insert(['employee_id' => $employee->id, 'site_id' => $siteId]);
        }

        return ApiResponse::data(['site_ids' => $valid->values()], [], 'Supervisor site coverage saved.');
    }

    public function uploadDocument(Request $request, Employee $employee, DocumentService $documents): JsonResponse
    {
        $this->permit('workforce.documents.manage');
        $data = $request->validate([
            'file' => ['required', 'file', 'max:5120'],
            'document_type' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:160'],
            'expires_on' => ['nullable', 'date'],
        ]);
        $document = $documents->store($employee, $data['file'], $data['document_type'], $data['title'], $data['expires_on'] ?? null);

        return ApiResponse::data($document->makeHidden(['disk', 'path']), [], null, 201);
    }

    public function downloadDocument(string $document, DocumentService $documents): StreamedResponse
    {
        $record = \App\Domains\Workforce\Models\EmployeeDocument::query()->findOrFail($document);
        $employeeId = app(TenantContext::class)->membership?->employee_id;
        if ($employeeId !== $record->employee_id) {
            $this->permit('workforce.documents.view');
        }

        return $documents->download($record);
    }

    public function myDocuments(): JsonResponse
    {
        $employeeId = app(TenantContext::class)->membership?->employee_id;
        abort_unless($employeeId, 403);

        return ApiResponse::data(
            \App\Domains\Workforce\Models\EmployeeDocument::query()->where('employee_id', $employeeId)->latest()->get()
        );
    }

    public function invite(Request $request): JsonResponse
    {
        $this->permit('team.manage');
        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
            'portal' => ['required', 'in:tenant,client,guard,supervisor'],
            'role_code' => ['required', 'string'],
            'employee_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'site_ids' => ['nullable', 'array'],
            'site_ids.*' => ['uuid'],
        ]);
        $token = Str::random(64);
        $invitation = Invitation::query()->create([
            'email' => Str::lower($data['email']),
            'name' => $data['name'] ?? null,
            'portal' => $data['portal'],
            'role_code' => $data['role_code'],
            'employee_id' => $data['employee_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'site_ids' => $data['site_ids'] ?? null,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]);
        $acceptUrl = rtrim((string) env('FRONTEND_PUBLIC_URL', 'http://localhost:5173'), '/').'/accept-invitation';
        Mail::raw(
            "You are invited to KAVACH ({$data['portal']}).\n\nAccept at {$acceptUrl}\nInvitation token: {$token}",
            function ($message) use ($data) {
                $message->to($data['email'])->subject('KAVACH invitation');
            }
        );

        return ApiResponse::data([
            'id' => $invitation->id,
            'email' => $invitation->email,
            'portal' => $invitation->portal,
            'role_code' => $invitation->role_code,
            'expires_at' => $invitation->expires_at,
            'invitation_token' => $token,
            'accept_url' => $acceptUrl,
        ], [], 'Invitation created. Share the token so they can accept and sign in.', 201);
    }

    public function settings(Request $request): JsonResponse
    {
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->tenantId);
        if ($request->isMethod('get')) {
            $this->permit('organization.view');

            return ApiResponse::data(['settings' => $tenant->settings, 'profile' => $tenant->only(['name', 'legal_name', 'email', 'phone', 'timezone', 'city', 'state', 'address', 'business_type'])]);
        }
        $this->permit('settings.manage');
        $data = $request->validate([
            'attendance_grace_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'require_supervisor_verification' => ['sometimes', 'boolean'],
            'require_location' => ['sometimes', 'boolean'],
            'patrol_qr_enabled' => ['sometimes', 'boolean'],
            'client_workflow_enabled' => ['sometimes', 'boolean'],
        ]);
        if ($tenant->business_type === 'inhouse') {
            $data['client_workflow_enabled'] = false;
        }
        $tenant->forceFill(['settings' => array_merge($tenant->settings ?? [], $data)])->save();

        return ApiResponse::data(['settings' => $tenant->settings]);
    }

    public function dashboard(): JsonResponse
    {
        $this->permitAny(['organization.view', 'sites.view', 'attendance.view', 'emergency.view']);
        $access = app(\App\Domains\Identity\Services\AccessService::class);

        return ApiResponse::data([
            'sites' => $access->scopeSites(Site::query())->count(),
            'open_incidents' => $access->scopeSites(\App\Domains\Incidents\Models\Incident::query()->whereIn('status', ['open', 'assigned', 'investigating']), 'site_id')->count(),
            'open_sos' => $access->scopeSites(\App\Domains\Emergency\Models\SosAlert::query()->whereIn('status', ['open', 'acknowledged', 'responding']), 'site_id')->count(),
            'attendance_exceptions' => $access->scopeSites(\App\Domains\Attendance\Models\AttendanceDay::query()->where('status', 'exception')->whereDate('work_date', now()->toDateString()), 'site_id')->count(),
        ]);
    }

    private function assertClientWorkflow(): void
    {
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->tenantId);
        if (! $tenant->clientWorkflowEnabled()) {
            throw ValidationException::withMessages(['clients' => __('tenancy.clients_disabled')]);
        }
    }
}
