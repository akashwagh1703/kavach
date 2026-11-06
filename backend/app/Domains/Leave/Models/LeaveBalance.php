<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveBalance extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'employee_id', 'leave_type_id', 'year', 'allocated', 'used'];

    protected function casts(): array
    {
        return [
            'allocated' => 'decimal:1',
            'used' => 'decimal:1',
        ];
    }
}
