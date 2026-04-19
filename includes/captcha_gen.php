<?php
/**
 * includes/captcha_gen.php
 * Generates a visual CAPTCHA image and stores code in session.
 */
session_start();

// Generate random string (numbers and capital letters, excluding confusing ones)
$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$code = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $chars[rand(0, strlen($chars) - 1)];
}

$_SESSION['captcha_code'] = $code;

// Create image
$width = 120;
$height = 40;
$image = imagecreatetruecolor($width, $height);

// Colors
$bg = imagecolorallocate($image, 255, 255, 255); // White bg
$text_color = imagecolorallocate($image, 30, 41, 59); // Slate-800
$noise_color = imagecolorallocate($image, 226, 232, 240); // Slate-200

imagefilledrectangle($image, 0, 0, $width, $height, $bg);

// Add Noise (Dots)
for ($i = 0; $i < 1000; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $noise_color);
}

// Add Noise (Lines)
for ($i = 0; $i < 5; $i++) {
    imageline($image, 0, rand(0, $height), $width, rand(0, $height), $noise_color);
}

// Draw characters with slight rotation
for ($i = 0; $i < 5; $i++) {
    $x = 15 + ($i * 18);
    $y = rand(20, 30);
    // Use built-in font for simplicity if custom fonts aren't available, 
    // but typically GD has 1-5 built-in fonts. Font 5 is largest.
    imagechar($image, 5, $x, $y - 10, $code[$i], $text_color);
}

header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
