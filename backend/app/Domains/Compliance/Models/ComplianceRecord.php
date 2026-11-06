<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ComplianceRecord extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'subject_type', 'subject_id', 'title', 'status', 'due_on', 'notes'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
        ];
    }
}
