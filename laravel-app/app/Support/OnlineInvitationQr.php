<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Build invitation QR images without requiring Imagick.
 * Prefer GD PNG (works on Hostinger); fall back to SVG data URI.
 */
class OnlineInvitationQr
{
    public static function dataUri(string $payload, int $size = 320, int $marginModules = 1): string
    {
        $payload = trim($payload);
        if ($payload === '') {
            $payload = ' ';
        }

        if (extension_loaded('imagick')) {
            try {
                $png = QrCode::format('png')->size($size)->margin($marginModules)->generate($payload);

                return 'data:image/png;base64,'.base64_encode($png);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            try {
                $png = self::pngViaGd($payload, $size, $marginModules);

                return 'data:image/png;base64,'.base64_encode($png);
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $svg = (string) QrCode::format('svg')->size($size)->margin($marginModules)->generate($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public static function pngBinary(string $payload, int $size = 320, int $marginModules = 1): string
    {
        $uri = self::dataUri($payload, $size, $marginModules);
        if (preg_match('#^data:image/png;base64,(.+)$#s', $uri, $m)) {
            return (string) base64_decode($m[1], true);
        }

        // Last resort: wrap SVG bytes (callers expecting PNG may still fail).
        if (preg_match('#^data:image/svg\\+xml;base64,(.+)$#s', $uri, $m)) {
            return (string) base64_decode($m[1], true);
        }

        return '';
    }

    protected static function pngViaGd(string $payload, int $size, int $marginModules): string
    {
        $qr = Encoder::encode($payload, ErrorCorrectionLevel::valueOf('M'));
        $matrix = $qr->getMatrix();
        $modules = (int) $matrix->getWidth();
        $totalModules = $modules + (2 * max(0, $marginModules));
        if ($totalModules < 1) {
            throw new \RuntimeException('Invalid QR matrix size');
        }

        $pixelSize = max(1, (int) floor($size / $totalModules));
        $canvas = $pixelSize * $totalModules;

        $img = imagecreatetruecolor($canvas, $canvas);
        if ($img === false) {
            throw new \RuntimeException('GD could not create image');
        }

        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $canvas - 1, $canvas - 1, $white);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ((int) $matrix->get($x, $y) === 1) {
                    $x0 = ($x + $marginModules) * $pixelSize;
                    $y0 = ($y + $marginModules) * $pixelSize;
                    imagefilledrectangle(
                        $img,
                        $x0,
                        $y0,
                        $x0 + $pixelSize - 1,
                        $y0 + $pixelSize - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($img);
        $binary = (string) ob_get_clean();
        imagedestroy($img);

        if ($binary === '') {
            throw new \RuntimeException('GD PNG encode failed');
        }

        return $binary;
    }
}
