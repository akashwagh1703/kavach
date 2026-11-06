<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'recorded_on', 'employees', 'sites', 'active_guards'];

    protected function casts(): array
    {
        return ['recorded_on' => 'date'];
    }
}
