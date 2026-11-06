<?php

declare(strict_types=1);

namespace App\Domains\Emergency\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SosEvent extends Model
{
    use HasUuids;

    protected $fillable = ['sos_alert_id', 'user_id', 'type', 'body'];
}
