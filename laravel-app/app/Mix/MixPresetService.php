<?php

namespace App\Mix;

class MixPresetService
{
    public function save(MixPreset $preset, array $input, $userId = null)
    {
        $preset->name = trim((string) $input['name']);
        if (! $preset->exists || $preset->slug === '') {
            $preset->slug = MixSupport::uniqueSlug(MixPreset::class, $preset->name, $preset->exists ? $preset->id : null);
        }
        $preset->instrument_id = $input['instrument_id'] ?: null;
        $preset->console_id = $input['console_id'] ?: null;
        $preset->manufacturer_id = $input['manufacturer_id'] ?: null;
        $preset->collection = $input['collection'] === 'presonus' ? 'presonus' : 'beyond';
        $preset->source_label = MixSupport::text(isset($input['source_label']) ? $input['source_label'] : null);
        $preset->notes = MixSupport::text(isset($input['notes']) ? $input['notes'] : null);
        $preset->official_url = MixSupport::text(isset($input['official_url']) ? $input['official_url'] : null);
        $preset->save();

        $version = $this->writeVersion($preset, $input, $userId);
        $preset->current_version_id = $version->id;
        $preset->save();
        MixAuditLog::write('preset.version', $preset, 'Version '.$version->version_number);

        return $preset;
    }

    public function writeVersion(MixPreset $preset, array $input, $userId = null)
    {
        $number = (int) MixPresetVersion::where('preset_id', $preset->id)->max('version_number') + 1;
        $version = new MixPresetVersion();
        $version->preset_id = $preset->id;
        $version->version_number = $number;
        $version->label = MixSupport::text(isset($input['version_label']) ? $input['version_label'] : null) ?: 'Version '.$number;
        $version->notes = MixSupport::text(isset($input['version_notes']) ? $input['version_notes'] : null);
        $version->created_by = $userId;
        $rawInput = isset($input['input']) && is_array($input['input']) ? $input['input'] : [];
        $version->input_json = json_encode([
            'preamp_gain_db' => MixSupport::num(isset($rawInput['preamp_gain_db']) ? $rawInput['preamp_gain_db'] : null),
            'digital_trim_db' => MixSupport::num(isset($rawInput['digital_trim_db']) ? $rawInput['digital_trim_db'] : null),
            'hpf_hz' => MixSupport::num(isset($rawInput['hpf_hz']) ? $rawInput['hpf_hz'] : null),
            'lpf_hz' => MixSupport::num(isset($rawInput['lpf_hz']) ? $rawInput['lpf_hz'] : null),
            'hpf_position' => MixSupport::text(isset($rawInput['hpf_position']) ? $rawInput['hpf_position'] : null),
            'phantom' => ! empty($rawInput['phantom']),
            'polarity' => ! empty($rawInput['polarity']),
            'delay_ms' => MixSupport::num(isset($rawInput['delay_ms']) ? $rawInput['delay_ms'] : null),
        ]);
        $version->save();

        $bands = isset($input['eq']) && is_array($input['eq']) ? $input['eq'] : [];
        $index = 1;
        foreach ($bands as $band) {
            if (! is_array($band)) {
                continue;
            }
            $freq = MixSupport::num(isset($band['frequency_hz']) ? $band['frequency_hz'] : null);
            $gain = MixSupport::num(isset($band['gain_db']) ? $band['gain_db'] : null);
            $q = MixSupport::num(isset($band['q']) ? $band['q'] : null);
            if ($freq === null && $gain === null && $q === null) {
                continue;
            }
            $row = new MixEqBand();
            $row->version_id = $version->id;
            $row->band_index = $index++;
            $row->enabled = ! isset($band['enabled']) || (string) $band['enabled'] !== '0';
            $type = isset($band['filter_type']) ? $band['filter_type'] : 'bell';
            $row->filter_type = in_array($type, ['bell', 'lowshelf', 'highshelf', 'hpf', 'lpf'], true) ? $type : 'bell';
            $row->frequency_hz = $freq;
            $row->gain_db = $gain;
            $row->q = $q;
            $row->save();
        }

        foreach (['compressor', 'gate', 'limiter'] as $kind) {
            $block = isset($input[$kind]) && is_array($input[$kind]) ? $input[$kind] : [];
            if ($this->dynamicEmpty($block)) {
                continue;
            }
            $row = new MixDynamic();
            $row->version_id = $version->id;
            $row->kind = $kind;
            $row->enabled = ! isset($block['enabled']) || (string) $block['enabled'] !== '0';
            $row->threshold_db = MixSupport::num(isset($block['threshold_db']) ? $block['threshold_db'] : null);
            $row->ratio = MixSupport::num(isset($block['ratio']) ? $block['ratio'] : null);
            $row->attack_ms = MixSupport::num(isset($block['attack_ms']) ? $block['attack_ms'] : null);
            $row->release_ms = MixSupport::num(isset($block['release_ms']) ? $block['release_ms'] : null);
            $row->knee = MixSupport::text(isset($block['knee']) ? $block['knee'] : null);
            $row->makeup_db = MixSupport::num(isset($block['makeup_db']) ? $block['makeup_db'] : null);
            $row->hold_ms = MixSupport::num(isset($block['hold_ms']) ? $block['hold_ms'] : null);
            $row->range_db = MixSupport::num(isset($block['range_db']) ? $block['range_db'] : null);
            $row->key_source = MixSupport::text(isset($block['key_source']) ? $block['key_source'] : null);
            $row->key_filter = MixSupport::text(isset($block['key_filter']) ? $block['key_filter'] : null);
            $row->model = MixSupport::text(isset($block['model']) ? $block['model'] : null);
            $row->expander_mode = MixSupport::text(isset($block['expander_mode']) ? $block['expander_mode'] : null);
            $extra = isset($block['extra']) && is_array($block['extra']) ? $block['extra'] : [];
            $clean = [];
            foreach ($extra as $key => $value) {
                $text = MixSupport::text($value);
                if ($text !== null) {
                    $clean[$key] = $text;
                }
            }
            $row->extra_json = $clean ? json_encode($clean) : null;
            $row->save();
        }

        $effects = isset($input['effects']) && is_array($input['effects']) ? $input['effects'] : [];
        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }
            $type = MixSupport::text(isset($effect['effect_type']) ? $effect['effect_type'] : null);
            if ($type === null) {
                continue;
            }
            $row = new MixEffect();
            $row->version_id = $version->id;
            $row->effect_type = $type;
            $row->send_level = MixSupport::num(isset($effect['send_level']) ? $effect['send_level'] : null);
            $row->return_level = MixSupport::num(isset($effect['return_level']) ? $effect['return_level'] : null);
            $row->params_json = MixSupport::text(isset($effect['params']) ? $effect['params'] : null);
            $row->save();
        }

        $routes = isset($input['routing']) && is_array($input['routing']) ? $input['routing'] : [];
        foreach (['main_lr', 'subgroup', 'aux', 'matrix', 'fx', 'stereo_link'] as $destination) {
            $block = isset($routes[$destination]) && is_array($routes[$destination]) ? $routes[$destination] : [];
            $enabled = ! empty($block['enabled']);
            $label = MixSupport::text(isset($block['label']) ? $block['label'] : null);
            $level = MixSupport::num(isset($block['level_db']) ? $block['level_db'] : null);
            if (! $enabled && $label === null && $level === null) {
                continue;
            }
            $row = new MixRouting();
            $row->version_id = $version->id;
            $row->destination = $destination;
            $row->label = $label;
            $row->pre_post = MixSupport::text(isset($block['pre_post']) ? $block['pre_post'] : null);
            $row->level_db = $level;
            $row->enabled = $enabled;
            $row->save();
        }

        return $version->load(['bands', 'dynamics', 'effects', 'routings']);
    }

    public function graphPayload(MixPresetVersion $version)
    {
        $version->loadMissing(['bands', 'dynamics']);
        $bands = [];
        foreach ($version->bands as $band) {
            $bands[] = [
                'enabled' => (bool) $band->enabled,
                'filter_type' => $band->filter_type,
                'frequency_hz' => $band->frequency_hz !== null ? (float) $band->frequency_hz : null,
                'gain_db' => $band->gain_db !== null ? (float) $band->gain_db : 0,
                'q' => $band->q !== null ? (float) $band->q : 1,
            ];
        }
        $input = $version->input();
        if (! empty($input['hpf_hz'])) {
            array_unshift($bands, [
                'enabled' => true,
                'filter_type' => 'hpf',
                'frequency_hz' => (float) $input['hpf_hz'],
                'gain_db' => 0,
                'q' => 0.707,
                'locked' => true,
            ]);
        }
        $map = function ($row) {
            if (! $row) {
                return null;
            }

            return [
                'enabled' => (bool) $row->enabled,
                'threshold_db' => $row->threshold_db !== null ? (float) $row->threshold_db : null,
                'ratio' => $row->ratio !== null ? (float) $row->ratio : null,
                'knee' => $row->knee,
                'range_db' => $row->range_db !== null ? (float) $row->range_db : null,
                'attack_ms' => $row->attack_ms,
                'release_ms' => $row->release_ms,
            ];
        };

        return [
            'bands' => $bands,
            'compressor' => $map($version->compressor()),
            'gate' => $map($version->gate()),
        ];
    }

    public function copyText(MixPreset $preset, MixPresetVersion $version)
    {
        $lines = [$preset->name, $preset->sourceName()];
        if ($preset->instrument) {
            $lines[] = 'Instrument: '.$preset->instrument->name;
        }
        if ($preset->console) {
            $lines[] = 'Console: '.$preset->console->name;
        }
        $lines[] = 'Version '.$version->version_number.($version->label ? ' — '.$version->label : '');
        $input = $version->input();
        foreach ([
            'preamp_gain_db' => 'Preamp gain',
            'digital_trim_db' => 'Digital trim',
            'hpf_hz' => 'HPF',
            'lpf_hz' => 'LPF',
            'delay_ms' => 'Delay',
        ] as $key => $label) {
            if (isset($input[$key]) && $input[$key] !== null && $input[$key] !== '') {
                $unit = strpos($key, '_hz') !== false ? ' Hz' : (strpos($key, '_ms') !== false ? ' ms' : ' dB');
                $lines[] = $label.': '.$input[$key].$unit;
            }
        }
        if (! empty($input['phantom'])) {
            $lines[] = 'Phantom power: on';
        }
        if (! empty($input['polarity'])) {
            $lines[] = 'Polarity: inverted';
        }
        foreach ($version->bands as $band) {
            $lines[] = 'EQ '.$band->band_index.' '.$band->filter_type
                .' '.($band->enabled ? 'on' : 'bypass')
                .' '.($band->frequency_hz !== null ? $band->frequency_hz.' Hz' : '')
                .' '.($band->gain_db !== null ? $band->gain_db.' dB' : '')
                .' '.($band->q !== null ? 'Q '.$band->q : '');
        }
        foreach ($version->dynamics as $row) {
            $lines[] = ucfirst($row->kind)
                .' threshold '.($row->threshold_db !== null ? $row->threshold_db.' dB' : '—')
                .' ratio '.($row->ratio !== null ? $row->ratio.':1' : '—')
                .' attack '.($row->attack_ms !== null ? $row->attack_ms.' ms' : '—')
                .' release '.($row->release_ms !== null ? $row->release_ms.' ms' : '—');
        }
        foreach ($version->effects as $effect) {
            $lines[] = 'Effect: '.$effect->effect_type;
        }
        $lines[] = 'Reference settings only. This text does not load the mixer.';

        return implode("\n", $lines);
    }

    protected function dynamicEmpty(array $block)
    {
        foreach (['threshold_db', 'ratio', 'attack_ms', 'release_ms', 'knee', 'makeup_db', 'hold_ms', 'range_db', 'key_source', 'key_filter', 'model', 'expander_mode'] as $key) {
            if (MixSupport::text(isset($block[$key]) ? $block[$key] : null) !== null) {
                return false;
            }
        }
        if (isset($block['extra']) && is_array($block['extra'])) {
            foreach ($block['extra'] as $value) {
                if (MixSupport::text($value) !== null) {
                    return false;
                }
            }
        }

        return true;
    }
}
