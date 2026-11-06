<?php

declare(strict_types=1);

namespace App\Domains\Scheduling\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Roster extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'site_id', 'name', 'period_start', 'period_end', 'status', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'published_at' => 'datetime',
        ];
    }
}
