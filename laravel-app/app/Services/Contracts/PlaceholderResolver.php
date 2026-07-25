<?php

namespace App\Services\Contracts;

class PlaceholderResolver
{
    public function resolve($html, array $data)
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', function ($m) use ($data) {
            $key = $m[1];
            $val = $this->lookup($data, $key);
            if ($val === null || $val === '') {
                return $m[0]; // leave unresolved for validation
            }
            if (is_array($val)) {
                $val = implode(', ', $val);
            }
            $raw = (string) $val;
            // Allow trusted HTML fragments for schedule tables, etc.
            if (substr($key, -5) === '_html') {
                return $raw;
            }

            return e($raw);
        }, (string) $html);
    }

    public function unresolvedRequired($html, array $requiredKeys, array $data)
    {
        $missing = [];
        foreach ($requiredKeys as $key) {
            $val = $this->lookup($data, $key);
            if ($val === null || $val === '') {
                $missing[] = $key;
            }
        }
        // Also detect leftover tokens that appear in HTML for required keys
        foreach ($requiredKeys as $key) {
            if (strpos($html, '{{'.$key.'}}') !== false || strpos($html, '{{ '.$key.' }}') !== false) {
                if (! in_array($key, $missing, true)) {
                    $val = $this->lookup($data, $key);
                    if ($val === null || $val === '') {
                        $missing[] = $key;
                    }
                }
            }
        }

        return array_values(array_unique($missing));
    }

    public function extractKeys($html)
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', (string) $html, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    protected function lookup(array $data, $key)
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        $parts = explode('.', $key);
        $cur = $data;
        foreach ($parts as $p) {
            if (! is_array($cur) || ! array_key_exists($p, $cur)) {
                return null;
            }
            $cur = $cur[$p];
        }

        return $cur;
    }
}
