<?php

declare(strict_types=1);

/**
 * Build client brand assets from light/dark source logos.
 *
 * Admin PWA icons live in public/icons/admin/ and are not overwritten here.
 *
 * Usage:
 *   php scripts/generate_logo_assets.php [light_source] [dark_source]
 */
$lightSource = $argv[1] ?? (__DIR__.'/../logo_light.jpg');
$darkSource = $argv[2] ?? (__DIR__.'/../logo_dark.png');

foreach (['light' => $lightSource, 'dark' => $darkSource] as $label => $path) {
    if (! is_file($path)) {
        fwrite(STDERR, "Source image not found ({$label}): {$path}\n");
        exit(1);
    }
}

$publicDir = __DIR__.'/../public';

function loadImage(string $path): \GdImage
{
    $src = imagecreatefromstring((string) file_get_contents($path));

    if ($src === false) {
        fwrite(STDERR, "Cannot decode source image: {$path}\n");
        exit(1);
    }

    imagealphablending($src, false);
    imagesavealpha($src, true);

    return $src;
}

/**
 * Remove near-black corner backdrop (JPEG squircle padding) via flood-fill.
 */
function punchNearBlackCorners(\GdImage $src, int $threshold = 28): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    $transparent = imagecolorallocatealpha($src, 0, 0, 0, 127);
    $visited = [];
    $stack = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];

    while ($stack !== []) {
        [$x, $y] = array_pop($stack);
        if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
            continue;
        }

        $key = $y * $w + $x;
        if (isset($visited[$key])) {
            continue;
        }
        $visited[$key] = true;

        $rgba = imagecolorat($src, $x, $y);
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        if ($r > $threshold || $g > $threshold || $b > $threshold) {
            continue;
        }

        imagesetpixel($src, $x, $y, $transparent);
        $stack[] = [$x + 1, $y];
        $stack[] = [$x - 1, $y];
        $stack[] = [$x, $y + 1];
        $stack[] = [$x, $y - 1];
    }
}

function resizeCover(\GdImage $master, int $size, float $scale = 1.0): \GdImage
{
    $mw = imagesx($master);
    $mh = imagesy($master);

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 0));

    $inner = max(1, (int) round($size * $scale));
    $offset = (int) round(($size - $inner) / 2);

    imagealphablending($out, true);
    imagecopyresampled($out, $master, $offset, $offset, 0, 0, $inner, $inner, $mw, $mh);
    imagealphablending($out, false);
    imagesavealpha($out, true);

    return $out;
}

function writePng(string $path, \GdImage $image): void
{
    imagepng($image, $path, 6);
    echo "wrote {$path}\n";
}

$light = loadImage($lightSource);
punchNearBlackCorners($light);
$dark = loadImage($darkSource);

// In-app marks: light theme uses green mark, dark theme uses charcoal mark.
writePng($publicDir.'/logo.png', resizeCover($light, 512));
writePng($publicDir.'/logo-dark.png', resizeCover($dark, 512));
writePng($publicDir.'/logo-wordmark.png', resizeCover($light, 512));
writePng($publicDir.'/logo-wordmark-dark.png', resizeCover($dark, 512));

// Favicon / tab icons for theme switching.
writePng($publicDir.'/icons/icon-32.png', resizeCover($light, 32));
writePng($publicDir.'/icons/icon-32-dark.png', resizeCover($dark, 32));

// PWA / apple-touch / SEO: charcoal mark reads clearly on home screens.
writePng($publicDir.'/icons/icon-192.png', resizeCover($dark, 192));
writePng($publicDir.'/icons/icon-512.png', resizeCover($dark, 512));
writePng($publicDir.'/icons/icon-512-maskable.png', resizeCover($dark, 512, 0.80));

echo "done\n";
