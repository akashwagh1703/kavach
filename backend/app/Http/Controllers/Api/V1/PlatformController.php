<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Catalog\AccessCatalog;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Site;
use App\Domains\Platform\Models\Announcement;
use App\Domains\Platform\Models\AuditLog;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Platform\Models\SupportAccessGrant;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\OnboardingService;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformController extends Controller
{
    use Responds;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OnboardingService $onboarding,
    ) {}

    public function dashboard(): JsonResponse
    {
        $this->permit('platform.dashboard.view');

        return ApiResponse::data([
            'tenants' => [
                'draft' => Tenant::query()->where('status', 'draft')->count(),
                'active' => Tenant::query()->where('status', 'active')->count(),
                'suspended' => Tenant::query()->where('status', 'suspended')->count(),
            ],
            'subscriptions' => Subscription::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'open_grants' => SupportAccessGrant::query()->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
        ]);
    }

    public function tenants(Request $request): JsonResponse
    {
        $this->permit('platform.tenants.view');
        $query = Tenant::query()->with('subscription.plan')->orderByDesc('created_at');
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($search = $this->search($request)) {
            $query->where(fn ($q) => $q->where('name', 'ilike', "%{$search}%")->orWhere('slug', 'ilike', "%{$search}%"));
        }

        return $this->page($query->paginate($this->perPage($request)), fn (Tenant $tenant) => $this->tenantSummary($tenant));
    }

    public function storeTenant(Request $request): JsonResponse
    {
        $this->permit('platform.tenants.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'business_type' => ['required', 'in:agency,inhouse'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email'],
        ]);

        $token = null;
        $tenant = DB::transaction(function () use ($data, &$token) {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
                'business_type' => $data['business_type'],
                'status' => 'draft',
                'onboarding_step' => 2,
                'email' => $data['owner_email'],
                'settings' => [
                    'client_workflow_enabled' => $data['business_type'] === 'agency',
                    'attendance_grace_minutes' => 15,
                    'require_supervisor_verification' => true,
                    'require_location' => false,
                    'patrol_qr_enabled' => true,
                ],
            ]);
            $token = Str::random(64);
            \App\Domains\Identity\Models\Invitation::query()->create([
                'tenant_id' => $tenant->id,
                'email' => Str::lower($data['owner_email']),
                'name' => $data['owner_name'],
                'portal' => 'tenant',
                'role_code' => 'organization_owner',
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ]);
            Mail::raw(
                "You have been invited to set up {$tenant->name} on KAVACH.\n\nAccept at http://localhost:5173/accept-invitation\nInvitation token: {$token}",
                function ($message) use ($data) {
                    $message->to($data['owner_email'])->subject('KAVACH organization invitation');
                }
            );

            return $tenant;
        });
        $this->audit->log('tenant.provisioned', $tenant, [], $tenant->id);

        return ApiResponse::data([
            ...$this->tenantSummary($tenant),
            'invitation_token' => $token,
            'accept_url' => 'http://localhost:5173/accept-invitation',
        ], [], null, 201);
    }

    public function setTenantStatus(Request $request, Tenant $tenant): JsonResponse
    {
        $this->permit('platform.tenants.manage');
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);

        if ($data['status'] === 'active') {
            $plan = Plan::query()->where('code', 'enterprise')->first()
                ?? Plan::query()->where('is_active', true)->orderBy('sort_order')->first();
            if ($plan && ! Subscription::query()->where('tenant_id', $tenant->id)->exists()) {
                Subscription::query()->create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'trial',
                    'trial_ends_at' => now()->addDays($plan->trial_days ?: 14),
                ]);
            }
            $tenant->forceFill([
                'status' => 'active',
                'onboarding_step' => max((int) $tenant->onboarding_step, 10),
                'onboarding_completed_at' => $tenant->onboarding_completed_at ?? now(),
            ])->save();
        } else {
            $tenant->forceFill(['status' => 'suspended'])->save();
        }

        $this->audit->log('tenant.'.$data['status'], $tenant, [], $tenant->id);

        return ApiResponse::data($this->tenantSummary($tenant->fresh('subscription.plan')));
    }

    public function plans(): JsonResponse
    {
        $this->permit('platform.plans.manage');

        return ApiResponse::data(Plan::query()->with('features')->orderBy('sort_order')->get());
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $this->permit('platform.subscriptions.manage');
        $query = Subscription::query()->with(['plan', 'tenant'])->orderByDesc('updated_at');

        return $this->page($query->paginate($this->perPage($request)), fn (Subscription $subscription) => [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at,
            'plan' => $subscription->plan->code,
            'tenant' => ['id' => $subscription->tenant->id, 'name' => $subscription->tenant->name, 'status' => $subscription->tenant->status],
        ]);
    }

    public function changePlan(Request $request, Tenant $tenant): JsonResponse
    {
        $this->permit('platform.subscriptions.manage');
        $data = $request->validate([
            'plan' => ['required', 'string'],
            'status' => ['required', 'in:trial,active,past_due,cancelled'],
        ]);
        $plan = Plan::query()->where('code', $data['plan'])->first();
        if (! $plan) {
            throw ValidationException::withMessages(['plan' => __('onboarding.plan_invalid')]);
        }
        $subscription = Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['plan_id' => $plan->id, 'status' => $data['status']],
        );
        $this->audit->log('subscription.changed', $subscription, ['plan' => $plan->code], $tenant->id);

        return ApiResponse::data($subscription->load('plan'));
    }

    public function announcements(Request $request): JsonResponse
    {
        $this->permit('platform.announcements.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'title' => ['required', 'string', 'max:160'],
                'body' => ['required', 'string'],
                'audience' => ['required', 'in:all,tenants,platform'],
            ]);
            $announcement = Announcement::query()->create([
                ...$data,
                'published_at' => now(),
                'created_by' => $request->user()->id,
            ]);

            return ApiResponse::data($announcement, [], null, 201);
        }

        return ApiResponse::data(Announcement::query()->latest()->limit(50)->get());
    }

    public function audit(Request $request): JsonResponse
    {
        $this->permit('platform.audit.view');
        $query = AuditLog::query()->latest('created_at');

        return $this->page($query->paginate($this->perPage($request)), fn (AuditLog $log) => $log);
    }

    public function grants(Request $request): JsonResponse
    {
        $this->permit('platform.support.grant');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'tenant_id' => ['required', 'uuid'],
                'purpose' => ['required', 'string', 'max:500'],
                'hours' => ['required', 'integer', 'min:1', 'max:8'],
            ]);
            $allowed = AccessCatalog::supportViewPermissions();
            $grant = SupportAccessGrant::query()->create([
                'platform_user_id' => $request->user()->id,
                'tenant_id' => $data['tenant_id'],
                'purpose' => $data['purpose'],
                'permissions' => $allowed,
                'starts_at' => now(),
                'expires_at' => now()->addHours((int) $data['hours']),
            ]);
            $this->audit->log('support.grant.created', $grant, ['purpose' => $grant->purpose], $grant->tenant_id);

            return ApiResponse::data($grant, [], null, 201);
        }

        return ApiResponse::data(SupportAccessGrant::query()->where('platform_user_id', $request->user()->id)->latest()->limit(50)->get());
    }

    public function revokeGrant(SupportAccessGrant $grant): JsonResponse
    {
        $this->permit('platform.support.grant');
        abort_unless($grant->platform_user_id === request()->user()->id, 404);
        $grant->forceFill(['revoked_at' => now()])->save();
        $this->audit->log('support.grant.revoked', $grant, [], $grant->tenant_id);

        return ApiResponse::data($grant);
    }

    public function supportSites(Request $request, Tenant $tenant): JsonResponse
    {
        $this->permit('platform.support.grant');

        return ApiResponse::data(Site::query()->orderBy('name')->get(['id', 'name', 'code', 'status', 'city']));
    }

    public function health(): JsonResponse
    {
        $this->permit('platform.health.view');
        $database = 'ok';
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $database = 'error';
        }

        return ApiResponse::data([
            'app' => 'ok',
            'database' => $database,
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
        ]);
    }

    public function platformUsers(Request $request): JsonResponse
    {
        $this->permit('platform.users.manage');
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email'],
                'role' => ['required', 'in:platform_admin,support_operator'],
            ]);
            $token = Str::random(64);
            \App\Domains\Identity\Models\Invitation::query()->create([
                'tenant_id' => null,
                'email' => Str::lower($data['email']),
                'name' => $data['name'],
                'portal' => 'platform',
                'role_code' => $data['role'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(3),
            ]);
            Mail::raw('You have been invited to the KAVACH platform admin. Invitation token: '.$token, function ($message) use ($data) {
                $message->to($data['email'])->subject('KAVACH platform invitation');
            });

            return ApiResponse::data(['invited' => true], [], __('auth.invitation_sent'), 201);
        }

        $roleIds = Role::query()->whereNull('tenant_id')->where('portal', 'platform')->pluck('id');
        $userIds = DB::table('membership_role')
            ->join('memberships', 'memberships.id', '=', 'membership_role.membership_id')
            ->whereIn('role_id', $roleIds)
            ->pluck('memberships.user_id');

        return ApiResponse::data(User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email', 'status', 'mfa_enabled']));
    }

    private function tenantSummary(Tenant $tenant): array
    {
        $tenant->loadMissing('subscription.plan');

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'business_type' => $tenant->business_type,
            'status' => $tenant->status,
            'onboarding_step' => $tenant->onboarding_step,
            'plan' => $tenant->subscription?->plan?->code,
            'subscription_status' => $tenant->subscription?->status,
            'created_at' => $tenant->created_at,
        ];
    }
}
