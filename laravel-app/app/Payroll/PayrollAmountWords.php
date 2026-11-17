<?php

namespace App\Payroll;

class PayrollAmountWords
{
    public static function both($amount)
    {
        $amount = (int) round((float) $amount);

        return [
            'en' => self::english($amount).' CFA francs',
            'fr' => self::french($amount).' francs CFA',
        ];
    }

    public static function english($amount)
    {
        $amount = (int) $amount;
        if ($amount === 0) {
            return 'zero';
        }
        $parts = [];
        $millions = (int) floor($amount / 1000000);
        $amount %= 1000000;
        $thousands = (int) floor($amount / 1000);
        $rest = $amount % 1000;
        if ($millions) {
            $parts[] = self::englishUnder1000($millions).' million';
        }
        if ($thousands) {
            $parts[] = self::englishUnder1000($thousands).' thousand';
        }
        if ($rest) {
            $parts[] = self::englishUnder1000($rest);
        }

        return implode(' ', $parts);
    }

    public static function french($amount)
    {
        $amount = (int) $amount;
        if ($amount === 0) {
            return 'zéro';
        }
        $parts = [];
        $millions = (int) floor($amount / 1000000);
        $amount %= 1000000;
        $thousands = (int) floor($amount / 1000);
        $rest = $amount % 1000;
        if ($millions) {
            $parts[] = ($millions === 1 ? 'un million' : self::frenchUnder1000($millions).' millions');
        }
        if ($thousands) {
            $parts[] = ($thousands === 1 ? 'mille' : self::frenchUnder1000($thousands).' mille');
        }
        if ($rest) {
            $parts[] = self::frenchUnder1000($rest);
        }

        return implode(' ', $parts);
    }

    protected static function englishUnder1000($n)
    {
        $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
        $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
        $n = (int) $n;
        $words = '';
        if ($n >= 100) {
            $words .= $ones[(int) floor($n / 100)].' hundred';
            $n %= 100;
            if ($n) {
                $words .= ' ';
            }
        }
        if ($n >= 20) {
            $words .= $tens[(int) floor($n / 10)];
            if ($n % 10) {
                $words .= '-'.$ones[$n % 10];
            }
        } elseif ($n > 0) {
            $words .= $ones[$n];
        }

        return $words;
    }

    protected static function frenchUnder1000($n)
    {
        $ones = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        $n = (int) $n;
        $words = '';
        if ($n >= 100) {
            $hundreds = (int) floor($n / 100);
            $words .= $hundreds > 1 ? $ones[$hundreds].' cent' : 'cent';
            $n %= 100;
            if ($n === 0 && $hundreds > 1) {
                $words .= 's';
            }
            if ($n) {
                $words .= ' ';
            }
        }
        if ($n >= 80) {
            $rest = $n - 80;
            $words .= 'quatre-vingt'.($rest === 0 ? 's' : ($rest === 1 ? '-un' : '-'.$ones[$rest]));
        } elseif ($n >= 70) {
            $words .= 'soixante-'.($n === 71 ? 'et-onze' : $ones[$n - 60]);
        } elseif ($n >= 20) {
            $ten = (int) floor($n / 10);
            $rest = $n % 10;
            $words .= $tens[$ten];
            if ($rest === 1 && $ten < 8) {
                $words .= '-et-un';
            } elseif ($rest) {
                $words .= '-'.$ones[$rest];
            }
        } elseif ($n > 0) {
            $words .= $ones[$n];
        }

        return $words;
    }
}
