<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'invoice_id', 'amount', 'paid_on', 'method', 'reference', 'notes'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }
}
