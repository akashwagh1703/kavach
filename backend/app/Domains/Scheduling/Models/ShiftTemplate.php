<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShiftTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'name', 'start_time', 'end_time', 'crosses_midnight', 'grace_minutes'];

    protected function casts(): array
    {
        return [
            'crosses_midnight' => 'boolean',
        ];
    }
}
