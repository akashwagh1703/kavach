<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'email', 'name', 'portal', 'role_code', 'client_id', 'employee_id', 'site_ids', 'token_hash', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'site_ids' => 'array',
        ];
    }
}
