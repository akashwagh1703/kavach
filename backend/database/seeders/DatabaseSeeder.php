<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Catalog\AccessCatalog;
use App\Domains\Identity\Models\Permission;
use App\Domains\Identity\Models\Role;
use App\Domains\Platform\Models\Feature;
use App\Domains\Platform\Models\Plan;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect(AccessCatalog::permissions())->map(function (array $meta, string $code) {
            return Permission::query()->updateOrCreate(['code' => $code], $meta);
        });

        foreach (AccessCatalog::systemRoles() as $code => $role) {
            $model = Role::query()->updateOrCreate(
                ['code' => $code, 'tenant_id' => null],
                ['portal' => $role['portal'], 'name' => $role['name'], 'is_system' => true],
            );
            $model->permissions()->sync($permissions->only($role['permissions'])->pluck('id')->all());
        }

        $features = collect(AccessCatalog::features())->map(function (array $meta, string $code) {
            return Feature::query()->updateOrCreate(['code' => $code], $meta);
        });

        $plans = [
            'starter' => ['Starter', 'Workforce, sites, scheduling and attendance for a single operation.', 4999, 49990, 1],
            'professional' => ['Professional', 'Field operations, incidents, patrol and the client portal.', 9999, 99990, 2],
            'enterprise' => ['Enterprise', 'The full KAVACH operations, finance and compliance workspace.', 19999, 199990, 3],
        ];

        foreach ($plans as $code => [$name, $description, $monthly, $yearly, $sort]) {
            $plan = Plan::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'description' => $description,
                'price_monthly' => $monthly,
                'price_yearly' => $yearly,
                'trial_days' => 14,
                'is_public' => true,
                'is_active' => true,
                'sort_order' => $sort,
            ]);
            $plan->features()->sync($features->only(AccessCatalog::planFeatures()[$code])->pluck('id')->all());
        }
    }
}
