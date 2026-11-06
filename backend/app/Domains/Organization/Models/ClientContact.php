<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'client_id', 'name', 'email', 'phone', 'designation', 'is_primary'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
