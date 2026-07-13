<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Response;
use RuntimeException;

final class CaptchaService
{
    private const SESSION_KEY = 'auth.captcha_code';

    private const LENGTH = 5;

    /** Avoid ambiguous characters: 0/O, 1/I/L. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function regenerate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        session([self::SESSION_KEY => strtolower($code)]);

        return $code;
    }

    public function matches(?string $input): bool
    {
        $expected = session(self::SESSION_KEY);

        if (! is_string($expected) || $expected === '' || $input === null) {
            return false;
        }

        return hash_equals($expected, strtolower(trim($input)));
    }

    public function invalidate(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function image(): Response
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('GD extension is required for captcha.');
        }

        $code = $this->regenerate();
        $width = 160;
        $height = 48;
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('Failed to create captcha image.');
        }

        $background = imagecolorallocate($image, 18, 24, 38);
        $textColor = imagecolorallocate($image, 230, 236, 245);
        $noiseColor = imagecolorallocate($image, 80, 100, 130);

        if ($background === false || $textColor === false || $noiseColor === false) {
            imagedestroy($image);

            throw new RuntimeException('Failed to allocate captcha colors.');
        }

        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        for ($i = 0; $i < 40; $i++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), $noiseColor);
        }

        for ($i = 0; $i < 4; $i++) {
            imageline(
                $image,
                random_int(0, $width / 2),
                random_int(0, $height - 1),
                random_int($width / 2, $width - 1),
                random_int(0, $height - 1),
                $noiseColor,
            );
        }

        $charWidth = (int) floor($width / (self::LENGTH + 1));

        for ($i = 0; $i < self::LENGTH; $i++) {
            $x = $charWidth * ($i + 1) - 6;
            $y = random_int(14, 28);
            imagestring($image, 5, $x, $y, $code[$i], $textColor);
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);
        $png = ob_get_clean();

        if ($png === false) {
            throw new RuntimeException('Failed to render captcha image.');
        }

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
