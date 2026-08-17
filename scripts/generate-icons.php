<?php

$sizes = [192, 512];
$dir = __DIR__ . '/../public/icons';
$teal = [13, 148, 136];
$bg = [248, 250, 252];
$white = 255;

if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);
    imagefill($img, 0, 0, imagecolorallocate($img, $bg[0], $bg[1], $bg[2]));

    $pad = (int) ($size * 0.08);
    $radius = (int) ($size * 0.18);
    $tealColor = imagecolorallocate($img, $teal[0], $teal[1], $teal[2]);
    filledRoundedRect($img, $pad, $pad, $size - $pad, $size - $pad, $radius, $tealColor);

    $thick = max(3, (int) ($size * 0.09));
    $whiteColor = imagecolorallocate($img, $white, $white, $white);
    imagesetthickness($img, $thick);
    $cx = (int) ($size * 0.36);
    imageline($img, $cx, (int) ($size * 0.30), $cx, (int) ($size * 0.70), $whiteColor);
    imageline($img, $cx, (int) ($size * 0.50), (int) ($size * 0.66), (int) ($size * 0.30), $whiteColor);
    imageline($img, $cx, (int) ($size * 0.50), (int) ($size * 0.66), (int) ($size * 0.70), $whiteColor);

    imagepng($img, $dir . '/icon-' . $size . '.png');
    imagedestroy($img);
}

function filledRoundedRect($img, $x1, $y1, $x2, $y2, $radius, $color)
{
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}
