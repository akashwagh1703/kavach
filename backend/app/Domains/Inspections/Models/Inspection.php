<?php

declare(strict_types=1);

namespace App\Domains\Inspections\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'template_id', 'site_id', 'inspector_employee_id', 'status', 'scheduled_for', 'completed_at', 'notes'];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
