<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Platform\Models\ContactLead;
use App\Domains\Platform\Models\Plan;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = Plan::query()
            ->with('features')
            ->where('is_public', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan) => [
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'trial_days' => $plan->trial_days,
                'features' => $plan->features->pluck('name'),
            ]);

        return ApiResponse::data($plans);
    }

    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'organization' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:2000'],
        ]);
        ContactLead::query()->create($data);

        return ApiResponse::data(null, [], __('public.contact_received'), 201);
    }

    public function health(): JsonResponse
    {
        $database = 'ok';
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $database = 'error';
        }

        return ApiResponse::data([
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'database' => $database,
            'queue' => config('queue.default'),
        ]);
    }
}
