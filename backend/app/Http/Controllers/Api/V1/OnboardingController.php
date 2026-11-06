<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Services\AuthService;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\OnboardingService;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class OnboardingController extends Controller
{
    use Responds;

    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly AuthService $auth,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $created = $this->onboarding->register($data['name'], $data['email'], $data['password'], $data['phone'] ?? null);
        $this->auth->login($request, $data['email'], $data['password'], 'tenant');

        return ApiResponse::data([
            'session' => $this->sessionPayload($created['user'], $created['membership']),
            'onboarding' => $this->onboarding->state($created['tenant']),
        ], [], null, 201);
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data($this->onboarding->state($this->tenant()));
    }

    public function businessType(Request $request): JsonResponse
    {
        $data = $request->validate(['business_type' => ['required', 'in:agency,inhouse']]);

        return ApiResponse::data($this->onboarding->state($this->onboarding->saveBusinessType($this->tenant(), $data['business_type'])));
    }

    public function organization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        return ApiResponse::data($this->onboarding->state($this->onboarding->saveOrganization($this->tenant(), $data)));
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        return ApiResponse::data($this->onboarding->state($this->onboarding->verifyEmail($request->user(), $this->tenant(), $data['code'])));
    }

    public function resend(Request $request): JsonResponse
    {
        $this->onboarding->resendVerification($request->user());

        return ApiResponse::data(null, [], __('onboarding.code_sent'));
    }

    public function profile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
        ]);

        return ApiResponse::data($this->onboarding->state($this->onboarding->saveProfile($this->tenant(), $data)));
    }

    public function branch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'skip' => ['sometimes', 'boolean'],
            'name' => ['required_unless:skip,true', 'string', 'max:160'],
            'code' => ['required_unless:skip,true', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);
        $skip = (bool) ($data['skip'] ?? false);

        return ApiResponse::data($this->onboarding->state($this->onboarding->saveBranch($this->tenant(), $skip ? null : $data, $skip)));
    }

    public function invites(Request $request): JsonResponse
    {
        return ApiResponse::data($this->onboarding->state($this->onboarding->skipInvites($this->tenant())));
    }

    public function workforceSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attendance_grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'require_supervisor_verification' => ['required', 'boolean'],
            'require_location' => ['required', 'boolean'],
            'patrol_qr_enabled' => ['required', 'boolean'],
        ]);

        return ApiResponse::data($this->onboarding->state($this->onboarding->saveWorkforceSettings($this->tenant(), $data)));
    }

    public function plan(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', 'string']]);

        return ApiResponse::data($this->onboarding->state($this->onboarding->selectPlan($this->tenant(), $data['plan'])));
    }

    public function complete(): JsonResponse
    {
        return ApiResponse::data($this->onboarding->state($this->onboarding->complete($this->tenant())));
    }

    private function tenant(): Tenant
    {
        return Tenant::query()->findOrFail(app(TenantContext::class)->tenantId);
    }
}
