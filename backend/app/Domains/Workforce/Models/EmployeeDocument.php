<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use BelongsToTenant, HasUuids;

    protected $hidden = ['disk', 'path'];

    protected $fillable = ['tenant_id', 'employee_id', 'document_type', 'title', 'disk', 'path', 'original_name', 'mime', 'size', 'expires_on', 'verified_at'];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'verified_at' => 'datetime',
        ];
    }
}
