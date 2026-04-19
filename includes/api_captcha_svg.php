<?php
/**
 * includes/api_captcha_svg.php
 * Generates an alphanumeric CAPTCHA using pure SVG (no GD library required).
 */
session_start();
header('Content-Type: image/svg+xml');

// Generate random code
$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$code = '';
for ($i = 0; $i < 6; $i++) {
    $code .= $chars[rand(0, strlen($chars) - 1)];
}
$_SESSION['captcha_code'] = $code;

// SVG Dimensions
$w = 150;
$h = 50;

echo '<?xml version="1.0" encoding="UTF-8" standalone="no"?>';
?>
<svg width="<?= $w ?>" height="<?= $h ?>" viewBox="0 0 <?= $w ?> <?= $h ?>" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="white" />
    <rect width="100%" height="100%" fill="none" stroke="#e2e8f0" stroke-width="1" />
    
    <!-- Background Noise: Dots -->
    <?php for ($i = 0; $i < 100; $i++): ?>
        <circle cx="<?= rand(0, $w) ?>" cy="<?= rand(0, $h) ?>" r="<?= rand(0.5, 1.5) ?>" fill="#cbd5e1" opacity="0.5" />
    <?php endfor; ?>

    <!-- Random Lines across text -->
    <?php for ($i = 0; $i < 5; $i++): ?>
        <line x1="<?= rand(0, $w) ?>" y1="<?= rand(0, $h) ?>" x2="<?= rand(0, $w) ?>" y2="<?= rand(0, $h) ?>" stroke="#94a3b8" stroke-width="1" opacity="0.4" />
    <?php endfor; ?>

    <!-- Character generation with distortion -->
    <g font-family="monospace, Courier, fixed" font-weight="900" font-size="24" fill="#1e293b">
        <?php 
        $startX = 15;
        for ($i = 0; $i < 6; $i++): 
            $char = $code[$i];
            $rot = rand(-20, 20);
            $y = rand(28, 38);
            $x = $startX + ($i * 22);
        ?>
        <text x="<?= $x ?>" y="<?= $y ?>" transform="rotate(<?= $rot ?>, <?= $x ?>, <?= $y ?>)"><?= $char ?></text>
        <?php endfor; ?>
    </g>

    <!-- Overlay "Scratches" -->
    <?php for ($i = 0; $i < 4; $i++): ?>
        <path d="M <?= rand(0, $w) ?> <?= rand(0, $h) ?> L <?= rand(0, $w) ?> <?= rand(0, $h) ?>" stroke="#1e293b" stroke-width="0.5" opacity="0.3" />
    <?php endfor; ?>
</svg>
