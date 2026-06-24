<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AuditLogService;
use App\Services\PhoneAuthService;
use App\Services\TelegramGatewayService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizationTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function phones(): array
    {
        return [
            'kazakh 8-prefixed' => ['8 707 123 45 67', '+77071234567'],
            'plus seven with punctuation' => ['+7 (707) 123-45-67', '+77071234567'],
            'bare digits with 7' => ['77071234567', '+77071234567'],
            'already normalized' => ['+77071234567', '+77071234567'],
            'foreign number untouched' => ['+380501234567', '+380501234567'],
        ];
    }

    #[DataProvider('phones')]
    public function test_normalizes_phone(string $input, string $expected): void
    {
        $service = new PhoneAuthService(new AuditLogService, new TelegramGatewayService);

        $this->assertSame($expected, $service->normalizePhone($input));
    }
}
