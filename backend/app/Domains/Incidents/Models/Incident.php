<?php

declare(strict_types=1);

namespace App\Domains\Incidents\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'site_id', 'category_id', 'reported_by', 'assigned_to_employee_id', 'title', 'description', 'priority', 'status', 'occurred_at', 'resolved_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
