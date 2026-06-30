<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AituPassportService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class AituPassportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aitu.client_id' => 'test-client',
            'aitu.client_secret' => 'test-secret',
            'aitu.base_url' => 'https://passport.test.supreme-team.tech',
            'aitu.scope' => 'openid phone',
            'aitu.endpoints.authorize' => '/oauth2/auth',
            'aitu.endpoints.token' => '/oauth2/token',
        ]);
    }

    #[DataProvider('authorizationPhones')]
    public function test_authorization_url_uses_digits_only_phone_prefill(string $input, string $expectedDigits): void
    {
        $url = app(AituPassportService::class)->authorizationUrl(
            redirectUri: 'https://kztusdt.kz/auth/aitu/callback',
            state: '12345678',
            phone: $input,
        );

        $this->assertStringContainsString('phone='.$expectedDigits, $url);
        $this->assertStringNotContainsString('phone=%2B', $url);
        $this->assertStringStartsWith('https://passport.test.supreme-team.tech/oauth2/auth?', $url);
    }

    public function test_authorization_url_omits_iin_when_signing_disabled(): void
    {
        config(['aitu.iin.signing_enabled' => false, 'aitu.iin.private_key' => 'dummy']);

        $url = app(AituPassportService::class)->authorizationUrl(
            redirectUri: 'https://kztusdt.kz/auth/aitu/callback',
            state: '12345678',
            iin: '900101300123',
        );

        $this->assertStringNotContainsString('iin_signature', $url);
        $this->assertStringNotContainsString('iin=', $url);
    }

    public function test_sign_iin_uses_standard_base64_by_default(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($keyPair);

        $details = openssl_pkey_get_details($keyPair);
        $this->assertIsArray($details);

        config([
            'aitu.iin.signing_enabled' => true,
            'aitu.iin.private_key' => $details['key'],
            'aitu.iin.signature_encoding' => 'base64',
        ]);

        $service = app(AituPassportService::class);
        $signature = $service->signIin('900101300123');

        $this->assertNotNull($signature);
        $this->assertStringNotContainsString('-', $signature);
        $this->assertStringNotContainsString('_', $signature);
        $this->assertNotFalse(base64_decode($signature, true));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function authorizationPhones(): array
    {
        return [
            'plus seven' => ['+77071234567', '77071234567'],
            'eight prefix' => ['87071234567', '77071234567'],
            'bare digits' => ['77071234567', '77071234567'],
        ];
    }
}
