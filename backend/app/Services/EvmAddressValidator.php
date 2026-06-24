<?php

declare(strict_types=1);

namespace App\Services;

use kornrunner\Keccak;

/**
 * EVM (BSC/Ethereum) address validation with EIP-55 checksum support.
 */
final class EvmAddressValidator
{
    public function isValid(string $address): bool
    {
        if (! preg_match('/^0x[0-9a-fA-F]{40}$/', $address)) {
            return false;
        }

        $hex = substr($address, 2);

        // All-lowercase / all-uppercase addresses carry no checksum information.
        if ($hex === strtolower($hex) || $hex === strtoupper($hex)) {
            return true;
        }

        return $this->toChecksum($address) === $address;
    }

    public function toChecksum(string $address): string
    {
        $hex = strtolower(substr($address, 2));
        $hash = Keccak::hash($hex, 256);

        $checksummed = '';

        for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
            $checksummed .= hexdec($hash[$i]) >= 8
                ? strtoupper($hex[$i])
                : $hex[$i];
        }

        return '0x'.$checksummed;
    }
}
