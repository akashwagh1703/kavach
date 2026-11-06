<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'user_id', 'branch_id', 'employee_code', 'kind', 'name', 'phone', 'email', 'designation', 'status', 'date_of_joining', 'address'];

    protected function casts(): array
    {
        return [
            'date_of_joining' => 'date',
        ];
    }
}
