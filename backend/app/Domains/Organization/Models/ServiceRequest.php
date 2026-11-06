<?php

declare(strict_types=1);

namespace App\Domains\Organization\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'client_id', 'site_id', 'user_id', 'subject', 'body', 'status'];
}
