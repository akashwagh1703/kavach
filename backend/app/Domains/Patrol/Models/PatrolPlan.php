<?php

declare(strict_types=1);

namespace App\Domains\Patrol\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PatrolPlan extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'site_id', 'name', 'status'];
}
