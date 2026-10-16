<?php

namespace Tests\Unit;

use App\Mix\MixImportService;
use Tests\TestCase;

class MixImportTest extends TestCase
{
    public function test_confirmed_rows_keep_values_and_skip_blanks()
    {
        $payload = MixImportService::buildInput([
            ['confirmed' => true, 'field' => 'input.hpf_hz', 'extracted_value' => '30', 'confirmed_value' => null],
            ['confirmed' => true, 'field' => 'eq.1.frequency_hz', 'extracted_value' => '54.46', 'confirmed_value' => ''],
            ['confirmed' => true, 'field' => 'eq.1.gain_db', 'extracted_value' => '5.40', 'confirmed_value' => null],
            ['confirmed' => false, 'field' => 'compressor.threshold_db', 'extracted_value' => '-21.39', 'confirmed_value' => null],
            ['confirmed' => true, 'field' => 'gate.range_db', 'extracted_value' => null, 'confirmed_value' => null],
            ['confirmed' => true, 'field' => 'input.preamp_gain_db', 'extracted_value' => '19', 'confirmed_value' => '18'],
        ]);

        $this->assertSame('30', $payload['input']['hpf_hz']);
        $this->assertSame('18', $payload['input']['preamp_gain_db']);
        $this->assertSame('54.46', $payload['eq'][1]['frequency_hz']);
        $this->assertSame('5.40', $payload['eq'][1]['gain_db']);
        $this->assertArrayNotHasKey('threshold_db', array_filter($payload['compressor'], function ($value, $key) {
            return $key !== 'extra';
        }, ARRAY_FILTER_USE_BOTH));
        $this->assertArrayNotHasKey('range_db', $payload['gate']);
    }

    public function test_unconfirmed_screenshot_value_is_not_copied()
    {
        $payload = MixImportService::buildInput([
            ['confirmed' => false, 'field' => 'compressor.threshold_db', 'extracted_value' => '-21.39'],
            ['confirmed' => false, 'field' => 'eq.1.gain_db', 'extracted_value' => null],
        ]);

        $this->assertSame([], $payload['eq']);
        $this->assertSame([], $payload['input']);
        $this->assertArrayNotHasKey('threshold_db', $payload['compressor']);
    }
}
