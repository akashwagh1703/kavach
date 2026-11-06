<?php

declare(strict_types=1);

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    public static function data(mixed $data, array $meta = [], ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'message' => $message,
        ], $status);
    }

    public static function resource(JsonResource $resource, ?string $message = null, int $status = 200): JsonResponse
    {
        return self::data($resource->resolve(request()), [], $message, $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, callable $transform, ?string $message = null): JsonResponse
    {
        return self::data(
            $transform($paginator->getCollection()),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
            $message,
        );
    }
}
