<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClientType;
use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class PhoneAuthService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly WhatsAppOtpService $whatsappOtp,
    ) {}

    /**
     * Begin phone login: request an OTP via WhatsApp (otp.kztusdt.kz).
     */
    public function start(
        string $phone,
        string $clientType = 'individual',
        ?string $iin = null,
        ?string $bin = null,
        ?string $companyName = null,
        ?string $ip = null,
    ): AuthSession {
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedIin = $iin !== null ? $this->normalizeIin($iin) : null;
        $normalizedBin = $bin !== null ? $this->normalizeBin($bin) : null;
        $companyName = $companyName !== null ? trim($companyName) : null;

        if ($clientType === ClientType::LegalEntity->value && config('ncanode.legal_entity_eds_required')) {
            throw new RuntimeException('Для юр. лица сначала подпишите заявку ЭЦП.');
        }

        if (! $this->whatsappOtp->isConfigured()) {
            throw new RuntimeException('Авторизация по номеру телефона временно недоступна.');
        }

        $recentAttempts = AuthSession::query()
            ->where('phone', $normalizedPhone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentAttempts >= config('otp.rate_limit_per_phone')) {
            throw new RuntimeException('Слишком много попыток. Попробуйте позже.');
        }

        AuthSession::query()
            ->where('phone', $normalizedPhone)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $loginCode = Str::upper(Str::random(32));
        $expiresIn = $this->whatsappOtp->send($normalizedPhone);

        $session = AuthSession::query()->create([
            'phone' => $normalizedPhone,
            'client_type' => $clientType,
            'iin' => $normalizedIin,
            'bin' => $normalizedBin,
            'company_name' => $companyName !== '' ? $companyName : null,
            'login_code' => $loginCode,
            'code_hash' => null,
            'gateway_request_id' => null,
            'code_attempts' => 0,
            'status' => 'pending',
            'expires_at' => now()->addSeconds($expiresIn),
        ]);

        $this->auditLogService->log(
            action: 'auth.phone.start',
            payload: ['phone' => $normalizedPhone],
            request: request(),
        );

        return $session;
    }

    /**
     * Создаёт сессию юр. лица до подписи ЭЦП (OTP не отправляется).
     */
    public function startPendingLegalEntity(
        string $phone,
        string $bin,
        string $companyName,
        ?string $ip = null,
    ): AuthSession {
        $normalizedPhone = $this->normalizePhone($phone);
        $normalizedBin = $this->normalizeBin($bin);
        $companyName = trim($companyName);

        $recentAttempts = AuthSession::query()
            ->where('phone', $normalizedPhone)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentAttempts >= config('otp.rate_limit_per_phone')) {
            throw new RuntimeException('Слишком много попыток. Попробуйте позже.');
        }

        AuthSession::query()
            ->where('phone', $normalizedPhone)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $loginCode = Str::upper(Str::random(32));
        $ttl = (int) config('ncanode.challenge_ttl_seconds', 600);

        $session = AuthSession::query()->create([
            'phone' => $normalizedPhone,
            'client_type' => ClientType::LegalEntity->value,
            'bin' => $normalizedBin,
            'company_name' => $companyName,
            'login_code' => $loginCode,
            'code_hash' => null,
            'gateway_request_id' => null,
            'code_attempts' => 0,
            'status' => 'pending',
            'expires_at' => now()->addSeconds($ttl),
        ]);

        $this->auditLogService->log(
            action: 'auth.phone.legal.pending',
            payload: ['phone' => $normalizedPhone, 'bin' => $normalizedBin],
            request: request(),
        );

        return $session;
    }

    public function sendOtpForSession(AuthSession $session): AuthSession
    {
        if (! $this->whatsappOtp->isConfigured()) {
            throw new RuntimeException('Авторизация по номеру телефона временно недоступна.');
        }

        if ($session->requiresEds() && ! $session->hasEdsVerified()) {
            throw new RuntimeException('Сначала подтвердите ЭЦП организации.');
        }

        $expiresIn = $this->whatsappOtp->send($session->phone);

        $session->update([
            'expires_at' => now()->addSeconds($expiresIn),
            'code_attempts' => 0,
            'status' => 'pending',
        ]);

        $this->auditLogService->log(
            action: 'auth.phone.start',
            payload: ['phone' => $session->phone],
            request: request(),
        );

        return $session->fresh();
    }

    public function resend(string $loginCode): AuthSession
    {
        $session = $this->findSessionForResend($loginCode);

        if ($session->requiresEds() && ! $session->hasEdsVerified()) {
            throw new RuntimeException('Сначала подтвердите ЭЦП организации.');
        }

        $expiresIn = $this->whatsappOtp->send($session->phone);

        $session->update([
            'expires_at' => now()->addSeconds($expiresIn),
            'code_attempts' => 0,
            'status' => 'pending',
        ]);

        $this->auditLogService->log(
            action: 'auth.phone.resend',
            payload: ['phone' => $session->phone],
            request: request(),
        );

        return $session->fresh();
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
     */
    public function verifyCode(string $loginCode, string $code): User
    {
        $maxAttempts = (int) config('otp.max_attempts');
        $session = $this->findActiveSession($loginCode);

        if ($session->requiresEds() && ! $session->hasEdsVerified()) {
            throw new RuntimeException('Сначала подтвердите ЭЦП организации.');
        }

        if ($session->code_attempts >= $maxAttempts) {
            $session->update(['status' => 'failed']);

            throw new RuntimeException('Превышено число попыток. Запросите новый код.');
        }

        try {
            $this->whatsappOtp->verify($session->phone, $code);
        } catch (RuntimeException $exception) {
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

            throw $exception;
        }

        $user = DB::transaction(function () use ($session): User {
            $locked = AuthSession::query()->whereKey($session->id)->lockForUpdate()->first();

            if ($locked === null || $locked->isVerified()) {
                throw new RuntimeException('Код уже использован.');
            }

            $user = User::query()->where('phone', $locked->phone)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $locked->company_name ?: ('User '.$locked->phone),
                    'email' => 'wa_'.Str::lower(Str::random(16)).'@exchange.local',
                    'password' => bcrypt(Str::random(32)),
                    'phone' => $locked->phone,
                    'client_type' => $locked->client_type ?: 'individual',
                    'iin' => $locked->iin,
                    'bin' => $locked->bin,
                    'company_name' => $locked->company_name,
                    'eds_verified_at' => $locked->eds_verified_at,
                    'eds_certificate_subject' => $locked->eds_certificate_subject,
                    'representative_iin' => $locked->eds_signer_iin,
                    'phone_verified' => true,
                    'phone_verified_at' => now(),
                    'kyc_status' => 'none',
                ]);
            } else {
                $attributes = [
                    'phone_verified' => true,
                    'phone_verified_at' => now(),
                ];

                if ($locked->iin !== null && $user->iin === null) {
                    $attributes['iin'] = $locked->iin;
                }

                if ($locked->bin !== null && $user->bin === null) {
                    $attributes['bin'] = $locked->bin;
                }

                if ($locked->company_name !== null && $user->company_name === null) {
                    $attributes['company_name'] = $locked->company_name;
                }

                if ($locked->client_type !== null && $user->client_type === 'individual' && $locked->client_type !== 'individual') {
                    $attributes['client_type'] = $locked->client_type;
                }

                if ($locked->eds_verified_at !== null && $user->eds_verified_at === null) {
                    $attributes['eds_verified_at'] = $locked->eds_verified_at;
                    $attributes['eds_certificate_subject'] = $locked->eds_certificate_subject;
                    $attributes['representative_iin'] = $locked->eds_signer_iin;
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

    public function normalizeBin(string $bin): string
    {
        return preg_replace('/\D+/', '', $bin) ?? '';
    }

    private function findActiveSession(string $loginCode): AuthSession
    {
        $session = $this->findSessionForResend($loginCode);

        if ($session->isExpired()) {
            $session->update(['status' => 'expired']);

            throw new RuntimeException('Код истёк. Запросите новый.');
        }

        return $session;
    }

    private function findSessionForResend(string $loginCode): AuthSession
    {
        $session = AuthSession::query()->where('login_code', $loginCode)->first();

        if ($session === null) {
            throw new RuntimeException('Сессия не найдена.');
        }

        if ($session->isVerified()) {
            throw new RuntimeException('Код уже использован.');
        }

        if ($session->status === 'failed') {
            throw new RuntimeException('Сессия заблокирована. Начните вход заново.');
        }

        return $session;
    }
}
