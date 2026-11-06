<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Services;

use App\Domains\Identity\Models\Membership;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\VerificationCode;
use App\Domains\Incidents\Models\IncidentCategory;
use App\Domains\Leave\Models\LeaveType;
use App\Domains\Organization\Models\Branch;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\Subscription;
use App\Domains\Tenancy\Models\Tenant;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function register(string $name, string $email, string $password, string $phone = null): array
    {
        $email = Str::lower(trim($email));

        return DB::transaction(function () use ($name, $email, $password, $phone) {
            if (User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('onboarding.email_taken'),
                ]);
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
                'locale' => 'en',
                'status' => 'active',
            ]);

            $tenant = Tenant::query()->create([
                'name' => $name."'s organization",
                'slug' => $this->uniqueSlug($name),
                'business_type' => 'agency',
                'status' => 'draft',
                'onboarding_step' => 2,
                'email' => $email,
                'phone' => $phone,
                'settings' => $this->defaultSettings('agency'),
            ]);

            $membership = $this->membership($user, $tenant, 'tenant', 'organization_owner');
            app(TenantContext::class)->activate($tenant->id, $membership);
            $this->seedTenantDefaults($tenant);
            $this->sendVerification($user);
            $this->audit->log('tenant.registered', $tenant, [], $tenant->id);

            return ['user' => $user, 'tenant' => $tenant->fresh(), 'membership' => $membership];
        });
    }

    public function state(Tenant $tenant): array
    {
        return [
            'step' => $tenant->onboarding_step,
            'completed' => $tenant->onboarding_completed_at !== null,
            'business_type' => $tenant->business_type,
            'organization' => [
                'name' => $tenant->name,
                'legal_name' => $tenant->legal_name,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'city' => $tenant->city,
                'state' => $tenant->state,
                'address' => $tenant->address,
                'timezone' => $tenant->timezone,
            ],
            'email_verified' => (bool) request()->user()?->email_verified_at,
            'settings' => $tenant->settings,
            'subscription' => $tenant->subscription()->with('plan')->first(),
        ];
    }

    public function saveBusinessType(Tenant $tenant, string $type): Tenant
    {
        $this->guardStep($tenant, 2);
        $settings = $tenant->settings ?? [];
        $settings['client_workflow_enabled'] = $type === 'agency';
        $tenant->fill([
            'business_type' => $type,
            'settings' => $settings,
            'onboarding_step' => max($tenant->onboarding_step, 3),
        ])->save();

        return $tenant->fresh();
    }

    public function saveOrganization(Tenant $tenant, array $data): Tenant
    {
        $this->guardStep($tenant, 3);
        $tenant->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? $tenant->email,
            'phone' => $data['phone'] ?? $tenant->phone,
            'timezone' => $data['timezone'] ?? $tenant->timezone,
            'onboarding_step' => max($tenant->onboarding_step, 4),
        ])->save();

        return $tenant->fresh();
    }

    public function verifyEmail(User $user, Tenant $tenant, string $code): Tenant
    {
        $this->guardStep($tenant, 4);
        $record = VerificationCode::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'onboarding')
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record || ! hash_equals($record->code_hash, hash('sha256', $code))) {
            throw ValidationException::withMessages([
                'code' => __('onboarding.code_invalid'),
            ]);
        }

        $record->forceFill(['consumed_at' => now()])->save();
        $user->forceFill(['email_verified_at' => now()])->save();
        $tenant->forceFill(['onboarding_step' => max($tenant->onboarding_step, 5)])->save();

        return $tenant->fresh();
    }

    public function resendVerification(User $user): void
    {
        $this->sendVerification($user);
    }

    public function saveProfile(Tenant $tenant, array $data): Tenant
    {
        $this->guardStep($tenant, 5);
        $tenant->fill([
            'legal_name' => $data['legal_name'] ?? $tenant->name,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'onboarding_step' => max($tenant->onboarding_step, 6),
        ])->save();

        return $tenant->fresh();
    }

    public function saveBranch(Tenant $tenant, ?array $branch, bool $skip): Tenant
    {
        $this->guardStep($tenant, 6);
        if (! $skip && $branch) {
            Branch::query()->create([
                'name' => $branch['name'],
                'code' => $branch['code'],
                'city' => $branch['city'] ?? null,
                'state' => $branch['state'] ?? null,
                'address' => $branch['address'] ?? null,
                'phone' => $branch['phone'] ?? null,
                'status' => 'active',
            ]);
        }
        $tenant->forceFill(['onboarding_step' => max($tenant->onboarding_step, 7)])->save();

        return $tenant->fresh();
    }

    public function skipInvites(Tenant $tenant): Tenant
    {
        $this->guardStep($tenant, 7);
        $tenant->forceFill(['onboarding_step' => max($tenant->onboarding_step, 8)])->save();

        return $tenant->fresh();
    }

    public function saveWorkforceSettings(Tenant $tenant, array $settings): Tenant
    {
        $this->guardStep($tenant, 8);
        $current = $tenant->settings ?? [];
        $tenant->forceFill([
            'settings' => array_merge($current, [
                'attendance_grace_minutes' => (int) ($settings['attendance_grace_minutes'] ?? 15),
                'require_supervisor_verification' => (bool) ($settings['require_supervisor_verification'] ?? true),
                'require_location' => (bool) ($settings['require_location'] ?? false),
                'patrol_qr_enabled' => (bool) ($settings['patrol_qr_enabled'] ?? true),
            ]),
            'onboarding_step' => max($tenant->onboarding_step, 9),
        ])->save();

        return $tenant->fresh();
    }

    public function selectPlan(Tenant $tenant, string $planCode): Tenant
    {
        $this->guardStep($tenant, 9);
        $plan = Plan::query()->where('code', $planCode)->where('is_public', true)->where('is_active', true)->first();
        if (! $plan) {
            throw ValidationException::withMessages(['plan' => __('onboarding.plan_invalid')]);
        }

        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(max(1, (int) $plan->trial_days)),
                'current_period_ends_at' => now()->addDays(max(1, (int) $plan->trial_days)),
            ],
        );

        $tenant->forceFill(['onboarding_step' => max($tenant->onboarding_step, 10)])->save();

        return $tenant->fresh();
    }

    public function complete(Tenant $tenant): Tenant
    {
        if ($tenant->onboarding_step < 10 || ! $tenant->subscription) {
            throw ValidationException::withMessages([
                'onboarding' => __('onboarding.incomplete'),
            ]);
        }

        if ($tenant->onboarding_completed_at) {
            return $tenant;
        }

        $tenant->forceFill([
            'status' => 'active',
            'onboarding_completed_at' => now(),
        ])->save();
        $this->audit->log('tenant.activated', $tenant, [], $tenant->id);

        return $tenant->fresh();
    }

    public function membership(User $user, Tenant $tenant, string $portal, string $roleCode, array $extra = []): Membership
    {
        $membership = Membership::query()->create(array_merge([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'portal' => $portal,
            'status' => 'active',
        ], $extra));

        $role = Role::query()->whereNull('tenant_id')->where('code', $roleCode)->firstOrFail();
        $membership->roles()->sync([$role->id]);

        return $membership->load('roles.permissions');
    }

    private function sendVerification(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        VerificationCode::query()->create([
            'user_id' => $user->id,
            'purpose' => 'onboarding',
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(30),
        ]);

        Mail::raw("Your KAVACH verification code is {$code}. It expires in 30 minutes.", function ($message) use ($user) {
            $message->to($user->email)->subject('KAVACH verification code');
        });
    }

    private function seedTenantDefaults(Tenant $tenant): void
    {
        foreach (['Theft', 'Trespass', 'Safety hazard', 'Other'] as $name) {
            IncidentCategory::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'default_severity' => 'medium',
            ]);
        }

        foreach ([['Casual', 'CL', true, 12], ['Sick', 'SL', true, 8], ['Unpaid', 'UL', false, null]] as [$name, $code, $paid, $quota]) {
            LeaveType::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'code' => $code,
                'paid' => $paid,
                'annual_quota' => $quota,
            ]);
        }
    }

    private function guardStep(Tenant $tenant, int $step): void
    {
        if ($tenant->status === 'suspended') {
            throw ValidationException::withMessages(['tenant' => __('tenancy.suspended')]);
        }
        if ($tenant->onboarding_completed_at) {
            return;
        }
        if ($tenant->onboarding_step < $step) {
            throw ValidationException::withMessages([
                'onboarding' => __('onboarding.step_locked'),
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        $i = 1;
        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSettings(string $type): array
    {
        return [
            'client_workflow_enabled' => $type === 'agency',
            'attendance_grace_minutes' => 15,
            'require_supervisor_verification' => true,
            'require_location' => false,
            'patrol_qr_enabled' => true,
        ];
    }
}
