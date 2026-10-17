<?php

namespace App\Gbhs;

class GbhsHistory
{
    /**
     * Figures recorded by the association. They are imported unverified.
     * The 152,000 extra for 1991 is a reversal. Fabric lines keep quantity
     * and stated value apart from cash.
     */
    public static function rows()
    {
        $cash = function ($year, $amount, $note, $class = 'cash') {
            return [
                'year' => $year,
                'name' => null,
                'classification' => $class,
                'amount' => $amount,
                'qty' => null,
                'value' => null,
                'note' => $note,
            ];
        };
        $rows = [
            $cash(1976, 800000, 'Batch 1976'),
            $cash(1977, 450000, 'Batch 1977'),
            ['year' => 1977, 'name' => null, 'classification' => 'in_kind', 'amount' => 0, 'qty' => 1, 'value' => 10000, 'note' => 'Fabric'],
            $cash(1978, 425000, 'Batch 1978'),
            ['year' => 1978, 'name' => null, 'classification' => 'in_kind', 'amount' => 0, 'qty' => 2, 'value' => 20000, 'note' => 'Fabric'],
            $cash(1979, 520000, 'Batch 1979'),
            $cash(1979, 70000, 'Batch 1979 additional'),
            $cash(1980, 625000, 'Batch 1980'),
            $cash(1981, 625000, 'Batch 1981, on request', 'earmarked'),
            $cash(1981, 5000, 'Batch 1981 logistics', 'logistics'),
            $cash(1982, 200000, 'Batch 1982'),
            $cash(1984, 300000, 'Batch 1984'),
            $cash(1985, 308000, 'Batch 1985'),
            $cash(1986, 825000, 'Batch 1986'),
            $cash(1987, 150000, 'Batch 1987'),
            $cash(1988, 75000, 'Batch 1988'),
            $cash(1988, 360000, 'Batch 1988 additional'),
            $cash(1991, 562000, 'Batch 1991'),
            $cash(1991, 152000, '1991 extra, recorded as a reversal', 'reversal'),
            $cash(1992, 350000, 'Batch 1992'),
            $cash(1993, 850000, 'Batch 1993'),
            $cash(1993, 150000, 'Batch 1993 stated for fabric', 'earmarked'),
            $cash(1994, 375000, 'Batch 1994'),
            $cash(1998, 25000, 'Batch 1998'),
            $cash(2005, 190000, 'Batch 2005'),
            $cash(2013, 75000, 'Batch 2013'),
            $cash(0, 76000, 'Unknown batch'),
            ['year' => 0, 'name' => null, 'classification' => 'in_kind', 'amount' => 0, 'qty' => 3, 'value' => 30000, 'note' => 'Unspecified fabrics'],
        ];
        foreach ([
            'Sama N' => 25000,
            'Oliver' => 25000,
            'Shey Kosho' => 50000,
            'Barfee' => 5000,
            'Kimbi' => 50000,
            'Sakah L' => 50000,
            'Langdji' => 15000,
        ] as $name => $amount) {
            $rows[] = [
                'year' => 0,
                'name' => $name,
                'classification' => 'cash',
                'amount' => $amount,
                'qty' => null,
                'value' => null,
                'note' => 'Named contributor',
            ];
        }

        return $rows;
    }
}
