<?php

declare(strict_types=1);

function stripBlackBg(string $src, string $dest, int $threshold = 28): void
{
    $im = imagecreatefrompng($src);
    if (! $im) {
        fwrite(STDERR, "fail {$src}\n");
        exit(1);
    }

    imagesavealpha($im, true);
    imagealphablending($im, false);

    $w = imagesx($im);
    $h = imagesy($im);

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $rgba = imagecolorat($im, $x, $y);
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            if ($r <= $threshold && $g <= $threshold && $b <= $threshold) {
                imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, 0, 0, 0, 127));
            }
        }
    }

    imagepng($im, $dest, 6);
    imagedestroy($im);
    echo basename($dest).' '.filesize($dest).PHP_EOL;
}

$root = dirname(__DIR__, 3);
$out = dirname(__DIR__).DIRECTORY_SEPARATOR.'brand';
if (! is_dir($out)) {
    mkdir($out, 0777, true);
}

stripBlackBg($root.DIRECTORY_SEPARATOR.'favicon.png', $out.DIRECTORY_SEPARATOR.'favicon.png');
stripBlackBg($root.DIRECTORY_SEPARATOR.'logo.png', $out.DIRECTORY_SEPARATOR.'logo.png');

foreach (glob(dirname(__DIR__).DIRECTORY_SEPARATOR.'apps'.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'public') as $dir) {
    copy($out.DIRECTORY_SEPARATOR.'favicon.png', $dir.DIRECTORY_SEPARATOR.'favicon.png');
    copy($out.DIRECTORY_SEPARATOR.'logo.png', $dir.DIRECTORY_SEPARATOR.'logo.png');
    echo 'copied '.$dir.PHP_EOL;
}
