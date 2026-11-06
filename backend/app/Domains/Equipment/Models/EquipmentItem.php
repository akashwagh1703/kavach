<?php

declare(strict_types=1);

namespace App\Domains\Equipment\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EquipmentItem extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'name', 'asset_code', 'status', 'notes'];

    protected $table = 'equipment';
}
