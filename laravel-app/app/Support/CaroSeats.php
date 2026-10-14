<?php

namespace App\Support;

class CaroSeats
{
    const COLUMNS = ['A', 'B', 'C', 'D'];

    const ROWS = 20;

    const PER_ROW = 8;

    const PER_COLUMN = 160;

    public static function roles()
    {
        return [
            'guest' => 'Guest',
            'pastor' => 'Rev. / Pastor',
            'guest_minister' => 'Guest Minister',
            'gospel_minister' => 'Gospel Minister',
        ];
    }

    public static function roleLabel($role)
    {
        $roles = self::roles();

        return isset($roles[$role]) ? $roles[$role] : 'Guest';
    }

    public static function code($column, $row, $chair)
    {
        $number = (($row - 1) * self::PER_ROW) + $chair;

        return $column.$number;
    }

    /**
     * Full seat address, for example B-01-02.
     */
    public static function label($code)
    {
        $seat = self::parse($code);
        if (! $seat) {
            return strtoupper(trim((string) $code));
        }

        return $seat['column'].'-'.sprintf('%02d', $seat['row']).'-'.sprintf('%02d', $seat['chair']);
    }

    /**
     * Accept B-01-02 or the stored code B10.
     */
    public static function normalize($value)
    {
        $value = strtoupper(trim((string) $value));
        if (preg_match('/^([A-D])-(\d{1,2})-(\d{1,2})$/', $value, $match)) {
            $row = (int) $match[2];
            $chair = (int) $match[3];
            if ($row < 1 || $row > self::ROWS || $chair < 1 || $chair > self::PER_ROW) {
                return null;
            }

            return self::code($match[1], $row, $chair);
        }
        $seat = self::parse($value);

        return $seat ? $seat['code'] : null;
    }

    public static function eventDay()
    {
        return '2026-11-08';
    }

    /**
     * Caroline, the host shown at the center of the flyer.
     */
    public static function hostPhone()
    {
        return '+237671147099';
    }

    public static function isEventDay()
    {
        return \Carbon\Carbon::now('Africa/Douala')->toDateString() === self::eventDay();
    }

    public static function parse($code)
    {
        $code = strtoupper(trim((string) $code));
        if (! preg_match('/^([A-D])([0-9]{1,3})$/', $code, $match)) {
            return null;
        }
        $number = (int) $match[2];
        if ($number < 1 || $number > self::PER_COLUMN) {
            return null;
        }
        $row = (int) ceil($number / self::PER_ROW);
        $chair = (($number - 1) % self::PER_ROW) + 1;

        return [
            'code' => $match[1].$number,
            'column' => $match[1],
            'number' => $number,
            'row' => $row,
            'chair' => $chair,
        ];
    }

    /**
     * Which role owns this seat. Guest seats return "guest".
     */
    public static function ownerRole($code)
    {
        $seat = self::parse($code);
        if (! $seat) {
            return null;
        }
        if ($seat['column'] === 'B' && $seat['row'] === 1) {
            return 'guest_minister';
        }
        if ($seat['column'] === 'B' && ($seat['row'] === 2 || $seat['row'] === 3)) {
            return 'pastor';
        }
        if ($seat['column'] === 'C' && $seat['row'] <= 3) {
            return 'gospel_minister';
        }

        return 'guest';
    }

    /**
     * Seats removed for the front-of-house desk.
     * B rows 10–11, chairs 4–8 and C rows 10–11, chairs 1–5.
     */
    public static function removed($code)
    {
        $seat = self::parse($code);
        if (! $seat) {
            return true;
        }
        if ($seat['column'] === 'B' && ($seat['row'] === 10 || $seat['row'] === 11) && $seat['chair'] >= 4) {
            return true;
        }
        if ($seat['column'] === 'C' && ($seat['row'] === 10 || $seat['row'] === 11) && $seat['chair'] <= 5) {
            return true;
        }

        return false;
    }

    public static function allowed($role, $code)
    {
        if (self::removed($code)) {
            return false;
        }

        return self::ownerRole($code) === $role;
    }

    /**
     * First open seat from the front, left to right.
     */
    public static function firstOpen($role, array $taken)
    {
        $takenMap = array_fill_keys($taken, true);
        for ($row = 1; $row <= self::ROWS; $row++) {
            foreach (self::COLUMNS as $column) {
                for ($chair = 1; $chair <= self::PER_ROW; $chair++) {
                    $code = self::code($column, $row, $chair);
                    if (self::allowed($role, $code) && empty($takenMap[$code])) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    public static function executive($code)
    {
        $owner = self::ownerRole($code);

        return $owner && $owner !== 'guest';
    }

    /**
     * Rows this role is shown. Guests see the whole hall.
     *
     * @return array<int, int>
     */
    public static function rowsFor($role)
    {
        if ($role === 'guest_minister') {
            return [1];
        }
        if ($role === 'pastor') {
            return [2, 3];
        }
        if ($role === 'gospel_minister') {
            return [1, 2, 3];
        }

        return range(1, self::ROWS);
    }

    /**
     * Columns highlighted for a reserved role. Guests see every column.
     *
     * @return array<int, string>
     */
    public static function columnsFor($role)
    {
        if ($role === 'guest_minister' || $role === 'pastor') {
            return ['B'];
        }
        if ($role === 'gospel_minister') {
            return ['C'];
        }

        return self::COLUMNS;
    }
}
