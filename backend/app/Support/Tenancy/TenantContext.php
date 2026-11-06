<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Domains\Identity\Models\Membership;
use App\Domains\Platform\Models\SupportAccessGrant;

class TenantContext
{
    public bool $active = false;

    public ?string $tenantId = null;

    public ?Membership $membership = null;

    public ?SupportAccessGrant $supportGrant = null;

    public function activate(?string $tenantId, ?Membership $membership = null, ?SupportAccessGrant $grant = null): void
    {
        $this->active = true;
        $this->tenantId = $tenantId;
        $this->membership = $membership;
        $this->supportGrant = $grant;
    }

    public function usePlatform(Membership $membership): void
    {
        $this->active = false;
        $this->tenantId = null;
        $this->membership = $membership;
        $this->supportGrant = null;
    }

    public function clear(): void
    {
        $this->active = false;
        $this->tenantId = null;
        $this->membership = null;
        $this->supportGrant = null;
    }
}
