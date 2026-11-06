<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? '';
$user = App\Domains\Identity\Models\User::query()->where('email', $email)->first();
$employee = App\Domains\Workforce\Models\Employee::query()->where('email', $email)->first();
$membership = $user
    ? App\Domains\Identity\Models\Membership::query()->where('user_id', $user->id)->get(['portal', 'status', 'employee_id'])
    : collect();

echo $user ? "user={$user->id} name={$user->name}\n" : "no-user\n";
echo $employee ? "employee={$employee->id} user_id=".($employee->user_id ?: 'null')." kind={$employee->kind}\n" : "no-employee\n";
foreach ($membership as $row) {
    echo "membership portal={$row->portal} status={$row->status} employee_id=".($row->employee_id ?: 'null')."\n";
}
