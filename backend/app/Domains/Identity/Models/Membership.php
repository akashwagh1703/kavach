<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Organization\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Workforce\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Membership extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'tenant_id', 'client_id', 'employee_id', 'portal', 'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_role');
    }

    public function hasPermission(string $code): bool
    {
        foreach ($this->roles as $role) {
            foreach ($role->permissions as $permission) {
                if ($permission->code === $code) {
                    return true;
                }
            }
        }

        return false;
    }
}