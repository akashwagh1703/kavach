<?php

declare(strict_types=1);

namespace App\Domains\Patrol\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Checkpoint extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'patrol_plan_id', 'name', 'code', 'sequence', 'token_hash'];

    protected $hidden = ['token_hash'];
}
