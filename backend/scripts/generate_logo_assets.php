<?php

declare(strict_types=1);

/**
 * One-off: build brand assets from the source logo.
 * Removes the (flattened) white background and emits transparent PNGs plus a
 * maskable icon on the brand-dark background.
 */
$source = $argv[1] ?? null;

if ($source === null || ! is_file($source)) {
    fwrite(STDERR, "Source image not found\n");
    exit(1);
}

$publicDir = __DIR__.'/../public';

$src = imagecreatefromstring((string) file_get_contents($source));

if ($src === false) {
    fwrite(STDERR, "Cannot decode source image\n");
    exit(1);
}

$w = imagesx($src);
$h = imagesy($src);

// Master with white knocked out to transparency.
$master = imagecreatetruecolor($w, $h);
imagealphablending($master, false);
imagesavealpha($master, true);
imagefill($master, 0, 0, imagecolorallocatealpha($master, 0, 0, 0, 127));

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($src, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        if ($lum >= 244) {
            continue; // leave transparent
        }

        $alpha = 0; // opaque
        if ($lum > 230) {
            $alpha = (int) round(127 * (($lum - 230) / 14));
        }

        imagesetpixel($master, $x, $y, imagecolorallocatealpha($master, $r, $g, $b, $alpha));
    }
}

/**
 * Resize the transparent master onto a square transparent canvas.
 */
function transparentIcon($master, int $size): \GdImage
{
    $mw = imagesx($master);
    $mh = imagesy($master);

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagealphablending($out, true);
    imagecopyresampled($out, $master, 0, 0, 0, 0, $size, $size, $mw, $mh);

    return $out;
}

/**
 * Opaque white-background icon (safe for iOS apple-touch & maskable, where
 * transparency would otherwise render as black and hide the dark logo half).
 * $scale controls the logo size inside the square (1.0 = full bleed).
 */
function whiteIcon($master, int $size, float $scale = 1.0): \GdImage
{
    $mw = imagesx($master);
    $mh = imagesy($master);

    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocate($out, 0xFF, 0xFF, 0xFF));

    $inner = (int) round($size * $scale);
    $offset = (int) round(($size - $inner) / 2);

    imagealphablending($out, true);
    imagecopyresampled($out, $master, $offset, $offset, 0, 0, $inner, $inner, $mw, $mh);

    return $out;
}

$targets = [
    // In-app marks (rendered in the DOM over the dark UI) — transparent.
    $publicDir.'/logo.png' => transparentIcon($master, 512),
    $publicDir.'/logo-wordmark.png' => transparentIcon($master, 512),
    // Installable / home-screen icons — opaque white so both logo halves read.
    $publicDir.'/icons/icon-192.png' => whiteIcon($master, 192),
    $publicDir.'/icons/icon-512.png' => whiteIcon($master, 512),
    $publicDir.'/icons/icon-32.png' => whiteIcon($master, 32),
    $publicDir.'/icons/icon-512-maskable.png' => whiteIcon($master, 512, 0.80),
];

foreach ($targets as $path => $image) {
    imagepng($image, $path);
    echo "wrote {$path}\n";
}

echo "done\n";
