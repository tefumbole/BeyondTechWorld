<?php

namespace App\Services\Assistant;

use App\Product;
use Illuminate\Support\Facades\Schema;

class ServiceMenu
{
    public function text()
    {
        return "You can tap a service below, or reply with a number:\n1. Sound\n2. Light\n3. Screens\n4. IT services\n5. Others and specify";
    }

    public function options()
    {
        return ['1. Sound', '2. Light', '3. Screens', '4. IT services', '5. Others and specify'];
    }

    public function match($text)
    {
        $t = strtolower(trim((string) $text));
        if (preg_match('/^(1|sound|1\.\s*sound)$/', $t)) {
            return 'sound';
        }
        if (preg_match('/^(2|light|lights|lighting|2\.\s*light)$/', $t)) {
            return 'light';
        }
        if (preg_match('/^(3|screens?|3\.\s*screens)$/', $t)) {
            return 'screen';
        }
        if (preg_match('/^(4|it|it services|4\.\s*it services)$/', $t)) {
            return 'it';
        }
        if (preg_match('/^(5|others?|others? and specify|5\.\s*others.*)$/', $t)) {
            return 'other';
        }

        return null;
    }

    public function describe($key, $text)
    {
        if ($key === 'other') {
            $extra = trim(preg_replace('/^(5|others?( and specify)?)\b[:\-\s.]*/i', '', (string) $text));
            if ($extra === '') {
                return 'Tell me what you need, and I will check it against the product list.';
            }

            return $this->catalogue([$extra], 'that request');
        }
        $terms = [
            'sound' => ['speaker', 'microphone', 'mixer', 'amplifier', 'sound'],
            'light' => ['light', 'lighting', 'par', 'beam', 'wash'],
            'screen' => ['screen', 'projector', 'display', 'monitor'],
            'it' => ['laptop', 'computer', 'network', 'router', 'cctv'],
        ];

        return $this->catalogue(isset($terms[$key]) ? $terms[$key] : [], $key);
    }

    protected function catalogue(array $terms, $label)
    {
        if (! Schema::hasTable('products') || count($terms) === 0) {
            return 'Nothing in the product list matches '.$label.' yet.';
        }
        $query = Product::query()->where('is_active', true)->where(function ($inner) use ($terms) {
            foreach ($terms as $term) {
                $inner->orWhere('name', 'like', '%'.$term.'%')->orWhere('code', 'like', '%'.$term.'%');
            }
        });
        $rows = $query->orderBy('name')->limit(8)->get(['name']);
        if ($rows->isEmpty()) {
            return 'Nothing in the product list matches '.$label.' yet.';
        }
        $lines = ['From the product list for '.$label.':'];
        foreach ($rows as $row) {
            $lines[] = '- '.$row->name;
        }
        $lines[] = 'Tell me the date if you want me to check availability. This is not a booking.';

        return implode("\n", $lines);
    }
}
