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

    public static function allowed($role, $code)
    {
        return self::ownerRole($code) === $role;
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
