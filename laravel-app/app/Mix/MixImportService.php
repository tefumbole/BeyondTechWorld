<?php

namespace App\Mix;

use Illuminate\Support\Facades\File;

class MixImportService
{
    public function syncSources()
    {
        $dir = resource_path('mix/sources');
        if (! is_dir($dir)) {
            return;
        }
        foreach (MixSourceCatalog::files() as $filename => $keys) {
            $source = $dir.DIRECTORY_SEPARATOR.$filename;
            if (! is_file($source)) {
                continue;
            }
            $existing = MixImportFile::where('original_name', $filename)->first();
            if ($existing) {
                continue;
            }
            $storedDir = storage_path('app/mix-imports');
            if (! is_dir($storedDir)) {
                File::makeDirectory($storedDir, 0755, true);
            }
            $stored = $storedDir.DIRECTORY_SEPARATOR.$filename;
            if (! is_file($stored)) {
                File::copy($source, $stored);
            }
            $file = new MixImportFile();
            $file->original_name = $filename;
            $file->stored_path = 'mix-imports/'.$filename;
            $file->status = 'review';
            $file->extracted_text = $this->extractText($stored);
            $file->save();
            $presets = MixSourceCatalog::presets();
            foreach ($keys as $key) {
                if (empty($presets[$key])) {
                    continue;
                }
                foreach ($presets[$key] as $row) {
                    $review = new MixImportReview();
                    $review->import_file_id = $file->id;
                    $review->preset_key = $row['preset_key'];
                    $review->preset_name = $row['preset_name'];
                    $review->instrument_slug = $row['instrument_slug'];
                    $review->field = $row['field'];
                    $review->extracted_value = $row['extracted_value'];
                    $review->status = $row['status'];
                    $review->note = $row['note'];
                    $review->confirmed = false;
                    $review->save();
                }
            }
        }
    }

    public function extractText($path)
    {
        if (! function_exists('shell_exec') || ! function_exists('exec')) {
            return null;
        }
        $binary = trim((string) shell_exec('command -v pdftotext'));
        if ($binary === '' || ! is_file($path)) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mixpdf');
        $command = escapeshellarg($binary).' -layout '.escapeshellarg($path).' '.escapeshellarg($tmp);
        exec($command, $output, $code);
        $text = is_file($tmp) ? file_get_contents($tmp) : '';
        @unlink($tmp);
        if ($code !== 0) {
            return null;
        }

        return $text !== '' ? $text : null;
    }

    /**
     * Build a preset payload from confirmed review rows only.
     * A confirmed row with an empty value is skipped. Missing rows are never filled.
     *
     * @param array<int, array<string, mixed>> $reviews
     */
    public static function buildInput(array $reviews)
    {
        $input = [
            'input' => [],
            'eq' => [],
            'compressor' => ['extra' => []],
            'gate' => ['extra' => []],
            'limiter' => ['extra' => []],
            'effects' => [],
        ];
        foreach ($reviews as $review) {
            if (empty($review['confirmed'])) {
                continue;
            }
            $value = array_key_exists('confirmed_value', $review) && $review['confirmed_value'] !== null && $review['confirmed_value'] !== ''
                ? $review['confirmed_value']
                : (isset($review['extracted_value']) ? $review['extracted_value'] : null);
            if ($value === null || $value === '') {
                continue;
            }
            $field = isset($review['field']) ? $review['field'] : '';
            if (strpos($field, 'input.') === 0) {
                $input['input'][substr($field, 6)] = $value;
                continue;
            }
            if (preg_match('/^eq\.(\d+)\.([a-z_]+)$/', $field, $match)) {
                $index = (int) $match[1];
                if (! isset($input['eq'][$index])) {
                    $input['eq'][$index] = ['enabled' => '1'];
                }
                $input['eq'][$index][$match[2]] = $value;
                continue;
            }
            if (strpos($field, 'eq.passive.') === 0) {
                if (! isset($input['effects']['passive'])) {
                    $input['effects']['passive'] = ['effect_type' => 'Passive EQ', 'params' => ''];
                }
                $input['effects']['passive']['params'] .= substr($field, 11).'='.$value.'; ';
                continue;
            }
            if (preg_match('/^(compressor|gate|limiter)\.extra\.([a-z0-9_]+)$/', $field, $match)) {
                $input[$match[1]]['extra'][$match[2]] = $value;
                continue;
            }
            if (preg_match('/^(compressor|gate|limiter)\.([a-z_]+)$/', $field, $match)) {
                $input[$match[1]][$match[2]] = $value;
                continue;
            }
            if (preg_match('/^effect\.([a-z0-9_]+)\.([a-z_]+)$/', $field, $match)) {
                $type = $match[1];
                if (! isset($input['effects'][$type])) {
                    $input['effects'][$type] = ['effect_type' => $type];
                }
                if ($match[2] === 'type') {
                    $input['effects'][$type]['params'] = $value;
                } else {
                    $input['effects'][$type][$match[2]] = $value;
                }
            }
        }
        $input['effects'] = array_values($input['effects']);
        ksort($input['eq']);

        return $input;
    }

    public function commitFile(MixImportFile $file, $userId = null)
    {
        $created = [];
        $grouped = [];
        foreach ($file->reviews as $review) {
            $grouped[$review->preset_key][] = $review->toArray();
        }
        $console = MixConsole::orderBy('id')->first();
        $manufacturer = MixManufacturer::where('slug', 'beyond')->first();
        foreach ($grouped as $key => $rows) {
            $meta = $rows[0];
            $payload = self::buildInput($rows);
            $hasValue = $this->payloadHasValue($payload);
            if (! $hasValue) {
                continue;
            }
            $instrument = MixInstrument::where('slug', $meta['instrument_slug'])->first();
            $name = 'Beyond '.$meta['preset_name'];
            if (MixPreset::where('name', $name)->exists()) {
                continue;
            }
            $preset = new MixPreset();
            $payload['name'] = $name;
            $payload['instrument_id'] = $instrument ? $instrument->id : null;
            $payload['console_id'] = $console ? $console->id : null;
            $payload['manufacturer_id'] = $manufacturer ? $manufacturer->id : null;
            $payload['collection'] = 'beyond';
            $payload['source_label'] = 'Beyond preset PDF '.$file->original_name;
            $payload['notes'] = 'Created from confirmed import rows only. Unconfirmed and empty fields were left blank.';
            $payload['version_label'] = 'Imported review';
            $preset = app(MixPresetService::class)->save($preset, $payload, $userId);
            $preset->published = false;
            $preset->save();
            $created[] = $preset;
        }
        $file->status = 'confirmed';
        $file->save();
        MixAuditLog::write('import.commit', $file, count($created).' unpublished presets');

        return $created;
    }

    protected function payloadHasValue(array $payload)
    {
        foreach ($payload['input'] as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }
        if ($payload['eq'] || $payload['effects']) {
            return true;
        }
        foreach (['compressor', 'gate', 'limiter'] as $kind) {
            foreach ($payload[$kind] as $key => $value) {
                if ($key === 'extra') {
                    if ($value) {
                        return true;
                    }
                    continue;
                }
                if ($value !== null && $value !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
