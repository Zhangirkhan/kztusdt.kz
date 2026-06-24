<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

final class PhoneAuthService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly TelegramGatewayService $telegramGateway,
    ) {}

    /**
     * Begin phone login: generate an OTP and deliver it to the user's Telegram
     * account through the Telegram Gateway API.
     */
    public function start(string $phone, ?string $iin = null, ?string $ip = null): AuthSession
    {
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedIin = $iin !== null ? $this->normalizeIin($iin) : null;

        if (! $this->telegramGateway->isConfigured()) {
            throw new RuntimeException('Авторизация через Telegram временно недоступна.');
        }

        $recentAttempts = AuthSession::query()
            ->where('phone', $normalizedPhone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentAttempts >= config('telegram.rate_limit_per_phone')) {
            throw new RuntimeException('Слишком много попыток. Попробуйте позже.');
        }

        AuthSession::query()
            ->where('phone', $normalizedPhone)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $loginCode = Str::upper(Str::random(32));
        $code = $this->generateNumericCode((int) config('telegram.gateway.code_length'));

        $requestId = $this->telegramGateway->sendVerificationMessage(
            $normalizedPhone,
            $code,
            payload: $loginCode,
        );

        $session = AuthSession::query()->create([
            'phone' => $normalizedPhone,
            'iin' => $normalizedIin,
            'login_code' => $loginCode,
            'code_hash' => Hash::make($code),
            'gateway_request_id' => $requestId,
            'code_attempts' => 0,
            'status' => 'pending',
            'expires_at' => now()->addSeconds((int) config('telegram.gateway.code_ttl_seconds')),
        ]);

        $this->auditLogService->log(
            action: 'auth.phone.start',
            payload: ['phone' => $normalizedPhone],
            request: request(),
        );

        return $session;
    }

    public function getStatus(string $loginCode): ?AuthSession
    {
        $session = AuthSession::query()->where('login_code', $loginCode)->first();

        if ($session === null) {
            return null;
        }

        if ($session->isExpired() && ! $session->isVerified()) {
            $session->update(['status' => 'expired']);
        }

        return $session->fresh();
    }

    /**
     * Verify the OTP entered by the user and log them in.
     *
     * Failure-path writes (attempt counters, expiry, lockout) are committed
     * immediately and must not be wrapped in the success transaction, otherwise
     * a thrown exception would roll back the very counters we rely on.
     */
    public function verifyCode(string $loginCode, string $code): User
    {
        $maxAttempts = (int) config('telegram.gateway.max_attempts');

        $session = $this->findActiveSession($loginCode);

        if ($session->code_attempts >= $maxAttempts) {
            $session->update(['status' => 'failed']);

            throw new RuntimeException('Превышено число попыток. Запросите новый код.');
        }

        if ($session->code_hash === null || ! Hash::check($code, $session->code_hash)) {
            // Atomic DB-level increment so two concurrent wrong guesses can't both
            // read the same counter and lose an attempt (weakening the lockout).
            $session->increment('code_attempts');
            $session->refresh();
            $attempts = (int) $session->code_attempts;

            if ($attempts >= $maxAttempts && $session->status !== 'failed') {
                $session->update(['status' => 'failed']);
            }

            $this->auditLogService->log(
                action: 'auth.phone.code_invalid',
                payload: ['phone' => $session->phone, 'attempts' => $attempts],
                request: request(),
            );

            throw new RuntimeException('Неверный код. Попробуйте ещё раз.');
        }

        $user = DB::transaction(function () use ($session): User {
            $locked = AuthSession::query()->whereKey($session->id)->lockForUpdate()->first();

            if ($locked === null || $locked->isVerified()) {
                throw new RuntimeException('Код уже использован.');
            }

            $user = User::query()->where('phone', $locked->phone)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => 'User '.$locked->phone,
                    'email' => 'tg_'.Str::lower(Str::random(16)).'@exchange.local',
                    'password' => bcrypt(Str::random(32)),
                    'phone' => $locked->phone,
                    'iin' => $locked->iin,
                    'phone_verified' => true,
                    'phone_verified_at' => now(),
                    'kyc_status' => 'none',
                ]);
            } else {
                $attributes = [
                    'phone_verified' => true,
                    'phone_verified_at' => now(),
                ];

                // Backfill the IIN for returning users who registered before we
                // started collecting it; never overwrite an existing value.
                if ($locked->iin !== null && $user->iin === null) {
                    $attributes['iin'] = $locked->iin;
                }

                $user->update($attributes);
            }

            $locked->update([
                'status' => 'verified',
                'verified_at' => now(),
                'user_id' => $user->id,
                'code_hash' => null,
            ]);

            return $user;
        });

        $this->auditLogService->log(
            action: 'auth.phone.verified',
            userId: $user->id,
            entityType: 'auth_session',
            entityId: $session->id,
            payload: ['phone' => $session->phone],
            request: request(),
        );

        if ($session->gateway_request_id !== null) {
            $this->telegramGateway->reportVerificationStatus($session->gateway_request_id, $code);
        }

        return $user;
    }

    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    public function normalizeIin(string $iin): string
    {
        return preg_replace('/\D+/', '', $iin) ?? '';
    }

    private function generateNumericCode(int $length): string
    {
        $length = max(4, min(8, $length));

        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    private function findActiveSession(string $loginCode): AuthSession
    {
        // Read-only pre-checks; the authoritative claim (lock + isVerified recheck)
        // happens inside the success transaction in verifyCode().
        $session = AuthSession::query()->where('login_code', $loginCode)->first();

        if ($session === null) {
            throw new RuntimeException('Сессия не найдена.');
        }

        if ($session->isVerified()) {
            throw new RuntimeException('Код уже использован.');
        }

        if ($session->status === 'failed') {
            throw new RuntimeException('Сессия заблокирована. Запросите новый код.');
        }

        if ($session->isExpired()) {
            $session->update(['status' => 'expired']);

            throw new RuntimeException('Код истёк. Запросите новый.');
        }

        return $session;
    }
}
