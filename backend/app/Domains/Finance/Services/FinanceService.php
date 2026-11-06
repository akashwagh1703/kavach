<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Models\Invoice;
use App\Domains\Finance\Models\InvoiceItem;
use App\Domains\Finance\Models\Payslip;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<array{description: string, quantity: float|int|string, unit_price: float|int|string}>  $items
     */
    public function createInvoice(array $header, array $items, string $tax): Invoice
    {
        return DB::transaction(function () use ($header, $items, $tax) {
            $subtotal = '0.00';
            $computed = [];
            foreach ($items as $item) {
                $amount = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $subtotal = bcadd($subtotal, $amount, 2);
                $computed[] = [
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => $amount,
                ];
            }
            $total = bcadd($subtotal, $tax, 2);
            $invoice = Invoice::query()->create([
                ...$header,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
            ]);
            foreach ($computed as $item) {
                InvoiceItem::query()->create(['invoice_id' => $invoice->id, ...$item]);
            }
            $this->audit->log('invoice.created', $invoice, ['total' => $total]);

            return $invoice->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $components
     */
    public function createPayslip(array $attributes, array $components): Payslip
    {
        $net = bcsub((string) $attributes['gross'], (string) $attributes['deductions'], 2);
        if (bccomp($net, (string) $attributes['net'], 2) !== 0) {
            throw ValidationException::withMessages([
                'net' => __('finance.net_mismatch'),
            ]);
        }

        $payslip = Payslip::query()->create([
            ...$attributes,
            'components' => $components,
            'status' => 'draft',
        ]);
        $this->audit->log('payslip.created', $payslip);

        return $payslip;
    }
}
