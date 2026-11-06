<?php

declare(strict_types=1);

namespace App\Domains\Inspections\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InspectionResponse extends Model
{
    use HasUuids;

    protected $fillable = ['inspection_id', 'item_key', 'result', 'notes'];
}
