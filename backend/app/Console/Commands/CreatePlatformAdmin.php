<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Models\Membership;
use App\Domains\Identity\Models\Role;
use App\Domains\Identity\Models\User;
use Illuminate\Console\Command;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'kavach:create-platform-admin';

    protected $description = 'Create a platform super admin from KAVACH_PLATFORM_ADMIN_EMAIL and KAVACH_PLATFORM_ADMIN_PASSWORD';

    public function handle(): int
    {
        $email = (string) env('KAVACH_PLATFORM_ADMIN_EMAIL');
        $password = (string) env('KAVACH_PLATFORM_ADMIN_PASSWORD');
        if ($email === '' || $password === '') {
            $this->error('Set KAVACH_PLATFORM_ADMIN_EMAIL and KAVACH_PLATFORM_ADMIN_PASSWORD in the environment. Do not commit them.');

            return self::FAILURE;
        }

        $user = User::query()->firstOrNew(['email' => strtolower($email)]);
        $user->fill([
            'name' => 'Platform Admin',
            'password' => $password,
            'status' => 'active',
            'locale' => 'en',
            'email_verified_at' => now(),
        ])->save();

        $membership = Membership::query()->firstOrCreate(
            ['user_id' => $user->id, 'portal' => 'platform', 'tenant_id' => null],
            ['status' => 'active'],
        );
        $role = Role::query()->whereNull('tenant_id')->where('code', 'platform_super_admin')->first();
        if (! $role) {
            $this->error('Run php artisan db:seed before creating a platform admin.');

            return self::FAILURE;
        }
        $membership->roles()->sync([$role->id]);
        $this->info('Platform admin is ready for '.$user->email);

        return self::SUCCESS;
    }
}
