<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'category', 'amount', 'spent_on', 'description', 'status'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
        ];
    }
}
