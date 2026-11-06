<?php

$im = imagecreatefrompng(dirname(__DIR__).'/brand/favicon.png');
$c = imagecolorat($im, 5, 5);
printf(
    "corner a=%d r=%d g=%d b=%d\n",
    ($c >> 24) & 0x7F,
    ($c >> 16) & 0xFF,
    ($c >> 8) & 0xFF,
    $c & 0xFF
);
$w = imagesx($im);
$h = imagesy($im);
$c2 = imagecolorat($im, intdiv($w, 2), intdiv($h, 2));
printf(
    "center a=%d r=%d g=%d b=%d size=%dx%d\n",
    ($c2 >> 24) & 0x7F,
    ($c2 >> 16) & 0xFF,
    ($c2 >> 8) & 0xFF,
    $c2 & 0xFF,
    $w,
    $h
);
