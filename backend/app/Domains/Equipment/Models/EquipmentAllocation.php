<?php

declare(strict_types=1);

namespace App\Domains\Equipment\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EquipmentAllocation extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'equipment_id', 'employee_id', 'allocated_at', 'returned_at', 'notes'];

    protected function casts(): array
    {
        return [
            'allocated_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
