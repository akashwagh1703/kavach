<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TenantAccessDenied extends NotFoundHttpException
{
    public function __construct()
    {
        parent::__construct('The requested record was not found.');
    }
}
