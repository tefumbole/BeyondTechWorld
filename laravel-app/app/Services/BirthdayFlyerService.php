<?php

namespace App\Services;

use App\BirthdayFlyer;
use Illuminate\Support\Str;

class BirthdayFlyerService
{
    const TEMPLATES = ['lace', 'kente', 'kimono'];

    public function randomTemplate()
    {
        $keys = self::TEMPLATES;

        return $keys[array_rand($keys)];
    }

    public function isTemplate($key)
    {
        return in_array((string) $key, self::TEMPLATES, true);
    }

    public function templateUrl($key)
    {
        return asset('public/birthday/mambole/templates/'.$key.'.jpg');
    }

    public function templatePath($key)
    {
        return public_path('birthday/mambole/templates/'.$key.'.jpg');
    }

    /**
     * @param  string|null  $selfieBin  PNG/JPEG bytes, already background-removed when possible
     * @return BirthdayFlyer
     */
    public function createFlyer($phone, $displayName, $callName, $template, $selfieBin = null)
    {
        if (! $this->isTemplate($template)) {
            $template = $this->randomTemplate();
        }
        $id = (string) Str::uuid();
        $file = $id.'.jpg';
        $this->ensureOutDir();
        $this->compose(
            $this->templatePath($template),
            public_path('birthday/mambole/out/'.$file),
            $callName,
            $displayName,
            $selfieBin,
            $template
        );

        return BirthdayFlyer::create([
            'id' => $id,
            'phone' => $phone,
            'display_name' => $displayName,
            'call_name' => $callName,
            'template' => $template,
            'has_selfie' => $selfieBin !== null && $selfieBin !== '',
            'output_file' => $file,
        ]);
    }

    public function compose($templatePath, $destPath, $callName, $displayName, $selfieBin = null, $templateKey = null)
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('Image processing is not available.');
        }
        $flyer = @imagecreatefromjpeg($templatePath);
        if (! $flyer) {
            throw new \RuntimeException('Could not read the birthday template.');
        }
        $w = imagesx($flyer);
        $h = imagesy($flyer);
        imagealphablending($flyer, true);

        $callName = $this->cleanText($callName, 40) ?: 'Ma Mbole';
        $displayName = $this->cleanText($displayName, 40);
        if ($templateKey === null) {
            $templateKey = strtolower((string) pathinfo($templatePath, PATHINFO_FILENAME));
        }

        $this->paintCallName($flyer, $w, $h, $callName, $templateKey);

        if ($selfieBin) {
            $this->paintSelfieRing($flyer, $w, $h, $selfieBin, $displayName);
        } elseif ($displayName !== '') {
            $this->paintFromCaption($flyer, $w, $h, $displayName, (int) round($w * 0.08), (int) round($h * 0.93));
        }

        imagejpeg($flyer, $destPath, 88);
        imagedestroy($flyer);

        if (! is_file($destPath)) {
            throw new \RuntimeException('Could not save the flyer.');
        }
    }

    protected function paintCallName($flyer, $w, $h, $callName, $templateKey = null)
    {
        $font = $this->fontPath('GreatVibes-Regular.ttf');
        $serif = $this->fontPath('Cinzel-Bold.ttf');
        $use = is_file($font) ? $font : $serif;
        if (! is_file($use)) {
            return;
        }

        $plates = [
            'lace' => ['x' => 0.048, 'y' => 0.262, 'w' => 0.43, 'h' => 0.078],
            'kente' => ['x' => 0.045, 'y' => 0.240, 'w' => 0.46, 'h' => 0.128],
            'kimono' => ['x' => 0.042, 'y' => 0.212, 'w' => 0.46, 'h' => 0.108],
        ];
        $key = isset($plates[$templateKey]) ? $templateKey : 'lace';
        $spec = $plates[$key];

        $maxW = (int) round($w * $spec['w']) - 16;
        $fitted = $this->fitCallNameLines($callName, $use, $maxW, $h);
        if (count($fitted['lines']) > 1 && $spec['h'] < 0.12) {
            $spec['h'] = 0.12;
            $spec['y'] = max(0.228, $spec['y'] - 0.02);
        }

        $boxX = (int) round($w * $spec['x']);
        $boxY = (int) round($h * $spec['y']);
        $boxW = (int) round($w * $spec['w']);
        $boxH = (int) round($h * $spec['h']);
        $cover = $this->sampleCoverColor($flyer, max(0, $boxX - 10), $boxY, 18, $boxH);
        $this->fillRoundedRect($flyer, $boxX, $boxY, $boxW, $boxH, (int) round($boxH * 0.28), $cover);

        $gold = imagecolorallocate($flyer, 240, 213, 122);
        $shadow = imagecolorallocate($flyer, 70, 40, 12);
        $size = $fitted['size'];
        $maxSize = (int) floor(($boxH - 12) / max(1, count($fitted['lines'])) / 1.08);
        if ($maxSize >= 14 && $size > $maxSize) {
            $size = $maxSize;
        }
        $lines = $fitted['lines'];
        $lineGap = (int) round($size * 1.05);
        $blockH = count($lines) * $lineGap;
        $y = $boxY + (int) round(($boxH - $blockH) / 2) + $size - 2;
        foreach ($lines as $line) {
            imagettftext($flyer, $size, 0, $boxX + 12, $y + 1, $shadow, $use, $line);
            imagettftext($flyer, $size, 0, $boxX + 11, $y, $gold, $use, $line);
            $y += $lineGap;
        }
    }

    protected function fillRoundedRect($img, $x, $y, $w, $h, $radius, $color)
    {
        $radius = max(8, min((int) floor(min($w, $h) / 2), $radius));
        imagefilledrectangle($img, $x + $radius, $y, $x + $w - $radius, $y + $h, $color);
        imagefilledrectangle($img, $x, $y + $radius, $x + $w, $y + $h - $radius, $color);
        imagefilledellipse($img, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x + $w - $radius, $y + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x + $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($img, $x + $w - $radius, $y + $h - $radius, $radius * 2, $radius * 2, $color);
    }

    /**
     * @return array{size:int,lines:string[]}
     */
    protected function fitCallNameLines($text, $font, $maxW, $h)
    {
        $size = (int) round($h * 0.038);
        $parts = preg_split('/\s+/', $text);
        $two = null;
        if (count($parts) >= 2) {
            $mid = (int) ceil(count($parts) / 2);
            $two = [
                implode(' ', array_slice($parts, 0, $mid)),
                implode(' ', array_slice($parts, $mid)),
            ];
        }

        while ($size >= 15) {
            if ($this->textWidth($size, $font, $text) <= $maxW) {
                return ['size' => $size, 'lines' => [$text]];
            }
            if ($two
                && $this->textWidth($size, $font, $two[0]) <= $maxW
                && $this->textWidth($size, $font, $two[1]) <= $maxW
            ) {
                return ['size' => $size, 'lines' => $two];
            }
            $size -= 2;
        }

        return ['size' => 15, 'lines' => $two ?: [$text]];
    }

    protected function textWidth($size, $font, $text)
    {
        $bb = imagettfbbox($size, 0, $font, $text);

        return abs($bb[2] - $bb[0]);
    }

    protected function paintSelfieRing($flyer, $w, $h, $selfieBin, $displayName)
    {
        $src = @imagecreatefromstring($selfieBin);
        if (! $src) {
            return;
        }
        $size = (int) round(min($w, $h) * 0.26);
        $size = max(110, min(230, $size));
        $bx = (int) round($w * 0.04);
        $by = (int) round($h * 0.71);
        if ($by + $size + 32 > $h) {
            $by = $h - $size - 32;
        }
        $badge = $this->makeRingBadge($src, $size);
        imagedestroy($src);
        if (! $badge) {
            return;
        }

        imagecopy($flyer, $badge, $bx, $by, 0, 0, imagesx($badge), imagesy($badge));
        imagedestroy($badge);

        if ($displayName !== '') {
            $this->paintFromCaption(
                $flyer,
                $w,
                $h,
                $displayName,
                $bx,
                $by + $size + (int) round($h * 0.012)
            );
        }
    }

    protected function makeRingBadge($src, $size)
    {
        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $clear = imagecolorallocatealpha($out, 0, 0, 0, 127);
        imagefilledrectangle($out, 0, 0, $size, $size, $clear);
        imagealphablending($out, true);

        $cx = (int) floor($size / 2);
        $cy = $cx;
        $navy = imagecolorallocate($out, 11, 42, 92);
        $navy2 = imagecolorallocate($out, 8, 28, 70);
        $gold = imagecolorallocate($out, 212, 175, 55);
        $gold2 = imagecolorallocate($out, 240, 211, 122);

        imagefilledellipse($out, $cx, $cy, $size - 2, $size - 2, $navy2);
        imagefilledellipse($out, $cx, $cy, $size - 10, $size - 10, $navy);

        $inner = $size - 28;
        list($sx, $sy, $side) = $this->subjectSquare($src);

        $cut = imagecreatetruecolor($inner, $inner);
        imagealphablending($cut, false);
        imagesavealpha($cut, true);
        $cClear = imagecolorallocatealpha($cut, 0, 0, 0, 127);
        imagefilledrectangle($cut, 0, 0, $inner, $inner, $cClear);
        imagealphablending($cut, true);
        imagecopyresampled($cut, $src, 0, 0, $sx, $sy, $inner, $inner, $side, $side);

        $ir = $inner / 2.0;
        $icx = $ir;
        $icy = $ir;
        for ($y = 0; $y < $inner; $y++) {
            for ($x = 0; $x < $inner; $x++) {
                $dx = ($x + 0.5) - $icx;
                $dy = ($y + 0.5) - $icy;
                if (($dx * $dx + $dy * $dy) > ($ir - 0.5) * ($ir - 0.5)) {
                    continue;
                }
                $col = imagecolorat($cut, $x, $y);
                $a = ($col >> 24) & 0x7F;
                if ($a >= 110) {
                    continue;
                }
                $r = ($col >> 16) & 0xFF;
                $g = ($col >> 8) & 0xFF;
                $b = $col & 0xFF;
                imagesetpixel(
                    $out,
                    (int) round($cx - $ir + $x),
                    (int) round($cy - $ir + $y),
                    imagecolorallocate($out, $r, $g, $b)
                );
            }
        }
        imagedestroy($cut);

        for ($i = 0; $i < 7; $i++) {
            $d = $size - 8 - $i;
            imageellipse($out, $cx, $cy, $d, $d, $i < 3 ? $gold2 : $gold);
        }

        return $out;
    }

    /**
     * Square crop around the face / head: opaque cutout bbox, or upper-center for a full photo.
     *
     * @return array{0:int,1:int,2:int} sx, sy, side
     */
    protected function subjectSquare($src)
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $bbox = $this->opaqueBounds($src);
        if ($bbox) {
            $bw = max(1, $bbox[2] - $bbox[0] + 1);
            $bh = max(1, $bbox[3] - $bbox[1] + 1);
            $headH = max($bw * 0.95, $bh * 0.58);
            $cx = ($bbox[0] + $bbox[2]) / 2.0;
            $cy = $bbox[1] + $headH * 0.42;
            $side = (int) round(max($bw, $headH) * 1.16);
        } else {
            $side = (int) round(min($sw, $sh * 0.72));
            $cx = $sw / 2.0;
            $cy = $sh * 0.36;
        }
        $side = max(32, min($side, max($sw, $sh)));
        $sx = (int) round($cx - $side / 2);
        $sy = (int) round($cy - $side / 2);
        if ($sx < 0) {
            $sx = 0;
        }
        if ($sy < 0) {
            $sy = 0;
        }
        if ($sx + $side > $sw) {
            $sx = max(0, $sw - $side);
        }
        if ($sy + $side > $sh) {
            $sy = max(0, $sh - $side);
        }
        $side = min($side, $sw - $sx, $sh - $sy);

        return [$sx, $sy, max(32, $side)];
    }

    /**
     * @return int[]|null [minX, minY, maxX, maxY]
     */
    protected function opaqueBounds($src)
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $step = ($sw * $sh > 400000) ? 2 : 1;
        $minX = $sw;
        $minY = $sh;
        $maxX = -1;
        $maxY = -1;
        $found = 0;
        $sampled = 0;
        for ($y = 0; $y < $sh; $y += $step) {
            for ($x = 0; $x < $sw; $x += $step) {
                $sampled++;
                $a = (imagecolorat($src, $x, $y) >> 24) & 0x7F;
                if ($a >= 110) {
                    continue;
                }
                $found++;
                if ($x < $minX) {
                    $minX = $x;
                }
                if ($y < $minY) {
                    $minY = $y;
                }
                if ($x > $maxX) {
                    $maxX = $x;
                }
                if ($y > $maxY) {
                    $maxY = $y;
                }
            }
        }
        if ($found < 40 || $found > $sampled * 0.92) {
            return null;
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    protected function paintFromCaption($flyer, $w, $h, $displayName, $x, $y)
    {
        $font = $this->fontPath('Cinzel-Bold.ttf');
        if (! is_file($font)) {
            $font = $this->fontPath('GreatVibes-Regular.ttf');
        }
        if (! is_file($font)) {
            return;
        }
        $label = 'From '.$displayName;
        $size = (int) round($h * 0.022);
        $size = max(11, min(18, $size));
        $gold = imagecolorallocate($flyer, 240, 213, 122);
        $shadow = imagecolorallocate($flyer, 20, 16, 8);
        imagettftext($flyer, $size, 0, $x + 1, $y + 1, $shadow, $font, $label);
        imagettftext($flyer, $size, 0, $x, $y, $gold, $font, $label);
        unset($w);
    }

    protected function sampleCoverColor($img, $x, $y, $boxW, $boxH)
    {
        $points = [
            [$x + 10, $y + (int) round($boxH * 0.50)],
            [$x + 18, $y + 8],
            [$x + (int) round($boxW * 0.22), $y + (int) round($boxH * 0.42)],
            [$x + 14, $y + $boxH - 10],
        ];
        $bestR = 40;
        $bestG = 18;
        $bestB = 18;
        $bestLum = 999;
        $maxX = imagesx($img) - 1;
        $maxY = imagesy($img) - 1;
        foreach ($points as $pt) {
            $px = max(0, min($maxX, $pt[0]));
            $py = max(0, min($maxY, $pt[1]));
            $rgb = imagecolorat($img, $px, $py);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $lum = 0.299 * $r + 0.587 * $g + 0.114 * $b;
            if ($lum < $bestLum) {
                $bestLum = $lum;
                $bestR = $r;
                $bestG = $g;
                $bestB = $b;
            }
        }

        return imagecolorallocate(
            $img,
            max(0, $bestR - 10),
            max(0, $bestG - 10),
            max(0, $bestB - 8)
        );
    }

    protected function fontPath($file)
    {
        return public_path('birthday/mambole/fonts/'.$file);
    }

    protected function cleanText($value, $max)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $value));
        $text = preg_replace('/[^\p{L}\p{N}\p{P}\p{Zs}]/u', '', $text);
        if ($text === null) {
            $text = '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $max);
        }

        return substr($text, 0, $max);
    }

    protected function ensureOutDir()
    {
        $dir = public_path('birthday/mambole/out');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
