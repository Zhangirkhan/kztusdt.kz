<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ClientType;
use App\Models\AuthSession;
use App\Support\NcaNode\NcaNodeClient;
use Illuminate\Support\Str;
use RuntimeException;

final class LegalEntityEdsRegistrationService
{
    public function __construct(
        private readonly NcaNodeClient $ncaNodeClient,
        private readonly AuditLogService $auditLogService,
        private readonly PhoneAuthService $phoneAuthService,
    ) {}

    /**
     * @return array{login_code: string, challenge: string, challenge_base64: string, expires_at: string}
     */
    public function startRegistration(
        string $phone,
        string $bin,
        string $companyName,
        ?string $ip = null,
    ): array {
        if (! config('ncanode.legal_entity_eds_required')) {
            throw new RuntimeException('Регистрация юр. лица через ЭЦП отключена.');
        }

        $normalizedPhone = $this->phoneAuthService->normalizePhone($phone);
        $normalizedBin = $this->phoneAuthService->normalizeBin($bin);
        $companyName = trim($companyName);

        if ($companyName === '') {
            throw new RuntimeException('Укажите наименование организации.');
        }

        $session = $this->phoneAuthService->startPendingLegalEntity(
            phone: $normalizedPhone,
            bin: $normalizedBin,
            companyName: $companyName,
            ip: $ip,
        );

        $challenge = $this->buildChallengePayload($session);
        $challengeJson = json_encode($challenge, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $challengeBase64 = base64_encode($challengeJson);

        $ttl = (int) config('ncanode.challenge_ttl_seconds');

        $session->update([
            'eds_challenge' => $challengeJson,
            'eds_challenge_expires_at' => now()->addSeconds($ttl),
        ]);

        $this->auditLogService->log(
            action: 'auth.legal_entity.eds.challenge_issued',
            entityType: 'auth_session',
            entityId: $session->id,
            payload: [
                'phone' => $normalizedPhone,
                'bin' => $normalizedBin,
            ],
            request: request(),
        );

        return [
            'login_code' => $session->login_code,
            'challenge' => $challengeJson,
            'challenge_base64' => $challengeBase64,
            'expires_at' => $session->eds_challenge_expires_at?->toIso8601String() ?? now()->addSeconds($ttl)->toIso8601String(),
        ];
    }

    /**
     * @return array{login_code: string, phone: string, expires_at: string, redirect: string}
     */
    public function verifySignature(string $loginCode, string $cms): array
    {
        $session = $this->findPendingLegalSession($loginCode);

        if ($session->eds_verified_at !== null) {
            return $this->completeAfterEds($session);
        }

        $this->assertChallengeValid($session);

        $challengeBase64 = base64_encode((string) $session->eds_challenge);

        if (config('ncanode.skip_verification')) {
            $this->markVerified($session, [
                'subject' => 'SKIP_VERIFICATION',
                'iin' => null,
                'bin' => $session->bin,
            ]);
        } else {
            $verify = $this->ncaNodeClient->verifyCms($cms, $challengeBase64);

            if (! ($verify['valid'] ?? false)) {
                $message = is_string($verify['message'] ?? null)
                    ? $verify['message']
                    : 'Подпись не прошла проверку.';

                throw new RuntimeException($message);
            }

            $signer = $this->extractPrimarySigner($verify);
            $this->assertSignerMatchesSession($session, $signer);

            $this->markVerified($session, $signer);
        }

        $session = $session->fresh();

        $this->auditLogService->log(
            action: 'auth.legal_entity.eds.verified',
            entityType: 'auth_session',
            entityId: $session->id,
            payload: [
                'phone' => $session->phone,
                'bin' => $session->bin,
                'eds_signer_bin' => $session->eds_signer_bin,
            ],
            request: request(),
        );

        $this->phoneAuthService->sendOtpForSession($session);

        return $this->completeAfterEds($session->fresh());
    }

    private function findPendingLegalSession(string $loginCode): AuthSession
    {
        $session = AuthSession::query()->where('login_code', $loginCode)->first();

        if ($session === null) {
            throw new RuntimeException('Сессия не найдена.');
        }

        if ($session->client_type !== ClientType::LegalEntity->value) {
            throw new RuntimeException('ЭЦП требуется только для юридических лиц.');
        }

        if ($session->isVerified()) {
            throw new RuntimeException('Сессия уже завершена.');
        }

        if ($session->status === 'failed') {
            throw new RuntimeException('Сессия заблокирована. Начните вход заново.');
        }

        if ($session->isExpired()) {
            $session->update(['status' => 'expired']);

            throw new RuntimeException('Сессия истекла. Начните вход заново.');
        }

        return $session;
    }

    private function assertChallengeValid(AuthSession $session): void
    {
        if ($session->eds_challenge === null || $session->eds_challenge === '') {
            throw new RuntimeException('Challenge не найден. Начните регистрацию заново.');
        }

        if ($session->eds_challenge_expires_at?->isPast()) {
            throw new RuntimeException('Время подписи истекло. Начните регистрацию заново.');
        }
    }

    /**
     * @return array{subject: string, iin: ?string, bin: ?string}
     */
    private function extractPrimarySigner(array $verifyResponse): array
    {
        $signers = $verifyResponse['signers'] ?? [];

        if (! is_array($signers) || $signers === []) {
            throw new RuntimeException('В подписи не найден сертификат подписанта.');
        }

        $signer = $signers[0];

        if (! ($signer['valid'] ?? true)) {
            throw new RuntimeException('Сертификат подписанта недействителен.');
        }

        $certificates = $signer['certificates'] ?? [];
        $leaf = is_array($certificates) && $certificates !== [] ? $certificates[0] : null;

        if (! is_array($leaf)) {
            throw new RuntimeException('Не удалось извлечь сертификат из подписи.');
        }

        $subject = $leaf['subject'] ?? [];
        $dn = is_array($subject) ? ($subject['dn'] ?? '') : '';
        $commonName = is_array($subject) ? ($subject['commonName'] ?? $subject['cn'] ?? '') : '';

        $iin = $this->pickString($subject, ['iin', 'IIN', 'serialNumber']);
        $bin = $this->pickString($subject, ['bin', 'BIN', 'businessIdentifier']);

        if ($bin === null && is_string($dn) && $dn !== '') {
            $bin = $this->matchPattern($dn, '/\bBIN(\d{12})\b/i')
                ?? $this->matchPattern($dn, '/1\.2\.398\.3\.3\.4\.1\.3=(\d{12})/');
        }

        if ($iin === null && is_string($dn) && $dn !== '') {
            $iin = $this->matchPattern($dn, '/\bIIN(\d{12})\b/i')
                ?? $this->matchPattern($dn, '/1\.2\.398\.3\.3\.4\.1\.1=(\d{12})/');
        }

        $subjectLabel = is_string($commonName) && $commonName !== ''
            ? $commonName
            : (is_string($dn) ? $dn : 'unknown');

        return [
            'subject' => $subjectLabel,
            'iin' => $iin,
            'bin' => $bin,
        ];
    }

    /**
     * @param  array<string, mixed>  $subject
     */
    private function pickString(array $subject, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $subject[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return preg_replace('/\D+/', '', $value) ?: $value;
            }
        }

        return null;
    }

    private function matchPattern(string $haystack, string $pattern): ?string
    {
        if (preg_match($pattern, $haystack, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array{subject: string, iin: ?string, bin: ?string}  $signer
     */
    private function assertSignerMatchesSession(AuthSession $session, array $signer): void
    {
        if ($signer['bin'] !== null && $session->bin !== null && $signer['bin'] !== $session->bin) {
            throw new RuntimeException('БИН в сертификате не совпадает с указанным БИН организации.');
        }
    }

    /**
     * @param  array{subject: string, iin: ?string, bin: ?string}  $signer
     */
    private function markVerified(AuthSession $session, array $signer): void
    {
        $session->update([
            'eds_verified_at' => now(),
            'eds_certificate_subject' => $signer['subject'],
            'eds_signer_iin' => $signer['iin'],
            'eds_signer_bin' => $signer['bin'] ?? $session->bin,
            'eds_challenge' => null,
            'eds_challenge_expires_at' => null,
        ]);
    }

    /**
     * @return array{login_code: string, phone: string, expires_at: string, redirect: string}
     */
    private function completeAfterEds(AuthSession $session): array
    {
        $locale = request()->route('locale') ?? app()->getLocale();

        return [
            'login_code' => $session->login_code,
            'phone' => $session->phone,
            'expires_at' => $session->expires_at->toIso8601String(),
            'redirect' => route('auth.whatsapp.wait', [
                'locale' => $locale,
                'loginCode' => $session->login_code,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildChallengePayload(AuthSession $session): array
    {
        return [
            'v' => 1,
            'action' => 'legal_entity_registration',
            'nonce' => Str::uuid()->toString(),
            'login_code' => $session->login_code,
            'bin' => $session->bin,
            'company_name' => $session->company_name,
            'phone' => $session->phone,
            'issued_at' => now()->toIso8601String(),
        ];
    }
}
