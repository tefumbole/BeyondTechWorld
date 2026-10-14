<?php

namespace App\Services;

use App\CaroInvitation;
use App\Jobs\SendCaroInvitationJob;
use App\Support\CaroSeats;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaroInvitationService
{
    const HOST = 'caro.beyondtechworld.com';

    public function onCaroHost()
    {
        return request()->getHost() === self::HOST;
    }

    public function pageUrl($path = '')
    {
        $path = trim((string) $path, '/');
        if ($this->onCaroHost()) {
            return $path === '' ? url('/') : url('/'.$path);
        }

        return $path === '' ? url('/caro') : url('/caro/'.$path);
    }

    public function passUrl($id)
    {
        return rtrim((string) config('app.url'), '/').'/caro/pass/'.$id;
    }

    public function findByPhone($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }

        return CaroInvitation::where('phone_digits', $digits)->first();
    }

    public function takenCodes()
    {
        return CaroInvitation::query()->pluck('seat_code')->all();
    }

    /**
     * Claim a seat and build the card. The same phone keeps the seat it already has.
     */
    public function claim($phone, $name, $role, $seatCode)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        $seatCode = strtoupper(trim((string) $seatCode));
        if (! CaroSeats::allowed($role, $seatCode)) {
            throw new \InvalidArgumentException('That seat is not open for this invitation.');
        }

        try {
            $row = DB::transaction(function () use ($phone, $digits, $name, $role, $seatCode) {
                $existing = CaroInvitation::where('phone_digits', $digits)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
                $taken = CaroInvitation::where('seat_code', $seatCode)->lockForUpdate()->first();
                if ($taken) {
                    throw new \InvalidArgumentException('That seat was just taken. Choose another.');
                }

                $id = (string) Str::uuid();
                $created = new CaroInvitation([
                    'id' => $id,
                    'phone' => $phone,
                    'phone_digits' => $digits,
                    'name' => $name,
                    'role' => $role,
                    'seat_code' => $seatCode,
                    'image_file' => $id.'.jpg',
                ]);
                $created->save();

                return $created;
            });
        } catch (QueryException $e) {
            $row = $this->findByPhone($phone);
            if (! $row) {
                throw new \InvalidArgumentException('That seat was just taken. Choose another.');
            }
        }

        if (! is_file($row->imagePath())) {
            try {
                $this->ensureOutDir();
                $this->compose($row);
            } catch (\Throwable $e) {
                $row->delete();
                throw $e;
            }
            SendCaroInvitationJob::dispatch($row->id)->onQueue('whatsapp');
        }

        return $row;
    }

    /**
     * Mark the guest present on the event day and thank them once.
     */
    public function markPresent(CaroInvitation $row)
    {
        if (! CaroSeats::isEventDay()) {
            throw new \InvalidArgumentException('Present is only available on 8 November 2026.');
        }

        $shouldThank = false;
        DB::transaction(function () use ($row, &$shouldThank) {
            $fresh = CaroInvitation::where('id', $row->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->presented_at) {
                return;
            }
            $fresh->presented_at = \Carbon\Carbon::now('Africa/Douala');
            $fresh->save();
            $shouldThank = empty($fresh->thanks_sent_at);
            $row->presented_at = $fresh->presented_at;
        });

        if ($shouldThank) {
            \App\Jobs\SendCaroThanksJob::dispatch($row->id)->onQueue('whatsapp');
        }

        return $shouldThank;
    }

    public function retakeSeat(CaroInvitation $row, $seatCode)
    {
        $seatCode = CaroSeats::normalize($seatCode);
        if (! $seatCode || CaroSeats::removed($seatCode) || ! CaroSeats::ownerRole($seatCode)) {
            throw new \InvalidArgumentException('That seat address is not in the hall.');
        }

        DB::transaction(function () use ($row, $seatCode) {
            $fresh = CaroInvitation::where('id', $row->id)->lockForUpdate()->first();
            if (! $fresh) {
                throw new \InvalidArgumentException('That invitation is no longer here.');
            }
            $taken = CaroInvitation::where('seat_code', $seatCode)->where('id', '!=', $fresh->id)->lockForUpdate()->first();
            if ($taken) {
                throw new \InvalidArgumentException('That seat is already assigned.');
            }
            $fresh->seat_code = $seatCode;
            $fresh->role = CaroSeats::ownerRole($seatCode);
            $fresh->save();
            $row->seat_code = $fresh->seat_code;
            $row->role = $fresh->role;
        });

        $this->ensureOutDir();
        $this->compose($row);

        return $row;
    }

    public function compose(CaroInvitation $row)
    {
        $template = public_path('caro/invitation.jpg');
        $img = @imagecreatefromjpeg($template);
        if (! $img) {
            throw new \RuntimeException('Invitation artwork could not be opened.');
        }
        $ink = imagecolorallocate($img, 48, 32, 12);
        $font = $this->fontPath();
        $this->drawCentered($img, $row->name, $font, 18, 136, 880, 500, 30, $ink);
        $this->drawCentered($img, CaroSeats::label($row->seat_code), $font, 13, 391, 926, 160, 28, $ink);

        $qrFile = $row->imagePath().'.qr.png';
        $this->writeQr($this->passUrl($row->id), $qrFile, 240);
        $qr = @imagecreatefrompng($qrFile);
        if ($qr) {
            imagecopyresampled($img, $qr, 677, 853, 0, 0, 90, 90, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
        @unlink($qrFile);

        imagejpeg($img, $row->imagePath(), 92);
        imagedestroy($img);
        if (! is_file($row->imagePath())) {
            throw new \RuntimeException('Invitation image was not saved.');
        }
    }

    protected function ensureOutDir()
    {
        $dir = public_path('caro/out');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    protected function fontPath()
    {
        $path = public_path('yong/fonts/LibreBaskerville-Bold.ttf');
        if (is_file($path)) {
            return $path;
        }
        $fallbacks = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf',
            '/System/Library/Fonts/Supplemental/Times New Roman Bold.ttf',
        ];
        foreach ($fallbacks as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    protected function drawCentered($img, $text, $font, $size, $boxX, $boxY, $boxW, $boxH, $color)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return;
        }
        if ($font && function_exists('imagettftext')) {
            while ($size > 8) {
                $bounds = imagettfbbox($size, 0, $font, $text);
                $width = abs($bounds[2] - $bounds[0]);
                if ($width <= $boxW - 8) {
                    break;
                }
                $size--;
            }
            $bounds = imagettfbbox($size, 0, $font, $text);
            $width = abs($bounds[2] - $bounds[0]);
            $height = abs($bounds[7] - $bounds[1]);
            $x = (int) ($boxX + (($boxW - $width) / 2));
            $y = (int) ($boxY + (($boxH + $height) / 2) - 1);
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);

            return;
        }
        $x = (int) ($boxX + max(0, ($boxW - (strlen($text) * 8)) / 2));
        imagestring($img, 3, $x, $boxY + 4, substr($text, 0, 28), $color);
    }

    protected function writeQr($text, $dest, $size)
    {
        $encoded = Encoder::encode($text, ErrorCorrectionLevel::M());
        $matrix = $encoded->getMatrix();
        $modules = $matrix->getWidth();
        $scale = (int) max(1, floor($size / $modules));
        $px = $modules * $scale;
        $qr = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($qr, 255, 255, 255);
        $black = imagecolorallocate($qr, 0, 0, 0);
        imagefill($qr, 0, 0, $white);
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle(
                        $qr,
                        $x * $scale,
                        $y * $scale,
                        (($x + 1) * $scale) - 1,
                        (($y + 1) * $scale) - 1,
                        $black
                    );
                }
            }
        }
        imagepng($qr, $dest);
        imagedestroy($qr);
    }
}
