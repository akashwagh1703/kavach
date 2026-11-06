<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Identity\Models\Membership;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Workforce\Models\EmployeeDocument;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

class ScanDocumentExpiry extends Command
{
    protected $signature = 'kavach:scan-document-expiry';

    protected $description = 'Notify workforce managers about documents expiring within 30 days';

    public function handle(NotificationService $notifications): int
    {
        $documents = EmployeeDocument::query()
            ->withoutGlobalScopes()
            ->whereNotNull('expires_on')
            ->whereBetween('expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->get();

        $sent = 0;
        foreach ($documents as $document) {
            $context = app(TenantContext::class);
            $context->activate($document->tenant_id);
            $recipients = Membership::query()
                ->where('tenant_id', $document->tenant_id)
                ->where('status', 'active')
                ->whereHas('roles.permissions', fn ($query) => $query->where('code', 'workforce.documents.view'))
                ->pluck('user_id');
            foreach ($recipients as $userId) {
                $exists = \App\Domains\Notifications\Models\AppNotification::query()
                    ->where('user_id', $userId)
                    ->where('category', 'document_expiry')
                    ->where('data->document_id', $document->id)
                    ->exists();
                if ($exists) {
                    continue;
                }
                $notifications->notify(
                    $userId,
                    'document_expiry',
                    'Document expiring',
                    $document->title.' expires on '.$document->expires_on->toDateString(),
                    ['document_id' => $document->id],
                    $document->tenant_id,
                );
                $sent++;
            }
            $context->clear();
        }
        $this->info("Expiry notifications stored: {$sent}");

        return self::SUCCESS;
    }
}
