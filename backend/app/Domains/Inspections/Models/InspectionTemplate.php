<?php

declare(strict_types=1);

namespace App\Domains\Inspections\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InspectionTemplate extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'name', 'items'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
