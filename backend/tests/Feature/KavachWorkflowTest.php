<?php

namespace Tests\Feature;

use App\Domains\Identity\Models\Membership;
use App\Domains\Identity\Models\User;
use App\Domains\Organization\Models\Site;
use App\Domains\Scheduling\Models\Roster;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workforce\Models\Employee;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KavachWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $password = 'Correct-Horse-1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_does_not_reveal_whether_the_portal_is_wrong(): void
    {
        $user = User::factory()->create(['password' => $this->password]);
        $this->withHeader('Origin', 'http://localhost:5175')
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => $this->password,
                'portal' => 'guard',
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
    }

    public function test_tenant_cannot_read_another_tenants_site(): void
    {
        [$ownerA] = $this->agency('Alpha Security');
        [, $beta] = $this->agency('Beta Security');
        $foreign = Site::query()->withoutGlobalScopes()->create([
            'tenant_id' => $beta->id,
            'name' => 'Beta Gate',
            'code' => 'BG',
            'status' => 'active',
        ]);

        $ids = collect($this->signIn($ownerA)->getJson('/api/v1/sites')->assertOk()->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_check_in_is_idempotent_and_rejected_without_a_shift(): void
    {
        [$owner, $tenant] = $this->agency('Field Agency');
        $guardUser = User::factory()->create(['password' => $this->password]);
        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $guardUser->id,
            'employee_code' => 'G-1',
            'kind' => 'guard',
            'name' => 'Ravi Patil',
            'status' => 'active',
        ]);
        $membership = Membership::query()->create([
            'user_id' => $guardUser->id,
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'portal' => 'guard',
            'status' => 'active',
        ]);
        $role = \App\Domains\Identity\Models\Role::query()->where('code', 'guard')->firstOrFail();
        $membership->roles()->sync([$role->id]);

        $this->signIn($membership)
            ->withHeader('Idempotency-Key', 'checkin-001')
            ->postJson('/api/v1/attendance/check-in', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.check_in.0', 'No published shift covers this time, so check-in was not recorded.');

        $site = Site::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Gate A',
            'code' => 'GA',
            'status' => 'active',
        ]);
        $roster = Roster::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'Week',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addWeek()->toDateString(),
            'status' => 'published',
            'published_at' => now(),
        ]);
        $shift = Shift::query()->create([
            'tenant_id' => $tenant->id,
            'roster_id' => $roster->id,
            'site_id' => $site->id,
            'name' => 'Day',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(7),
            'required_guards' => 1,
            'status' => 'scheduled',
        ]);
        $shift->assignments()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'role' => 'guard',
            'status' => 'assigned',
        ]);

        $first = $this->signIn($membership)
            ->withHeader('Idempotency-Key', 'checkin-002')
            ->postJson('/api/v1/attendance/check-in', [])
            ->assertOk()
            ->json('data.id');

        $second = $this->signIn($membership)
            ->withHeader('Idempotency-Key', 'checkin-002')
            ->postJson('/api/v1/attendance/check-in', [])
            ->assertOk()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, \App\Domains\Attendance\Models\PunchEvent::query()->count());
    }

    public function test_missing_punch_is_an_exception_not_an_absence(): void
    {
        [, $tenant] = $this->agency('Night Watch');
        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'employee_code' => 'G-9',
            'kind' => 'guard',
            'name' => 'Missing Guard',
            'status' => 'active',
        ]);
        $site = Site::query()->create(['tenant_id' => $tenant->id, 'name' => 'Dock', 'code' => 'DK', 'status' => 'active']);
        $roster = Roster::query()->create([
            'tenant_id' => $tenant->id,
            'site_id' => $site->id,
            'name' => 'Done',
            'period_start' => now()->subDay()->toDateString(),
            'period_end' => now()->toDateString(),
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        $shift = Shift::query()->create([
            'tenant_id' => $tenant->id,
            'roster_id' => $roster->id,
            'site_id' => $site->id,
            'name' => 'Night',
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->subHour(),
            'status' => 'scheduled',
        ]);
        $shift->assignments()->create([
            'tenant_id' => $tenant->id,
            'employee_id' => $employee->id,
            'role' => 'guard',
            'status' => 'assigned',
        ]);

        $this->artisan('kavach:scan-missing-punches')->assertSuccessful();
        $day = \App\Domains\Attendance\Models\AttendanceDay::query()->first();
        $this->assertNotNull($day);
        $this->assertSame('exception', $day->status);
        $this->assertSame('missing_punch', $day->exception_type);
        $this->assertNull($day->check_in_at);
    }

    public function test_platform_admin_cannot_list_tenant_sites_without_a_grant(): void
    {
        [, $tenant] = $this->agency('Guarded Org');
        Site::query()->create(['tenant_id' => $tenant->id, 'name' => 'HQ', 'code' => 'HQ', 'status' => 'active']);
        $admin = User::factory()->create(['password' => $this->password, 'mfa_enabled' => true]);
        $membership = Membership::query()->create([
            'user_id' => $admin->id,
            'portal' => 'platform',
            'status' => 'active',
        ]);
        $role = \App\Domains\Identity\Models\Role::query()->where('code', 'platform_super_admin')->firstOrFail();
        $membership->roles()->sync([$role->id]);

        $this->signIn($membership)
            ->getJson('/api/v1/platform/tenants/'.$tenant->id.'/support/sites')
            ->assertStatus(403);
    }

    public function test_onboarding_does_not_create_a_second_tenant_for_the_same_email(): void
    {
        $this->withHeader('Origin', 'http://localhost:5175')
            ->postJson('/api/v1/onboarding/register', [
                'name' => 'Asha Kulkarni',
                'email' => 'asha@example.com',
                'password' => $this->password,
                'password_confirmation' => $this->password,
            ])->assertCreated();

        $this->postJson('/api/v1/onboarding/register', [
            'name' => 'Asha Kulkarni',
            'email' => 'asha@example.com',
            'password' => $this->password,
            'password_confirmation' => $this->password,
        ])->assertStatus(422);

        $this->assertSame(1, Tenant::query()->count());
    }

    public function test_inhouse_organization_cannot_create_clients(): void
    {
        [$owner] = $this->agency('Internal Security', 'inhouse');
        $this->signIn($owner)
            ->postJson('/api/v1/clients', ['name' => 'Outside Co', 'code' => 'OUT'])
            ->assertStatus(422);
    }

    public function test_payslip_rejects_totals_that_do_not_balance(): void
    {
        [$owner, $tenant] = $this->agency('Payroll Org', 'agency', 'enterprise');
        $employee = Employee::query()->create([
            'tenant_id' => $tenant->id,
            'employee_code' => 'E-1',
            'kind' => 'staff',
            'name' => 'Meera',
            'status' => 'active',
        ]);

        $this->signIn($owner)->postJson('/api/v1/payslips', [
            'employee_id' => $employee->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'gross' => '10000.00',
            'deductions' => '500.00',
            'net' => '10000.00',
            'components' => ['basic' => '10000.00'],
        ])->assertStatus(422)->assertJsonPath('errors.net.0', 'Net pay must equal gross minus deductions. Payslips are not calculated from attendance.');
    }

    /**
     * @return array{0: Membership, 1: Tenant}
     */
    private function agency(string $name, string $type = 'agency', string $planCode = 'enterprise'): array
    {
        $user = User::factory()->create(['password' => $this->password, 'mfa_enabled' => true]);
        $tenant = Tenant::query()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(4)),
            'business_type' => $type,
            'status' => 'active',
            'onboarding_step' => 10,
            'onboarding_completed_at' => now(),
            'settings' => [
                'client_workflow_enabled' => $type === 'agency',
                'attendance_grace_minutes' => 15,
                'require_location' => false,
            ],
        ]);
        $plan = \App\Domains\Platform\Models\Plan::query()->where('code', $planCode)->firstOrFail();
        \App\Domains\Platform\Models\Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $membership = Membership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'portal' => 'tenant',
            'status' => 'active',
        ]);
        $role = \App\Domains\Identity\Models\Role::query()->where('code', 'organization_owner')->firstOrFail();
        $membership->roles()->sync([$role->id]);
        $membership->setRelation('user', $user);

        return [$membership, $tenant];
    }

    private function signIn(Membership $membership): static
    {
        $membership->loadMissing('user');

        return $this->actingAs($membership->user)
            ->withSession([
                'kavach.portal' => $membership->portal,
                'kavach.membership_id' => $membership->id,
            ])
            ->withHeader('Origin', 'http://localhost:5175');
    }
}
