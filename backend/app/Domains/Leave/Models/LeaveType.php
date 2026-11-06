<?php

declare(strict_types=1);

namespace App\Domains\Leave\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'name', 'code', 'paid', 'annual_quota'];

    protected function casts(): array
    {
        return [
            'paid' => 'boolean',
        ];
    }
}
