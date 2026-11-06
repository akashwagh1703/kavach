<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Domains\Platform\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(string $action, ?Model $auditable = null, array $metadata = [], ?string $tenantId = null): void
    {
        $context = app(TenantContext::class);
        $request = Request::instance();

        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId ?? $context->tenantId,
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip' => $request->ip(),
            'request_id' => $request->attributes->get('request_id'),
        ]);
    }
}
