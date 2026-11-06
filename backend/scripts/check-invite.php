<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$token = $argv[1] ?? '';
$inv = App\Domains\Identity\Models\Invitation::query()
    ->where('token_hash', hash('sha256', $token))
    ->first();

if (! $inv) {
    echo "missing\n";
    exit(1);
}

echo "portal={$inv->portal} email={$inv->email} employee={$inv->employee_id} accepted=".($inv->accepted_at ?: 'no')."\n";
