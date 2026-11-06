<?php

$root = dirname(__DIR__).'/app/Domains';

$models = [
    'Tenancy/Models/Tenant.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Models;

use App\Domains\Organization\Models\Branch;
use App\Domains\Platform\Models\Subscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 'legal_name', 'slug', 'business_type', 'status', 'onboarding_step',
        'onboarding_completed_at', 'email', 'phone', 'timezone', 'city', 'state',
        'address', 'settings', 'branding',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'branding' => 'array',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function clientWorkflowEnabled(): bool
    {
        if ($this->business_type === 'inhouse') {
            return false;
        }

        return (bool) ($this->settings['client_workflow_enabled'] ?? true);
    }

    public function graceMinutes(): int
    {
        return (int) ($this->settings['attendance_grace_minutes'] ?? 15);
    }
}
PHP,
    'Identity/Models/User.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'locale', 'timezone', 'status',
        'must_reset_password', 'mfa_secret', 'mfa_enabled', 'mfa_confirmed_at', 'email_verified_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
            'must_reset_password' => 'boolean',
            'mfa_secret' => 'encrypted',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
PHP,
    'Identity/Models/Permission.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasUuids;

    protected $fillable = ['code', 'module', 'name'];
}
PHP,
    'Identity/Models/Role.php' => <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'portal', 'code', 'name', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }
}
PHP,
    'Identity/Models/Membership.php' => <<<'PHP'
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
PHP,
];

foreach ($models as $relative => $contents) {
    $path = $root.'/'.$relative;
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    file_put_contents($path, $contents);
    echo $relative.PHP_EOL;
}
