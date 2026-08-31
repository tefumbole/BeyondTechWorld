<?php

namespace App\Support;

class CountryDialCodes
{
    /** Broad international dial codes for public forms. */
    public static function all()
    {
        return [
            '+237' => 'Cameroon (+237)',
            '+250' => 'Rwanda (+250)',
            '+256' => 'Uganda (+256)',
            '+254' => 'Kenya (+254)',
            '+255' => 'Tanzania (+255)',
            '+243' => 'DR Congo (+243)',
            '+242' => 'Congo (+242)',
            '+235' => 'Chad (+235)',
            '+236' => 'Central African Rep. (+236)',
            '+241' => 'Gabon (+241)',
            '+234' => 'Nigeria (+234)',
            '+233' => 'Ghana (+233)',
            '+225' => 'Côte d\'Ivoire (+225)',
            '+221' => 'Senegal (+221)',
            '+212' => 'Morocco (+212)',
            '+213' => 'Algeria (+213)',
            '+216' => 'Tunisia (+216)',
            '+20' => 'Egypt (+20)',
            '+27' => 'South Africa (+27)',
            '+251' => 'Ethiopia (+251)',
            '+249' => 'Sudan (+249)',
            '+211' => 'South Sudan (+211)',
            '+1' => 'USA/Canada (+1)',
            '+44' => 'United Kingdom (+44)',
            '+33' => 'France (+33)',
            '+49' => 'Germany (+49)',
            '+32' => 'Belgium (+32)',
            '+31' => 'Netherlands (+31)',
            '+41' => 'Switzerland (+41)',
            '+39' => 'Italy (+39)',
            '+34' => 'Spain (+34)',
            '+351' => 'Portugal (+351)',
            '+91' => 'India (+91)',
            '+86' => 'China (+86)',
            '+81' => 'Japan (+81)',
            '+82' => 'South Korea (+82)',
            '+61' => 'Australia (+61)',
            '+64' => 'New Zealand (+64)',
            '+55' => 'Brazil (+55)',
            '+52' => 'Mexico (+52)',
            '+971' => 'UAE (+971)',
            '+966' => 'Saudi Arabia (+966)',
            '+974' => 'Qatar (+974)',
            '+965' => 'Kuwait (+965)',
            '+973' => 'Bahrain (+973)',
            '+968' => 'Oman (+968)',
            '+90' => 'Turkey (+90)',
            '+7' => 'Russia/Kazakhstan (+7)',
            '+380' => 'Ukraine (+380)',
            '+48' => 'Poland (+48)',
            '+46' => 'Sweden (+46)',
            '+47' => 'Norway (+47)',
            '+45' => 'Denmark (+45)',
            '+358' => 'Finland (+358)',
            '+353' => 'Ireland (+353)',
            '+43' => 'Austria (+43)',
            '+36' => 'Hungary (+36)',
            '+420' => 'Czechia (+420)',
            '+40' => 'Romania (+40)',
            '+30' => 'Greece (+30)',
            '+972' => 'Israel (+972)',
            '+92' => 'Pakistan (+92)',
            '+880' => 'Bangladesh (+880)',
            '+63' => 'Philippines (+63)',
            '+62' => 'Indonesia (+62)',
            '+60' => 'Malaysia (+60)',
            '+65' => 'Singapore (+65)',
            '+66' => 'Thailand (+66)',
            '+84' => 'Vietnam (+84)',
            '+852' => 'Hong Kong (+852)',
            '+886' => 'Taiwan (+886)',
            '+54' => 'Argentina (+54)',
            '+56' => 'Chile (+56)',
            '+57' => 'Colombia (+57)',
            '+58' => 'Venezuela (+58)',
            '+51' => 'Peru (+51)',
            '+593' => 'Ecuador (+593)',
        ];
    }

    public static function combine($code, $number)
    {
        $codeDigits = preg_replace('/\D/', '', (string) $code);
        $digits = preg_replace('/\D/', '', (string) $number);
        if ($digits !== '' && $digits[0] === '0') {
            $digits = ltrim($digits, '0');
        }

        if ($codeDigits !== '' && $digits !== '' && strpos($digits, $codeDigits) === 0) {
            $rest = substr($digits, strlen($codeDigits));
            if (strlen($rest) >= 7) {
                $digits = $rest;
            }
        }

        // A local field polluted with default Cameroon (+237) must not beat the selected country.
        if ($codeDigits !== '' && $codeDigits !== '237' && strpos($digits, '237') === 0) {
            $rest = substr($digits, 3);
            if (strlen($rest) >= 7 && strlen($rest) <= 10) {
                $digits = $rest;
            }
        }

        if ($codeDigits === '') {
            $codeDigits = '237';
        }

        return '+'.$codeDigits.$digits;
    }

    /**
     * Split an E.164-ish number into [dial code, national digits].
     *
     * @return array{0:string,1:string}
     */
    public static function split($full)
    {
        $digits = preg_replace('/\D/', '', (string) $full);
        $codes = array_keys(self::all());
        usort($codes, function ($a, $b) {
            return strlen(preg_replace('/\D/', '', $b)) - strlen(preg_replace('/\D/', '', $a));
        });
        foreach ($codes as $code) {
            $prefix = preg_replace('/\D/', '', $code);
            if ($prefix !== '' && strpos($digits, $prefix) === 0) {
                return [$code, substr($digits, strlen($prefix))];
            }
        }

        return ['+237', ltrim($digits, '0')];
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function list()
    {
        $out = [];
        foreach (self::all() as $code => $label) {
            $out[] = ['code' => $code, 'label' => $label];
        }

        return $out;
    }
}
