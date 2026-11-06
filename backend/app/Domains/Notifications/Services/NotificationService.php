<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Models\AppNotification;
use App\Support\Tenancy\TenantContext;

class NotificationService
{
    public function notify(
        string $userId,
        string $category,
        string $title,
        string $body,
        array $data = [],
        ?string $tenantId = null,
    ): AppNotification {
        return AppNotification::query()->create([
            'tenant_id' => $tenantId ?? app(TenantContext::class)->tenantId,
            'user_id' => $userId,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
            'delivery_status' => 'stored',
        ]);
    }
}
