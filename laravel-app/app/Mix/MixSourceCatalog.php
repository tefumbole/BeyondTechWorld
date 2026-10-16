<?php

namespace App\Mix;

/**
 * Review rows for the bundled Beyond preset PDFs.
 * status ok = printed text is unambiguous.
 * status conflict = screenshot-only, garbled text, or two printed values disagree.
 * status missing = the document does not show the value.
 * Nothing here is written onto a preset until an admin confirms the row.
 */
class MixSourceCatalog
{
    public static function files()
    {
        return [
            'Kick.pdf' => ['kick-out', 'kick-in'],
            'Snare_Top.pdf' => ['snare-top'],
            'Snare_Bottom.pdf' => ['snare-bottom'],
            'Tom_1.pdf' => ['tom-1'],
            'Tom_2.pdf' => ['tom-2'],
            'Tom_3.pdf' => ['tom-3'],
            'Bass_Bus.pdf' => ['bass-bus'],
        ];
    }

    public static function presets()
    {
        return [
            'kick-out' => self::kickOut(),
            'kick-in' => self::kickIn(),
            'snare-top' => self::snareTop(),
            'snare-bottom' => self::snareBottom(),
            'tom-1' => self::tom('Tom 1', 'tom-1', '11', '108', [
                ['168.8', '7.86', '1.35'],
                ['362.3', '-7.56', '0.98'],
                ['1000', '-5.10', '1.51'],
                ['5060', '9.48', '2.34'],
            ], self::dyn('compressor', '-28.67', '4.0', '10.1', '204', '5.21'), self::dyn('gate', '-35.37', null, '0.33', '222', null, '-40.17')),
            'tom-2' => self::tom('Tom 2', 'tom-2', '10', '84', [
                ['120.7', '4.86', '1.65'],
                ['265.6', '-7.02', '1.69'],
                ['551.1', '-4.68', '0.84'],
                ['5060', '9.60', '1.55'],
            ], self::dyn('compressor', '-28.90', '4.0', '11.5', '197', '5.21'), self::dyn('gate', '-24.86', null, '0.27', '281', null, '-40.63')),
            'tom-3' => self::tom3(),
            'bass-bus' => self::bassBus(),
        ];
    }

    protected static function kickOut()
    {
        $rows = [
            self::row('Kick Out', 'kick-out', 'input.preamp_gain_db', '19', 'ok', 'Printed as Pre: 19 dB.'),
            self::row('Kick Out', 'kick-out', 'input.hpf_hz', '30', 'ok', 'Printed as HPF: 30 Hz.'),
            self::row('Kick Out', 'kick-out', 'input.hpf_position', 'post', 'ok', 'Printed as Post above the HPF.'),
        ];
        $rows = array_merge($rows, self::bands('Kick Out', 'kick-out', [
            ['54.46', '5.40', '1.2'],
            ['217.0', '-11.52', '0.73'],
            ['539.3', '-8.58', '1.28'],
            ['3080', '13.32', '0.88'],
        ], 'ok', 'Printed in the Kick Out equalization section.'));
        $rows = array_merge($rows, self::namedDyn('Kick Out', 'kick-out', 'compressor', '-21.39', '4.2', '12.1', '315', '7.00'));
        $rows = array_merge($rows, self::namedDyn('Kick Out', 'kick-out', 'gate', '-16.93', null, '0.24', '700', null, '-42.45'));
        $rows[] = self::row('Kick Out', 'kick-out', 'compressor.knee', null, 'missing', 'The Soft and Auto buttons are both visible. Neither is clearly selected.');
        $rows[] = self::row('Kick Out', 'kick-out', 'input.lpf_hz', null, 'missing', 'No low-pass value is printed.');

        return $rows;
    }

    protected static function kickIn()
    {
        $rows = [
            self::row('Kick In', 'kick-in', 'input.hpf_hz', '1000', 'conflict', 'The text prints "HPF: 1000H", which is not a valid unit. The screenshot knob reads 1000 Hz. Confirm before use.'),
            self::row('Kick In', 'kick-in', 'input.preamp_gain_db', '17', 'conflict', 'Pre 17 dB is on the screenshot knob. The text block does not print this gain.'),
            self::row('Kick In', 'kick-in', 'input.delay_ms', '0.0', 'conflict', 'Delay 0.0 ms is on the screenshot. It is not in the text export.'),
            self::row('Kick In', 'kick-in', 'input.hpf_position', 'post', 'conflict', 'The text says Post. Confirm it against the channel strip.'),
        ];
        $rows = array_merge($rows, self::bands('Kick In', 'kick-in', [
            ['130.0', '-0.30', '0.60'],
            ['320.0', '0.00', '0.60'],
            ['1570', '-9.90', '0.60'],
            ['8970', '6.24', '0.91'],
        ], 'ok', 'Printed in the Kick In equalization section.'));
        $rows = array_merge($rows, self::namedDyn('Kick In', 'kick-in', 'compressor', '-41.55', '4.11', '10.4', '75.7', '6.55'));
        $rows = array_merge($rows, self::namedDyn('Kick In', 'kick-in', 'gate', '-30.24', null, '0.13', '84.2', null, '-43.80'));

        return $rows;
    }

    protected static function snareTop()
    {
        $rows = [
            self::row('Snare Top', 'snare-top', 'input.hpf_hz', null, 'conflict', 'Three values appear: header text 80 Hz, screenshot knob 109 Hz, and the equalization section 30 Hz. None is selected automatically.'),
            self::row('Snare Top', 'snare-top', 'input.preamp_gain_db', null, 'conflict', 'Header and knob show 13 dB. The equalization section prints Pre: 19 dB.'),
            self::row('Snare Top', 'snare-top', 'input.delay_ms', '0.0', 'conflict', 'Delay 0.0 ms is on the screenshot only.'),
            self::row('Snare Top', 'snare-top', 'effect.reverb.type', 'Concert Hall', 'ok', 'Printed as Concert Hall Reverb. Send and return levels are not printed.'),
            self::row('Snare Top', 'snare-top', 'effect.reverb.send_level', null, 'missing', 'No send level is printed.'),
            self::row('Snare Top', 'snare-top', 'effect.reverb.return_level', null, 'missing', 'No return level is printed.'),
        ];
        $rows = array_merge($rows, self::bands('Snare Top', 'snare-top', [
            ['248.1', '6.54', '1.59'],
            ['470.4', '-6.96', '1.38'],
            ['1090', '-5.70', '1.11'],
            ['5000', '5.58', '0.60'],
        ], 'ok', 'Printed in the equalization section.'));
        $rows = array_merge($rows, self::namedDyn('Snare Top', 'snare-top', 'compressor', '-32.82', '2.0', '20.7', '150', '1.51'));
        $rows[] = self::row('Snare Top', 'snare-top', 'gate.threshold_db', '-52.75', 'conflict', 'Screenshot only. Expander is on and range is labeled auto, so range is left empty.');
        $rows[] = self::row('Snare Top', 'snare-top', 'gate.attack_ms', '0.28', 'conflict', 'Screenshot only.');
        $rows[] = self::row('Snare Top', 'snare-top', 'gate.release_ms', '700', 'conflict', 'Screenshot only.');
        $rows[] = self::row('Snare Top', 'snare-top', 'gate.expander_mode', 'auto', 'conflict', 'Screenshot shows Expander engaged and Expander auto. No range in dB is shown.');
        $rows[] = self::row('Snare Top', 'snare-top', 'gate.range_db', null, 'missing', 'No gate range in dB is shown.');

        return $rows;
    }

    protected static function snareBottom()
    {
        $rows = [
            self::row('Snare Bottom', 'snare-bottom', 'input.hpf_hz', '180', 'ok', 'Printed as HPF: 180 and shown on the high-pass knob as 180 Hz.'),
            self::row('Snare Bottom', 'snare-bottom', 'input.preamp_gain_db', '13', 'ok', 'Printed as Pre: 13db.'),
            self::row('Snare Bottom', 'snare-bottom', 'effect.reverb.type', 'Concert Hall', 'ok', 'Printed as Concert Hall Reverb and again as Reverb: Concert Hall.'),
            self::row('Snare Bottom', 'snare-bottom', 'effect.reverb.send_level', null, 'missing', 'No send level is printed.'),
        ];
        $rows = array_merge($rows, self::bands('Snare Bottom', 'snare-bottom', [
            ['120.0', '0.00', '0.57', 'lowshelf'],
            ['250.0', '-3.00', '1.00'],
            ['3500', '2.00', '1.20'],
            ['8000', '3.00', '0.70', 'highshelf'],
        ], 'conflict', 'These frequencies are on the equalizer screenshot. The text export has no EQ numbers. Confirm each band against the PDF.'));
        $rows = array_merge($rows, self::namedDyn('Snare Bottom', 'snare-bottom', 'compressor', '-25.42', '2.7', null, null, '3.55'));
        $rows[] = self::row('Snare Bottom', 'snare-bottom', 'gate.threshold_db', '-43.01', 'conflict', 'Screenshot only.');
        $rows[] = self::row('Snare Bottom', 'snare-bottom', 'gate.attack_ms', '5.00', 'conflict', 'Screenshot only.');
        $rows[] = self::row('Snare Bottom', 'snare-bottom', 'gate.release_ms', '700', 'conflict', 'Screenshot only.');
        $rows[] = self::row('Snare Bottom', 'snare-bottom', 'gate.expander_mode', 'auto', 'conflict', 'Expander is engaged. Range in dB is not shown.');
        $rows[] = self::row('Snare Bottom', 'snare-bottom', 'gate.range_db', null, 'missing', 'No gate range in dB is shown.');

        return $rows;
    }

    protected static function tom($name, $slug, $pre, $hpf, array $bands, array $compressor, array $gate)
    {
        $rows = [
            self::row($name, $slug, 'input.preamp_gain_db', $pre, 'ok', 'Printed as Pre.'),
            self::row($name, $slug, 'input.hpf_hz', $hpf, 'ok', 'Printed as HPF.'),
        ];
        $rows = array_merge($rows, self::bands($name, $slug, $bands, 'ok', 'Printed in the equalization section.'));
        foreach (array_merge($compressor, $gate) as $row) {
            $row['preset_name'] = $name;
            $row['preset_key'] = $slug;
            $row['instrument_slug'] = $slug;
            $rows[] = $row;
        }

        return $rows;
    }

    protected static function tom3()
    {
        $rows = self::tom('Tom 3', 'tom-3', '17', '65', [
            ['68.97', '8.16', '1.65'],
            ['265.6', null, '1.69'],
            ['551.1', null, '0.84'],
            ['5060', '6.00', '1.55'],
        ], self::dyn('compressor', '-21.73', '4.0', '0.37', '472', '3.30'), self::dyn('gate', '-28.06', null, '0.27', '292', null, '-40.63'));
        $rows[] = self::row('Tom 3', 'tom-3', 'effect.reverb.type', 'Concert Hall', 'ok', 'Printed as FX: Concert Hall. Send and return are not printed. Low-Mid and High-Mid gains print as "--4.68 dB" and are left blank.');
        $rows[] = self::row('Tom 3', 'tom-3', 'compressor.knee', 'soft', 'conflict', 'The Soft button is highlighted on the compressor screenshot.');

        return $rows;
    }

    protected static function bassBus()
    {
        return [
            self::row('Bass Bus', 'bass-bus', 'input.hpf_hz', null, 'missing', 'The HPF knob is visible and no frequency is printed.'),
            self::row('Bass Bus', 'bass-bus', 'eq.passive.low_boost', '1.75', 'conflict', 'Passive EQ screenshot. This is a boost amount, not a frequency.'),
            self::row('Bass Bus', 'bass-bus', 'eq.passive.low_atten', '1.00', 'conflict', 'Passive EQ screenshot.'),
            self::row('Bass Bus', 'bass-bus', 'eq.passive.bandwidth', '8.00', 'conflict', 'Passive EQ screenshot. Bandwidth is not a standard parametric Q.'),
            self::row('Bass Bus', 'bass-bus', 'eq.passive.high_boost', '2.50', 'conflict', 'Passive EQ screenshot.'),
            self::row('Bass Bus', 'bass-bus', 'eq.passive.high_atten', '0.50', 'conflict', 'Passive EQ screenshot.'),
            self::row('Bass Bus', 'bass-bus', 'eq.1.frequency_hz', null, 'missing', 'The passive EQ does not print a selected frequency for a parametric band.'),
            self::row('Bass Bus', 'bass-bus', 'gate.threshold_db', '-54.89', 'conflict', 'Screenshot only.'),
            self::row('Bass Bus', 'bass-bus', 'gate.attack_ms', '5.00', 'conflict', 'Screenshot only.'),
            self::row('Bass Bus', 'bass-bus', 'gate.release_ms', '100', 'conflict', 'Screenshot only.'),
            self::row('Bass Bus', 'bass-bus', 'gate.expander_mode', 'auto', 'conflict', 'Expander auto is shown. Range in dB is not shown.'),
            self::row('Bass Bus', 'bass-bus', 'gate.range_db', null, 'missing', 'No gate range in dB is shown.'),
            self::row('Bass Bus', 'bass-bus', 'compressor.model', 'TubeCOMP', 'conflict', 'The strip is a TubeCOMP, not a standard threshold/ratio compressor.'),
            self::row('Bass Bus', 'bass-bus', 'compressor.extra.gain', '53.00', 'conflict', 'TubeCOMP gain. It is not makeup gain and is not copied into makeup gain.'),
            self::row('Bass Bus', 'bass-bus', 'compressor.extra.peak_reduction', '57.00', 'conflict', 'TubeCOMP peak reduction. Threshold is not invented from this number.'),
            self::row('Bass Bus', 'bass-bus', 'compressor.extra.key_filter_hz', '2730', 'conflict', 'Key filter reads 2.73 kHz on the TubeCOMP.'),
            self::row('Bass Bus', 'bass-bus', 'compressor.threshold_db', null, 'missing', 'TubeCOMP does not show a threshold in dB.'),
            self::row('Bass Bus', 'bass-bus', 'compressor.ratio', null, 'missing', 'TubeCOMP does not show a ratio.'),
            self::row('Bass Bus', 'bass-bus', 'limiter.threshold_db', '-1.00', 'conflict', 'Limiter screenshot, labeled Thresh.'),
            self::row('Bass Bus', 'bass-bus', 'limiter.release_ms', '300', 'conflict', 'Limiter screenshot.'),
        ];
    }

    protected static function bands($name, $slug, array $bands, $status, $note)
    {
        $rows = [];
        $i = 1;
        foreach ($bands as $band) {
            $filter = isset($band[3]) ? $band[3] : 'bell';
            $rows[] = self::row($name, $slug, 'eq.'.$i.'.filter_type', $filter, $status, $note);
            $rows[] = self::row($name, $slug, 'eq.'.$i.'.frequency_hz', $band[0], $status, $note);
            $rows[] = self::row($name, $slug, 'eq.'.$i.'.gain_db', $band[1], $band[1] === null ? 'conflict' : $status, $note);
            $rows[] = self::row($name, $slug, 'eq.'.$i.'.q', $band[2], $status, $note);
            $i++;
        }

        return $rows;
    }

    protected static function namedDyn($name, $slug, $kind, $threshold, $ratio, $attack, $release, $makeup, $range = null)
    {
        $rows = self::dyn($kind, $threshold, $ratio, $attack, $release, $makeup, $range);
        foreach ($rows as $index => $row) {
            $rows[$index]['preset_name'] = $name;
            $rows[$index]['preset_key'] = $slug;
            $rows[$index]['instrument_slug'] = $slug;
        }

        return $rows;
    }

    protected static function dyn($kind, $threshold, $ratio, $attack, $release, $makeup, $range = null)
    {
        $note = 'Read from the '.$kind.' screenshot. The text export does not include these numbers.';

        return [
            self::row('', '', $kind.'.threshold_db', $threshold, 'conflict', $note),
            self::row('', '', $kind.'.ratio', $ratio, $ratio === null ? 'missing' : 'conflict', $note),
            self::row('', '', $kind.'.attack_ms', $attack, $attack === null ? 'missing' : 'conflict', $note),
            self::row('', '', $kind.'.release_ms', $release, $release === null ? 'missing' : 'conflict', $note),
            self::row('', '', $kind.'.makeup_db', $makeup, $makeup === null ? 'missing' : 'conflict', $note),
            self::row('', '', $kind.'.range_db', $range, $range === null ? 'missing' : 'conflict', $note),
            self::row('', '', $kind.'.key_source', 'None', 'conflict', $note),
            self::row('', '', $kind.'.key_filter', 'off', 'conflict', $note),
        ];
    }

    protected static function row($name, $slug, $field, $value, $status, $note)
    {
        return [
            'preset_name' => $name,
            'preset_key' => $slug,
            'instrument_slug' => $slug,
            'field' => $field,
            'extracted_value' => $value,
            'status' => $status,
            'note' => $note,
        ];
    }
}
