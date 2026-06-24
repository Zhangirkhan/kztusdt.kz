<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\EvmAddressValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EvmAddressValidatorTest extends TestCase
{
    private EvmAddressValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new EvmAddressValidator;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validAddresses(): array
    {
        return [
            // Official EIP-55 test vectors.
            'eip55 vector 1' => ['0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed'],
            'eip55 vector 2' => ['0xfB6916095ca1df60bB79Ce92cE3Ea74c37c5d359'],
            'eip55 vector 3' => ['0xdbF03B407c01E7cD3CBea99509d93f8DDDC8C6FB'],
            'eip55 vector 4' => ['0xD1220A0cf47c7B9Be7A2E6BA89F429762e7b9aDb'],
            // No checksum information — must be accepted.
            'all lowercase' => ['0x5aaeb6053f3e94c9b9a09f33669435e7ef1beaed'],
            'all uppercase' => ['0x5AAEB6053F3E94C9B9A09F33669435E7EF1BEAED'],
            // Project hot wallet.
            'hot wallet' => ['0xC20DED767eb4D81F65FEf76aF0E9b634e9EE1a22'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAddresses(): array
    {
        return [
            'bad checksum (one letter case flipped)' => ['0x5aaeb6053F3E94C9b9A09f33669435E7Ef1BeAed'],
            'missing 0x prefix' => ['5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed'],
            'too short' => ['0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAe'],
            'too long' => ['0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed1'],
            'non-hex characters' => ['0xZZZeb6053F3E94C9b9A09f33669435E7Ef1BeAed'],
            'empty string' => [''],
            'random text' => ['not-an-address'],
        ];
    }

    #[DataProvider('validAddresses')]
    public function test_accepts_valid_addresses(string $address): void
    {
        $this->assertTrue($this->validator->isValid($address));
    }

    #[DataProvider('invalidAddresses')]
    public function test_rejects_invalid_addresses(string $address): void
    {
        $this->assertFalse($this->validator->isValid($address));
    }

    public function test_to_checksum_produces_eip55_form(): void
    {
        $this->assertSame(
            '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed',
            $this->validator->toChecksum('0x5aaeb6053f3e94c9b9a09f33669435e7ef1beaed'),
        );
    }
}
