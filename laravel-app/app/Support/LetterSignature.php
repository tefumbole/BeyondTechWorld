<?php

namespace App\Support;

use App\User;
use Illuminate\Support\Facades\DB;

class LetterSignature
{
    public static function storeFromDataUrl(string $dataUrl, string $prefix = 'letter_sig'): ?string
    {
        if (!preg_match('/^data:image\/png;base64,/', $dataUrl)) {
            return null;
        }

        $raw = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }

        imagesavealpha($src, true);
        imagealphablending($src, false);
        $src = self::trimImage($src);
        $src = self::stampDate($src, date('M d, Y'));

        $dir = self::ensureWritableDir([
            public_path('letter/signatures'),
            storage_path('app/public/letter/signatures'),
            storage_path('app/signatures'),
        ]);
        if (! $dir) {
            imagedestroy($src);
            \Log::error('LetterSignature: no writable signatures directory');

            return null;
        }

        $filename = $prefix . '_' . date('YmdHis') . '_' . uniqid() . '.png';
        $path = $dir . '/' . $filename;
        $ok = @imagepng($src, $path);
        imagedestroy($src);

        return $ok && is_file($path) ? $filename : null;
    }

    /**
     * Copy a user account signature (images/user) into letter signatures with a date stamp.
     */
    public static function storeFromAccountFile(?string $userFilename, string $prefix = 'letter_sig'): ?string
    {
        $path = self::userImagePath($userFilename);
        if (! $path) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (! $src) {
            return null;
        }

        imagesavealpha($src, true);
        imagealphablending($src, false);
        $src = self::trimImage($src);
        $src = self::stripBottomStampBand($src);
        $src = self::stampDate($src, date('M d, Y'));

        $dir = self::ensureWritableDir([
            public_path('letter/signatures'),
            storage_path('app/public/letter/signatures'),
            storage_path('app/signatures'),
        ]);
        if (! $dir) {
            imagedestroy($src);

            return null;
        }

        $filename = $prefix.'_'.date('YmdHis').'_'.uniqid().'.png';
        $out = $dir.'/'.$filename;
        $ok = @imagepng($src, $out);
        imagedestroy($src);

        return $ok && is_file($out) ? $filename : null;
    }

    /**
     * Account signature column for edit / approve / sign steps.
     */
    public static function accountColumnForPrefix(string $prefix): ?string
    {
        $map = [
            'edit' => 'stemp',
            'approve' => 'approve',
            'sign' => 'sign',
        ];

        return $map[$prefix] ?? null;
    }

    /**
     * Resolve/create the first writable directory from candidates.
     *
     * @param  array  $candidates
     * @return string|null
     */
    public static function ensureWritableDir(array $candidates)
    {
        foreach ($candidates as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return null;
    }

    /**
     * Absolute path for a stored signature filename (checks known dirs).
     */
    public static function absolutePath(?string $filename): ?string
    {
        if (! $filename) {
            return null;
        }
        foreach ([
            public_path('letter/signatures/'.$filename),
            storage_path('app/public/letter/signatures/'.$filename),
            storage_path('app/signatures/'.$filename),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function url(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        return url('public/letter/signatures/' . $filename);
    }

    public static function path(?string $filename): ?string
    {
        return self::absolutePath($filename);
    }

    public static function resolveEditSrc($letter, $user = null): ?string
    {
        if (!empty($letter->edit_signature)) {
            return self::url($letter->edit_signature);
        }

        if ($user && !empty($user->stemp)) {
            return url('public/images/user/' . $user->stemp);
        }

        return null;
    }

    public static function resolveApproveSrc($letter, $user = null): ?string
    {
        if (!empty($letter->approve_signature)) {
            return self::url($letter->approve_signature);
        }

        if ($user && !empty($user->approve)) {
            return url('public/images/user/' . $user->approve);
        }

        return null;
    }

    public static function resolveSignSrc($letter, $user = null): ?string
    {
        if (!empty($letter->sign_signature)) {
            return self::url($letter->sign_signature);
        }

        if ($user && !empty($user->sign)) {
            return url('public/images/user/' . $user->sign);
        }

        return null;
    }

    /**
     * Absolute path for a user profile image under images/user.
     */
    public static function userImagePath(?string $filename): ?string
    {
        if (! $filename) {
            return null;
        }
        foreach ([
            public_path('images/user/'.$filename),
            storage_path('app/public/images/user/'.$filename),
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Active admin user that has a signature on file.
     */
    public static function adminWithSignature(): ?User
    {
        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('sign')
            ->where('sign', '!=', '')
            ->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            });

        $admin = (clone $query)
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['admin'])
                    ->orWhere('email', 'like', 'admin@%');
            })
            ->orderBy('id')
            ->first();

        if ($admin) {
            return $admin;
        }

        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');
        if (! $adminRoleId) {
            return null;
        }

        return $query->where('role_id', $adminRoleId)->orderBy('id')->first();
    }

    /**
     * PNG data-URI of the admin signature, re-stamped with the document date
     * so a copied signature image is tied to that document.
     */
    public static function adminSignatureDataUri($date = null): ?string
    {
        $admin = self::adminWithSignature();
        if (! $admin || empty($admin->sign)) {
            return null;
        }

        return self::stampedDataUriFromPath(self::userImagePath($admin->sign), $date);
    }

    /**
     * Signature for invoice/quotation/rental "Created By" block.
     * Prefer the creating user's sign, then admin. Returns stamped data-URI when
     * possible, otherwise a public URL (with separate date label in the view).
     *
     * @return array{src:?string,stamp:string,via:string}
     */
    public static function invoiceSignatureForUser($user = null, $date = null): array
    {
        $stamp = self::normalizeStampDate($date);
        $candidates = [];

        if ($user && ! empty($user->sign)) {
            $candidates[] = ['file' => $user->sign, 'via' => 'user'];
        }

        $admin = self::adminWithSignature();
        if ($admin && ! empty($admin->sign)) {
            $already = ! empty($candidates[0]['file']) && $candidates[0]['file'] === $admin->sign;
            if (! $already) {
                $candidates[] = ['file' => $admin->sign, 'via' => 'admin'];
            }
        }

        foreach ($candidates as $candidate) {
            $path = self::userImagePath($candidate['file']);
            if (! $path) {
                continue;
            }

            $stamped = self::stampedDataUriFromPath($path, $stamp);
            if ($stamped) {
                return ['src' => $stamped, 'stamp' => $stamp, 'via' => $candidate['via']];
            }

            // GD unavailable / corrupt file — still show the raw signature URL.
            return [
                'src' => url('public/images/user/'.$candidate['file']),
                'stamp' => $stamp,
                'via' => $candidate['via'],
            ];
        }

        return ['src' => null, 'stamp' => $stamp, 'via' => 'none'];
    }

    /**
     * Load an image, stamp a tiny date under it, return a PNG data-URI.
     * Replaces any previous bottom stamp strip when possible by using a
     * fresh stamp line for the given document date.
     */
    public static function stampedDataUriFromPath(?string $path, $date = null): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (! $src) {
            return null;
        }

        imagesavealpha($src, true);
        imagealphablending($src, false);

        // Drop a prior tiny stamp strip (from attach-time) so invoice gets one document date.
        $src = self::stripBottomStampBand($src);

        $stamp = self::normalizeStampDate($date);
        $src = self::stampDate($src, $stamp);

        ob_start();
        imagepng($src);
        $bin = ob_get_clean();
        imagedestroy($src);

        if ($bin === false || $bin === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bin);
    }

    /**
     * Remove a short mostly-empty band at the bottom (attach-time date line).
     */
    private static function stripBottomStampBand($img)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $font = 1;
        $band = imagefontheight($font) + 4;
        if ($height <= $band + 8) {
            return $img;
        }

        $cut = $height - $band;
        $opaqueInBand = 0;
        for ($y = $cut; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 120) {
                    $opaqueInBand++;
                }
            }
        }
        // Only strip when the band looks like sparse text, not real ink.
        $bandPixels = $width * $band;
        if ($opaqueInBand <= 0 || ($opaqueInBand / max(1, $bandPixels)) > 0.12) {
            return $img;
        }

        $trimmed = imagecreatetruecolor($width, $cut);
        imagesavealpha($trimmed, true);
        $transparent = imagecolorallocatealpha($trimmed, 0, 0, 0, 127);
        imagefill($trimmed, 0, 0, $transparent);
        imagecopy($trimmed, $img, 0, 0, 0, 0, $width, $cut);
        imagedestroy($img);

        return $trimmed;
    }

    public static function normalizeStampDate($date = null): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('M d, Y');
        }
        if (is_string($date) && trim($date) !== '') {
            $ts = strtotime($date);
            if ($ts !== false) {
                return date('M d, Y', $ts);
            }

            return trim($date);
        }

        return date('M d, Y');
    }

    private static function trimImage($img)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $top = $height;
        $left = $width;
        $bottom = 0;
        $right = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha < 120) {
                    if ($x < $left) {
                        $left = $x;
                    }
                    if ($x > $right) {
                        $right = $x;
                    }
                    if ($y < $top) {
                        $top = $y;
                    }
                    if ($y > $bottom) {
                        $bottom = $y;
                    }
                }
            }
        }

        if ($right <= $left || $bottom <= $top) {
            return $img;
        }

        $newWidth = $right - $left + 1;
        $newHeight = $bottom - $top + 1;
        $trimmed = imagecreatetruecolor($newWidth, $newHeight);
        imagesavealpha($trimmed, true);
        $transparent = imagecolorallocatealpha($trimmed, 0, 0, 0, 127);
        imagefill($trimmed, 0, 0, $transparent);
        imagecopy($trimmed, $img, 0, 0, $left, $top, $newWidth, $newHeight);
        imagedestroy($img);

        return $trimmed;
    }

    /**
     * Append a very small date under the signature so a plain copy is incomplete.
     */
    public static function stampDate($img, string $date)
    {
        $width = imagesx($img);
        $height = imagesy($img);
        $font = 1; // smallest built-in GD font
        $textHeight = imagefontheight($font);
        $pad = 1;
        $newHeight = $height + $textHeight + ($pad * 2);
        $new = imagecreatetruecolor($width, $newHeight);
        imagesavealpha($new, true);
        $transparent = imagecolorallocatealpha($new, 0, 0, 0, 127);
        imagefill($new, 0, 0, $transparent);
        imagecopy($new, $img, 0, 0, 0, 0, $width, $height);
        $textColor = imagecolorallocate($new, 55, 55, 55);
        $textWidth = imagefontwidth($font) * strlen($date);
        $x = max(0, (int) (($width - $textWidth) / 2));
        imagestring($new, $font, $x, $height + $pad, $date, $textColor);
        imagedestroy($img);

        return $new;
    }
}
