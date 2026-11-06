<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Models\Invitation;
use App\Domains\Identity\Models\Membership;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\AuthService;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tenancy\Services\OnboardingService;
use App\Domains\Workforce\Models\Employee;
use App\Http\Controllers\Api\Concerns\Responds;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use Responds;

    public function __construct(private readonly AuthService $auth) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'portal' => ['required', 'in:platform,tenant,client,guard,supervisor'],
        ]);

        $result = $this->auth->login($request, $data['email'], $data['password'], $data['portal']);
        if ($result['mfa_required']) {
            return ApiResponse::data([
                'mfa_required' => true,
                'challenge' => $result['challenge'],
            ], [], __('auth.mfa_required'));
        }

        return ApiResponse::data($this->sessionPayload($result['user'], $result['membership']));
    }

    public function verifyMfa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        $result = $this->auth->verifyMfa($request, $data['challenge'], $data['code']);

        return ApiResponse::data($this->sessionPayload($result['user'], $result['membership']));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get('membership');

        return ApiResponse::data($this->sessionPayload($request->user(), $membership));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request);

        return ApiResponse::data(null, [], __('auth.logged_out'));
    }

    public function enrollMfa(Request $request): JsonResponse
    {
        return ApiResponse::data($this->auth->enrollMfa($request->user()));
    }

    public function confirmMfa(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $this->auth->confirmMfa($request->user(), $data['code']);

        return ApiResponse::data(['mfa_enabled' => true], [], __('auth.mfa_enabled'));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get('membership');
        $locales = in_array($membership->portal, ['guard', 'supervisor'], true) ? ['en', 'hi', 'mr'] : ['en'];
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'locale' => ['sometimes', 'in:'.implode(',', $locales)],
            'timezone' => ['sometimes', 'timezone'],
        ]);
        $request->user()->fill($data)->save();

        return ApiResponse::data($this->sessionPayload($request->user()->fresh(), $membership));
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::broker()->sendResetLink(['email' => $data['email']]);

        return ApiResponse::data(null, [], __('auth.reset_sent'));
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()],
        ]);
        $status = Password::broker()->reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'must_reset_password' => false,
                ])->save();
            }
        );
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __('auth.reset_invalid')]);
        }

        return ApiResponse::data(null, [], __('auth.reset_complete'));
    }

    public function acceptInvitation(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $data['token']))
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages(['token' => __('auth.invitation_invalid')]);
        }

        $membership = DB::transaction(function () use ($invitation, $data, $onboarding) {
            $user = User::query()->where('email', $invitation->email)->first();
            if (! $user) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                    'status' => 'active',
                    'locale' => 'en',
                ]);
            } else {
                $user->forceFill(['password' => $data['password'], 'name' => $data['name'], 'status' => 'active'])->save();
            }

            if ($invitation->portal === 'platform') {
                $membership = Membership::query()->create([
                    'user_id' => $user->id,
                    'tenant_id' => null,
                    'portal' => 'platform',
                    'status' => 'active',
                ]);
                $role = \App\Domains\Identity\Models\Role::query()->whereNull('tenant_id')->where('code', $invitation->role_code)->firstOrFail();
                $membership->roles()->sync([$role->id]);
            } else {
                $tenant = Tenant::query()->findOrFail($invitation->tenant_id);
                $membership = $onboarding->membership($user, $tenant, $invitation->portal, $invitation->role_code, [
                    'client_id' => $invitation->client_id,
                    'employee_id' => $invitation->employee_id,
                ]);
            }
            if ($invitation->employee_id) {
                Employee::query()->whereKey($invitation->employee_id)->update(['user_id' => $user->id]);
            }
            foreach ($invitation->site_ids ?? [] as $siteId) {
                DB::table('client_site_access')->insert([
                    'membership_id' => $membership->id,
                    'site_id' => $siteId,
                ]);
            }
            $invitation->forceFill(['accepted_at' => now()])->save();

            return $membership->load('user');
        });

        $result = $this->auth->login($request, $invitation->email, $data['password'], $invitation->portal);
        if ($result['mfa_required'] ?? false) {
            return ApiResponse::data([
                'mfa_required' => true,
                'challenge' => $result['challenge'],
            ], [], null, 201);
        }

        return ApiResponse::data($this->sessionPayload($result['user'], $result['membership']), [], null, 201);
    }
}
