<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShiftReplacement extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'assignment_id', 'replaced_employee_id', 'replacement_employee_id', 'replaced_by', 'reason'];
}
