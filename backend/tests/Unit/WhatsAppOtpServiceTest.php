<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\WhatsAppOtpService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class WhatsAppOtpServiceTest extends TestCase
{
    public function test_phone_for_api_normalizes_kazakhstan_numbers(): void
    {
        $service = new WhatsAppOtpService;

        $this->assertSame('77071234567', $service->phoneForApi('+77071234567'));
        $this->assertSame('77071234567', $service->phoneForApi('8 707 123 45 67'));
    }

    public function test_send_posts_to_whatsapp_otp_api(): void
    {
        config([
            'otp.token' => 'secret-token',
            'otp.base_url' => 'https://otp.kztusdt.kz/api',
            'otp.purpose' => 'login',
        ]);

        Http::fake([
            'otp.kztusdt.kz/api/otp/send' => Http::response([
                'success' => true,
                'message' => 'OTP отправлен на WhatsApp',
                'expires_in' => 300,
            ]),
        ]);

        $expiresIn = (new WhatsAppOtpService)->send('+77071234567');

        $this->assertSame(300, $expiresIn);

        Http::assertSent(fn ($request) => $request->url() === 'https://otp.kztusdt.kz/api/otp/send'
            && $request['phone'] === '77071234567'
            && $request['purpose'] === 'login'
            && $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_verify_throws_on_invalid_code_response(): void
    {
        config(['otp.token' => 'secret-token']);

        Http::fake([
            'otp.kztusdt.kz/api/otp/verify' => Http::response([
                'success' => false,
                'message' => 'Неверный или просроченный код',
            ], 422),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Неверный или просроченный код');

        (new WhatsAppOtpService)->verify('+77071234567', '000000');
    }
}
