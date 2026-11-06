<?php

declare(strict_types=1);

namespace App\Domains\Inspections\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CorrectiveAction extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'inspection_id', 'description', 'status', 'due_on'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }
}
