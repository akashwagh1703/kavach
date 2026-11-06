<?php

declare(strict_types=1);

namespace App\Domains\Platform\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactLead extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'email', 'phone', 'organization', 'message'];
}
