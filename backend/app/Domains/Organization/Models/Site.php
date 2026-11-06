<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'client_id', 'branch_id', 'name', 'code', 'address', 'city', 'state', 'latitude', 'longitude', 'instructions', 'requirements', 'status', 'shift_requirements'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'shift_requirements' => 'array',
        ];
    }
}
