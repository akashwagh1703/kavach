<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Shared\Models\Concerns\BelongsToTenant;
use App\Domains\Workforce\Models\Employee;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'employee_id', 'period_start', 'period_end', 'status', 'gross', 'deductions', 'net', 'components'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'gross' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net' => 'decimal:2',
            'components' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
