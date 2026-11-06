<?php

declare(strict_types=1);

namespace App\Domains\Incidents\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IncidentUpdate extends Model
{
    use HasUuids;

    protected $fillable = ['incident_id', 'user_id', 'body', 'status_after'];
}
