<?php

declare(strict_types=1);

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Models\LoginAttempt;
use App\Domains\Identity\Models\Membership;
use App\Domains\Identity\Models\MfaChallenge;
use App\Domains\Identity\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @return array{mfa_required: bool, challenge?: string, user?: User, membership?: Membership}
     */
    public function login(Request $request, string $email, string $password, string $portal): array
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();
        $passwordMatches = Hash::check($password, $user->password ?? $this->dummyHash());

        if (! $user || ! $passwordMatches || $user->status !== 'active') {
            $this->attempt($email, $portal, $request, false, $user ? 'rejected' : 'unknown');
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $membership = Membership::query()
            ->with(['roles.permissions', 'tenant'])
            ->where('user_id', $user->id)
            ->where('portal', $portal)
            ->where('status', 'active')
            ->first();

        if (! $membership || ($membership->tenant && $membership->tenant->status === 'suspended')) {
            $this->attempt($email, $portal, $request, false, 'portal');
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if ($user->mfa_enabled) {
            $challenge = $this->issueChallenge($user, $membership);
            $this->attempt($email, $portal, $request, false, 'mfa_required');

            return [
                'mfa_required' => true,
                'challenge' => $challenge,
            ];
        }

        $this->startSession($request, $user, $membership);
        $this->attempt($email, $portal, $request, true, null);
        $this->audit->log('auth.login', $user, ['portal' => $portal], $membership->tenant_id);

        return [
            'mfa_required' => false,
            'user' => $user,
            'membership' => $membership,
        ];
    }

    public function verifyMfa(Request $request, string $challengeToken, string $code): array
    {
        $challenge = MfaChallenge::query()
            ->where('token_hash', hash('sha256', $challengeToken))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        $user = $challenge ? User::query()->find($challenge->user_id) : null;
        $secret = $user?->mfa_secret;
        $valid = $secret && (new Google2FA())->verifyKey($secret, $code);

        if (! $challenge || ! $user || ! $valid) {
            throw ValidationException::withMessages([
                'code' => __('auth.mfa_failed'),
            ]);
        }

        $membership = Membership::query()->with(['roles.permissions', 'tenant'])->find($challenge->membership_id);
        if (! $membership || $membership->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'code' => __('auth.mfa_failed'),
            ]);
        }

        $challenge->forceFill(['consumed_at' => now()])->save();
        $this->startSession($request, $user, $membership);
        $this->attempt($user->email, $membership->portal, $request, true, null);
        $this->audit->log('auth.login', $user, ['portal' => $membership->portal, 'mfa' => true], $membership->tenant_id);

        return ['user' => $user, 'membership' => $membership];
    }

    /**
     * @return array{secret: string, otpauth: string}
     */
    public function enrollMfa(User $user): array
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();
        $user->forceFill([
            'mfa_secret' => $secret,
            'mfa_enabled' => false,
            'mfa_confirmed_at' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth' => $google2fa->getQRCodeUrl('KAVACH', $user->email, $secret),
        ];
    }

    public function confirmMfa(User $user, string $code): void
    {
        if (! $user->mfa_secret || ! (new Google2FA())->verifyKey($user->mfa_secret, $code)) {
            throw ValidationException::withMessages([
                'code' => __('auth.mfa_failed'),
            ]);
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_confirmed_at' => now(),
        ])->save();
        $this->audit->log('auth.mfa_enabled', $user);
    }

    public function logout(Request $request): void
    {
        $this->audit->log('auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function startSession(Request $request, User $user, Membership $membership): void
    {
        if (! $request->hasSession()) {
            $request->setLaravelSession(app('session.store'));
        }
        if (! $request->session()->isStarted()) {
            $request->session()->start();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('kavach.portal', $membership->portal);
        $request->session()->put('kavach.membership_id', $membership->id);
    }

    private function issueChallenge(User $user, Membership $membership): string
    {
        $token = Str::random(64);
        MfaChallenge::query()->create([
            'user_id' => $user->id,
            'portal' => $membership->portal,
            'membership_id' => $membership->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $token;
    }

    private function attempt(string $email, string $portal, Request $request, bool $successful, ?string $reason): void
    {
        LoginAttempt::query()->create([
            'email' => $email,
            'portal' => $portal,
            'ip' => $request->ip(),
            'successful' => $successful,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(Str::random(32));
    }
}
