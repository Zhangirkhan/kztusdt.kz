<?php

declare(strict_types=1);

/**
 * Build brand assets from the source logo.
 *
 * Only the outer (border-connected) near-white background is removed via flood-fill.
 * The white top face of the mark is preserved.
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

$master = imagecreatetruecolor($w, $h);
imagealphablending($master, false);
imagesavealpha($master, true);
imagefill($master, 0, 0, imagecolorallocatealpha($master, 0, 0, 0, 127));

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($src, $x, $y);
        $a = ($rgb & 0x7F000000) >> 24;
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        // GD truecolor alpha: 0 opaque .. 127 transparent. Also support loaded PNG opacity.
        $srcA = imagecolorat($src, $x, $y);
        // Prefer reading via imagecolorsforindex when available through truecolor.
        imagesetpixel($master, $x, $y, imagecolorallocatealpha($master, $r, $g, $b, 0));
    }
}

imagecopy($master, $src, 0, 0, 0, 0, $w, $h);
imagealphablending($master, false);
imagesavealpha($master, true);

$visited = array_fill(0, $w * $h, false);
$queue = [];

$enqueue = static function (int $x, int $y) use (&$queue, $w, $h, &$visited): void {
    if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
        return;
    }
    $i = $y * $w + $x;
    if ($visited[$i]) {
        return;
    }
    $visited[$i] = true;
    $queue[] = [$x, $y];
};

for ($x = 0; $x < $w; $x++) {
    $enqueue($x, 0);
    $enqueue($x, $h - 1);
}
for ($y = 0; $y < $h; $y++) {
    $enqueue(0, $y);
    $enqueue($w - 1, $y);
}

$transparent = imagecolorallocatealpha($master, 0, 0, 0, 127);

while ($queue !== []) {
    [$x, $y] = array_shift($queue);
    $rgb = imagecolorat($master, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    $a = ($rgb & 0x7F000000) >> 24;

    $isBg = $a >= 120 || ($r >= 245 && $g >= 245 && $b >= 245);

    if (! $isBg) {
        continue;
    }

    imagesetpixel($master, $x, $y, $transparent);
    $enqueue($x + 1, $y);
    $enqueue($x - 1, $y);
    $enqueue($x, $y + 1);
    $enqueue($x, $y - 1);
}

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
    imagealphablending($out, false);
    imagesavealpha($out, true);

    return $out;
}

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
    $publicDir.'/logo.png' => transparentIcon($master, 512),
    $publicDir.'/logo-wordmark.png' => transparentIcon($master, 512),
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
