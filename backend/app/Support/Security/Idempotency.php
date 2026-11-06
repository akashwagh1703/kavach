<?php

declare(strict_types=1);

namespace App\Support\Security;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class Idempotency
{
    public static function key(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (! preg_match('/^[A-Za-z0-9_:\-]{8,80}$/', $key)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('operations.idempotency_required'),
            ]);
        }

        return $key;
    }
}
