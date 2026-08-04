<?php
// make_logo.php
// Generates a PNG version of the CAR Rentals logo (for use in FPDF invoices).
// Run once: php make_logo.php
// Requires the PHP GD extension.

$w = 680;
$h = 180;
$img = imagecreatetruecolor($w, $h);

// White background
$white = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $white);

// Brand colors
$orange = imagecolorallocate($img, 255, 77, 77);
$amber  = imagecolorallocate($img, 255, 184, 77);
$dark   = imagecolorallocate($img, 26, 26, 26);
$gray   = imagecolorallocate($img, 120, 120, 120);

// Draw a simple stylized car body (rounded look using filled polygon)
$points = [
    44, 108,   // bottom-left
    88, 52,    // left top
    168, 28,   // hood
    248, 32,   // roof left
    296, 52,   // windshield
    424, 52,   // roof right
    468, 32,   // rear
    536, 56,   // trunk
    552, 108,  // bottom-right
    508, 108,  // wheel right
    500, 92,
    420, 92,
    412, 108,
    172, 108,
    164, 92,
    84, 92,
    76, 108,
    44, 108,
];
// Draw gradient-like body by layering two polygons
imagefilledpolygon($img, $points, count($points)/2, $orange);

// Roof highlight
$roof = [
    168, 46,
    248, 46,
    296, 62,
    424, 62,
    468, 46,
    536, 62,
    536, 72,
    468, 60,
    424, 70,
    296, 70,
    248, 56,
    168, 56,
];
imagefilledpolygon($img, $roof, count($roof)/2, $amber);

// Wheels
$wheelColor = imagecolorallocate($img, 20, 20, 20);
imagefilledellipse($img, 150, 108, 56, 56, $wheelColor);
imagefilledellipse($img, 434, 108, 56, 56, $wheelColor);
$hubColor = imagecolorallocate($img, 200, 200, 200);
imagefilledellipse($img, 150, 108, 24, 24, $hubColor);
imagefilledellipse($img, 434, 108, 24, 24, $hubColor);

// Windows
$win = imagecolorallocate($img, 220, 235, 250);
imagefilledpolygon($img, [248,44, 296,60, 420,60, 424,44], 4, $win);

// Text: "SPEED RENTALS"
// Use a system font if available
$font = null;
foreach ([
    '/Library/Fonts/Arial.ttf',
    '/System/Library/Fonts/Supplemental/Arial.ttf',
    '/System/Library/Fonts/Helvetica.ttc',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
] as $candidate) {
    if (file_exists($candidate)) { $font = $candidate; break; }
}

if ($font) {
    imagettftext($img, 44, 0, 200, 140, $dark, $font, 'SPEED');
    imagettftext($img, 44, 0, 430, 140, $orange, $font, 'RENTALS');
} else {
    // Fallback: draw text with a built-in font
    imagestring($img, 5, 200, 120, 'SPEED RENTALS', $dark);
}

// Save PNG
imagepng($img, __DIR__ . '/logo.png');
imagedestroy($img);
echo "logo.png created\n";
