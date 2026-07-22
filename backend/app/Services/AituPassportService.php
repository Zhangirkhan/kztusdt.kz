<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\AppLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Aitu Passport (OAuth 2.0 / OpenID Connect) integration.
 *
 * Flow:
 *  1. {@see authorizationUrl()} builds the redirect to Aitu Passport.
 *  2. Aitu Passport returns the user to the Redirect URI with ?code&state.
 *  3. {@see exchangeCode()} swaps the one-time code for access/id tokens
 *     (server-side only — client_secret must never leave the backend).
 *  4. {@see claimsFromIdToken()} decodes the OpenID id_token claims.
 *  5. {@see logoutUrl()} builds the Aitu Passport session-logout redirect.
 *
 * @see https://docs.aitu.io/aituapps/aitu-passport/integraciya-s-aitu-passport
 */
final class AituPassportService
{
    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * Build the authorization request URL (OAuth2.0 Workflow, step 1).
     *
     * @param  string  $redirectUri  Must exactly match a Redirect URI registered in the console.
     * @param  string  $state  Opaque CSRF/state token (min length 8 enforced by Aitu Passport).
     * @param  string|null  $phone  Optional 7XXXXXXXXXX prefill (Aitu docs: без «+»).
     * @param  string|null  $iin  Optional 12-digit IIN to prefill / lock (see $iin_signature).
     */
    public function authorizationUrl(
        string $redirectUri,
        string $state,
        ?string $phone = null,
        ?string $iin = null,
        ?string $scope = null,
    ): string {
        $query = array_filter([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'scope' => $scope ?? (string) config('aitu.scope'),
            'state' => $state,
            'locale' => (string) config('aitu.locale'),
            'phone' => $this->phoneForAuthorizationRequest($phone),
            ...$this->iinAuthorizationParams($iin),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return $this->endpoint('authorize').'?'.http_build_query($query);
    }

    /**
     * OAuth scope string for login vs KYC redirect.
     */
    public function scopeForIntent(string $intent): string
    {
        $base = trim((string) config('aitu.scope'));
        $extra = trim((string) config('aitu.kyc_scope'));

        if ($intent === 'kyc' && $extra !== '') {
            return trim($base.' '.$extra);
        }

        return $base;
    }

    public function kycScopeConfigured(): bool
    {
        return trim((string) config('aitu.kyc_scope')) !== '';
    }

    public function iinSigningEnabled(): bool
    {
        return (bool) config('aitu.iin.signing_enabled', false) && $this->iinPrivateKey() !== null;
    }

    /**
     * @return array{iin?: string, iin_signature?: string}
     */
    private function iinAuthorizationParams(?string $iin): array
    {
        if ($iin === null || trim($iin) === '') {
            return [];
        }

        if (! $this->iinSigningEnabled()) {
            // Без iin_signature Aitu stage падает с «illegal base64 character 2d»,
            // если передать только iin (ожидается подпись или публичный ключ в консоли).
            return [];
        }

        $signature = $this->signIin($iin);

        if ($signature === null) {
            return ['iin' => $iin];
        }

        return [
            'iin' => $iin,
            'iin_signature' => $signature,
        ];
    }

    /**
     * Sign an IIN with the partner's RSA private key (SHA256withRSA) and return
     * the signature in base64url, as required by Aitu Passport's iin_signature.
     */
    public function signIin(string $iin): ?string
    {
        $privateKeyPem = $this->iinPrivateKey();

        if ($privateKeyPem === null) {
            return null;
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new RuntimeException('Некорректный приватный RSA-ключ для подписи ИИН.');
        }

        $signature = '';
        $signed = openssl_sign($iin, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Не удалось подписать ИИН.');
        }

        return match (strtolower((string) config('aitu.iin.signature_encoding', 'base64'))) {
            'base64url' => $this->base64UrlEncode($signature),
            default => base64_encode($signature),
        };
    }

    /**
     * Exchange the one-time authorization code for tokens (Workflow, step 2).
     *
     * @return array{access_token: string, id_token: string, token_type?: string, expires_in?: int, raw: array<string, mixed>}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()
            ->timeout((int) config('aitu.http_timeout', 15))
            ->withBasicAuth($this->clientId(), $this->clientSecret())
            ->acceptJson()
            ->post($this->endpoint('token'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if ($response->failed()) {
            AppLog::authWarning('auth.aitu.token_exchange_failed', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            throw new RuntimeException('Не удалось получить токены Aitu Passport.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        $accessToken = (string) ($payload['access_token'] ?? '');
        $idToken = (string) ($payload['id_token'] ?? '');

        if ($accessToken === '' || $idToken === '') {
            throw new RuntimeException('Ответ Aitu Passport не содержит токенов.');
        }

        return [
            'access_token' => $accessToken,
            'id_token' => $idToken,
            'token_type' => isset($payload['token_type']) ? (string) $payload['token_type'] : 'Bearer',
            'expires_in' => isset($payload['expires_in']) ? (int) $payload['expires_in'] : null,
            'raw' => $payload,
        ];
    }

    /**
     * Decode AND verify the OpenID id_token, returning its claims.
     *
     * The token is obtained server-side over TLS, but we still validate it as a
     * security defence-in-depth: structure, exp/nbf/iat (fail-closed on expiry),
     * iss/aud when configured, and the RSA signature (RS256) whenever a public key
     * or JWKS endpoint is configured — in that case a bad signature rejects login.
     *
     * @return array<string, mixed>
     */
    public function claimsFromIdToken(string $idToken): array
    {
        $segments = explode('.', $idToken);

        if (count($segments) < 2) {
            throw new RuntimeException('Некорректный id_token Aitu Passport.');
        }

        $header = $this->decodeJsonSegment($segments[0], 'заголовок');
        $claims = $this->decodeJsonSegment($segments[1], 'данные');

        $this->verifySignature($idToken, $segments, $header);
        $this->assertTimeClaims($claims);
        $this->assertIssuerAndAudience($claims);

        return $this->normalizeNestedClaims($claims);
    }

    /**
     * Aitu иногда кладёт вложенные объекты (gov_doc_verification, confidence_level)
     * в id_token как JSON-строки, а не как объекты.
     *
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>
     */
    private function normalizeNestedClaims(array $claims): array
    {
        foreach (['gov_doc_verification', 'confidence_level', 'confidenceLevel'] as $key) {
            if (! array_key_exists($key, $claims)) {
                continue;
            }

            $parsed = $this->parseStructuredClaimValue($claims[$key]);

            if ($parsed !== null) {
                $claims[$key] = $parsed;
            }
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseStructuredClaimValue(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return (array) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || $trimmed === 'null') {
            return null;
        }

        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return array<string, mixed>|null
     */
    public function structuredClaim(array $claims, string $key): ?array
    {
        if (! array_key_exists($key, $claims)) {
            return null;
        }

        return $this->parseStructuredClaimValue($claims[$key]);
    }

    /**
     * @param  array<int, string>  $segments
     * @param  array<string, mixed>  $header
     */
    private function verifySignature(string $idToken, array $segments, array $header): void
    {
        $publicKeyPem = $this->idTokenPublicKey($header);

        // No verification material configured — accept the server-fetched token but
        // record it so operators know signature verification is currently disabled.
        if ($publicKeyPem === null) {
            AppLog::authWarning('auth.aitu.id_token.signature_unverified', [
                'reason' => 'no_public_key_or_jwks_configured',
            ]);

            return;
        }

        $alg = strtoupper((string) ($header['alg'] ?? ''));

        if ($alg !== 'RS256') {
            throw new RuntimeException("Неподдерживаемый алгоритм подписи id_token Aitu: {$alg}.");
        }

        if (count($segments) < 3 || $segments[2] === '') {
            throw new RuntimeException('id_token Aitu Passport не содержит подписи.');
        }

        $signature = $this->base64UrlDecode($segments[2]);
        $signingInput = $segments[0].'.'.$segments[1];

        $verified = openssl_verify($signingInput, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new RuntimeException('Подпись id_token Aitu Passport не прошла проверку.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertTimeClaims(array $claims): void
    {
        $leeway = (int) config('aitu.id_token.leeway', 60);
        $now = time();

        if (isset($claims['exp']) && $now > ((int) $claims['exp'] + $leeway)) {
            throw new RuntimeException('id_token Aitu Passport истёк.');
        }

        if (isset($claims['nbf']) && $now < ((int) $claims['nbf'] - $leeway)) {
            throw new RuntimeException('id_token Aitu Passport ещё не действителен.');
        }

        if (isset($claims['iat']) && $now < ((int) $claims['iat'] - $leeway)) {
            throw new RuntimeException('id_token Aitu Passport выдан в будущем.');
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function assertIssuerAndAudience(array $claims): void
    {
        $expectedIssuer = (string) config('aitu.id_token.issuer', '');

        if ($expectedIssuer !== '' && (string) ($claims['iss'] ?? '') !== $expectedIssuer) {
            throw new RuntimeException('Неверный издатель (iss) id_token Aitu Passport.');
        }

        if ((bool) config('aitu.id_token.verify_audience', false)) {
            $aud = $claims['aud'] ?? null;
            $audiences = is_array($aud) ? array_map('strval', $aud) : [(string) $aud];

            if (! in_array($this->clientId(), $audiences, true)) {
                throw new RuntimeException('Неверная аудитория (aud) id_token Aitu Passport.');
            }
        }
    }

    /**
     * Resolve the RSA public key (PEM) for id_token verification, from an inline
     * key/file or from the configured JWKS endpoint (matched by the header `kid`).
     *
     * @param  array<string, mixed>  $header
     */
    private function idTokenPublicKey(array $header): ?string
    {
        $inline = (string) config('aitu.id_token.public_key', '');

        if ($inline !== '') {
            return str_contains($inline, "\n") ? $inline : str_replace('\n', "\n", $inline);
        }

        $path = (string) config('aitu.id_token.public_key_path', '');

        if ($path !== '' && is_file($path)) {
            $contents = file_get_contents($path);

            return $contents === false ? null : $contents;
        }

        $jwksUri = config('aitu.id_token.jwks_uri');

        if ($jwksUri === null && (bool) config('aitu.id_token.auto_jwks_uri', true)) {
            $base = rtrim((string) config('aitu.base_url'), '/');
            $jwksUri = $base !== '' ? $base.'/oauth2/jwks' : '';
        } else {
            $jwksUri = (string) $jwksUri;
        }

        if ($jwksUri !== '') {
            return $this->publicKeyFromJwks($jwksUri, (string) ($header['kid'] ?? ''));
        }

        return null;
    }

    private function publicKeyFromJwks(string $jwksUri, string $kid): ?string
    {
        $ttl = (int) config('aitu.id_token.jwks_cache_ttl', 3600);

        /** @var array<int, array<string, mixed>> $keys */
        $keys = Cache::remember('aitu:jwks:'.md5($jwksUri), $ttl, function () use ($jwksUri): array {
            $response = Http::timeout((int) config('aitu.http_timeout', 15))->acceptJson()->get($jwksUri);

            if ($response->failed()) {
                return [];
            }

            $data = $response->json('keys');

            return is_array($data) ? $data : [];
        });

        foreach ($keys as $key) {
            if (($key['kty'] ?? '') !== 'RSA') {
                continue;
            }

            // Match the requested kid; if the token carries no kid, accept a sole key.
            if ($kid !== '' && (string) ($key['kid'] ?? '') !== $kid) {
                continue;
            }

            $pem = $this->rsaJwkToPem((string) ($key['n'] ?? ''), (string) ($key['e'] ?? ''));

            if ($pem !== null) {
                return $pem;
            }
        }

        throw new RuntimeException('Не найден ключ JWKS Aitu Passport для проверки id_token.');
    }

    /**
     * Build a PEM SubjectPublicKeyInfo from a base64url RSA modulus/exponent.
     */
    private function rsaJwkToPem(string $n, string $e): ?string
    {
        if ($n === '' || $e === '') {
            return null;
        }

        $modulus = $this->base64UrlDecode($n);
        $exponent = $this->base64UrlDecode($e);

        $rsaPublicKey = $this->derSequence(
            $this->derUnsignedInteger($modulus).$this->derUnsignedInteger($exponent),
        );

        // AlgorithmIdentifier for rsaEncryption (1.2.840.113549.1.1.1) + NULL params.
        $algorithmIdentifier = $this->derSequence(
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00",
        );

        $subjectPublicKeyInfo = $this->derSequence(
            $algorithmIdentifier.$this->derBitString($rsaPublicKey),
        );

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xff).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function derSequence(string $contents): string
    {
        return "\x30".$this->derLength(strlen($contents)).$contents;
    }

    private function derUnsignedInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        // Prepend a zero byte if the high bit is set, to keep it positive.
        if (ord($bytes[0]) & 0x80) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLength(strlen($bytes)).$bytes;
    }

    private function derBitString(string $contents): string
    {
        return "\x03".$this->derLength(strlen($contents) + 1)."\x00".$contents;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonSegment(string $segment, string $label): array
    {
        $decoded = $this->base64UrlDecode($segment);

        /** @var array<string, mixed>|null $data */
        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            throw new RuntimeException("Не удалось декодировать {$label} id_token Aitu Passport.");
        }

        return $data;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Некорректное base64url-значение в id_token Aitu Passport.');
        }

        return $decoded;
    }

    /**
     * Build the Aitu Passport logout (end-session) URL.
     *
     * @param  string  $idToken  id_token received during authorization.
     * @param  string  $postLogoutRedirectUri  Must match the console's Post Logout Redirect URI.
     */
    public function logoutUrl(string $idToken, string $postLogoutRedirectUri, string $state): string
    {
        $query = http_build_query([
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
            'state' => $state,
        ]);

        return $this->endpoint('logout').'?'.$query;
    }

    /**
     * Find an existing user by phone or provision a new one from Aitu claims.
     *
     * @param  array<string, mixed>  $claims
     */
    public function findOrCreateUser(array $claims): User
    {
        $phone = $this->normalizePhone($this->extractPhone($claims));

        if ($phone === null) {
            throw new RuntimeException('Aitu Passport не вернул номер телефона. Проверьте scope сервиса.');
        }

        $user = User::query()->where('phone', $phone)->first();

        if ($user !== null) {
            $user->update([
                'phone_verified' => true,
                'phone_verified_at' => now(),
            ]);

            return $user;
        }

        $name = (string) ($claims['name'] ?? $claims['given_name'] ?? 'User '.$phone);

        $user = User::query()->create([
            'name' => $name,
            'email' => 'aitu_'.Str::lower(Str::random(16)).'@exchange.local',
            'password' => bcrypt(Str::random(32)),
            'phone' => $phone,
            'phone_verified' => true,
            'phone_verified_at' => now(),
            'kyc_status' => 'none',
        ]);

        app(ReferralService::class)->applyToNewUser($user);

        return $user;
    }

    /**
     * Verify the optional Logout Callback webhook signature.
     */
    public function verifyLogoutWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) config('aitu.logout_webhook_secret');

        if ($secret === '') {
            // Fail-closed once live: never accept an unsigned webhook in production.
            if (app()->environment('production')) {
                AppLog::authWarning('auth.aitu.logout_webhook.secret_missing');

                return false;
            }

            return true;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function normalizePhone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    /**
     * Phone prefill for /oauth2/auth — Aitu expects digits only (e.g. 77071234567).
     */
    public function phoneForAuthorizationRequest(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function phoneFromClaims(array $claims): ?string
    {
        return $this->normalizePhone($this->extractPhone($claims));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    public function iinFromClaims(array $claims): ?string
    {
        $govDoc = $this->structuredClaim($claims, 'gov_doc_verification');

        if ($govDoc !== null && is_string($govDoc['iin'] ?? null)) {
            $iin = trim($govDoc['iin']);

            if ($iin !== '') {
                return $iin;
            }
        }

        if (isset($claims['iin']) && is_scalar($claims['iin'])) {
            $iin = trim((string) $claims['iin']);

            return $iin !== '' ? $iin : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function extractPhone(array $claims): ?string
    {
        foreach (['phone_number', 'phone', 'phoneNumber', 'msisdn'] as $key) {
            if (isset($claims[$key]) && $claims[$key] !== '') {
                return (string) $claims[$key];
            }
        }

        return null;
    }

    /**
     * Resolve the IIN-signing RSA private key (PEM) from inline config or file.
     * Inline values may use escaped "\n" newlines (env-friendly).
     */
    private function iinPrivateKey(): ?string
    {
        $inline = (string) config('aitu.iin.private_key', '');

        if ($inline !== '') {
            return str_contains($inline, "\n") ? $inline : str_replace('\n', "\n", $inline);
        }

        $path = (string) config('aitu.iin.private_key_path', '');

        if ($path !== '' && is_file($path)) {
            $contents = file_get_contents($path);

            return $contents === false ? null : $contents;
        }

        return null;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function endpoint(string $name): string
    {
        $path = (string) config("aitu.endpoints.{$name}");

        return (string) config('aitu.base_url').'/'.ltrim($path, '/');
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Aitu Passport не настроен (AITU_CLIENT_ID / AITU_CLIENT_SECRET).');
        }
    }

    private function clientId(): string
    {
        return (string) config('aitu.client_id');
    }

    private function clientSecret(): string
    {
        return (string) config('aitu.client_secret');
    }
}
