<?php

declare(strict_types=1);

namespace App\Services\Tron;

use InvalidArgumentException;

/**
 * TRON address encoding/validation.
 *
 * A TRON account address is derived exactly like an Ethereum one
 * (keccak256(pubkey)[-20:]), then prefixed with the 0x41 network byte and
 * Base58Check-encoded — producing the user-facing "T..." form.
 *
 *   hex (on-chain):  41 || keccak256(pubkey)[-20:]            (21 bytes)
 *   base58 (user):   Base58Check(hex)                          ("T...")
 */
final class TronAddressService
{
    private const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    private const PREFIX = '41';

    /**
     * Build a Base58Check TRON address from a 20-byte keccak hash (40 hex chars,
     * the same value an EVM address carries after the 0x prefix).
     */
    public function fromEvmHash(string $hash40): string
    {
        $hash = strtolower(ltrim($hash40, "0x\t\n\r "));

        if (! preg_match('/^[0-9a-f]{40}$/', $hash)) {
            throw new InvalidArgumentException('Ожидается 20-байтовый hex-хэш адреса.');
        }

        return $this->base58CheckEncode(hex2bin(self::PREFIX.$hash));
    }

    /** Convert a Base58Check "T..." address to its hex form (41...). */
    public function toHex(string $base58): string
    {
        $payload = $this->base58CheckDecode($base58);

        return bin2hex($payload);
    }

    /** Convert a hex address (with or without the 41 prefix) to the Base58Check form. */
    public function fromHex(string $hex): string
    {
        $hex = strtolower(ltrim($hex, "0x"));

        if (strlen($hex) === 40) {
            $hex = self::PREFIX.$hex;
        }

        if (! preg_match('/^41[0-9a-f]{40}$/', $hex)) {
            throw new InvalidArgumentException('Некорректный hex TRON-адрес.');
        }

        return $this->base58CheckEncode(hex2bin($hex));
    }

    public function isValid(string $address): bool
    {
        if (! preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) {
            return false;
        }

        try {
            $payload = $this->base58CheckDecode($address);
        } catch (InvalidArgumentException) {
            return false;
        }

        return strlen($payload) === 21 && $payload[0] === "\x41";
    }

    public function base58CheckEncode(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return $this->base58Encode($payload.$checksum);
    }

    public function base58CheckDecode(string $encoded): string
    {
        $decoded = $this->base58Decode($encoded);

        if (strlen($decoded) < 5) {
            throw new InvalidArgumentException('Слишком короткий Base58Check.');
        }

        $payload = substr($decoded, 0, -4);
        $checksum = substr($decoded, -4);
        $expected = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        if (! hash_equals($expected, $checksum)) {
            throw new InvalidArgumentException('Неверная контрольная сумма Base58Check.');
        }

        return $payload;
    }

    public function base58Encode(string $bytes): string
    {
        $zeros = 0;
        $len = strlen($bytes);

        while ($zeros < $len && $bytes[$zeros] === "\x00") {
            $zeros++;
        }

        $num = $len > 0 ? gmp_import($bytes) : gmp_init(0);
        $base = gmp_init(58);
        $out = '';

        while (gmp_cmp($num, 0) > 0) {
            $rem = gmp_intval(gmp_mod($num, $base));
            $num = gmp_div_q($num, $base);
            $out .= self::ALPHABET[$rem];
        }

        return str_repeat('1', $zeros).strrev($out);
    }

    public function base58Decode(string $encoded): string
    {
        $base = gmp_init(58);
        $num = gmp_init(0);
        $len = strlen($encoded);

        for ($i = 0; $i < $len; $i++) {
            $pos = strpos(self::ALPHABET, $encoded[$i]);

            if ($pos === false) {
                throw new InvalidArgumentException('Недопустимый символ Base58.');
            }

            $num = gmp_add(gmp_mul($num, $base), $pos);
        }

        $bytes = gmp_cmp($num, 0) > 0 ? gmp_export($num) : '';

        $zeros = 0;
        while ($zeros < $len && $encoded[$zeros] === '1') {
            $zeros++;
        }

        return str_repeat("\x00", $zeros).$bytes;
    }
}
