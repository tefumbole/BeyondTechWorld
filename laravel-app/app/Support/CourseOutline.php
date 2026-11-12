<?php

namespace App\Support;

use App\InternshipProgram;
use App\InternshipProgramTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CourseOutline
{
    public static function sectionsForName($name, $code = null)
    {
        $module = self::moduleFor($name, $code);
        if (! $module) {
            return [];
        }
        $sections = isset($module['sections']) && is_array($module['sections']) ? $module['sections'] : [];

        return self::normalize($sections);
    }

    /**
     * Use the full topic list when a saved course only has empty section titles.
     */
    public static function enrich($name, $sections)
    {
        $sections = self::normalize(is_array($sections) ? $sections : []);
        foreach ($sections as $section) {
            if (! empty($section['items'])) {
                return $sections;
            }
        }
        $filled = self::sectionsForName($name);

        return $filled ?: $sections;
    }

    /**
     * @param Collection|array $programs
     * @return array<int,array{sections:array,weeks:array}>
     */
    public static function forPrograms($programs)
    {
        $programs = $programs instanceof Collection ? $programs : collect($programs);
        $ids = $programs->pluck('id')->filter()->values()->all();
        $byProgram = [];
        if ($ids && Schema::hasTable('internship_program_tasks')) {
            $rows = InternshipProgramTask::whereIn('program_id', $ids)
                ->orderBy('day_number')
                ->get(['program_id', 'day_number', 'title']);
            foreach ($rows as $row) {
                $week = (int) ceil(max(1, (int) $row->day_number) / 7);
                $title = trim((string) $row->title);
                if ($title === '') {
                    continue;
                }
                $byProgram[$row->program_id][$week][] = $title;
            }
        }

        $out = [];
        foreach ($programs as $program) {
            if (! $program instanceof InternshipProgram) {
                continue;
            }
            $weeks = [];
            foreach (($byProgram[$program->id] ?? []) as $number => $items) {
                $weeks[] = [
                    'title' => 'Week '.$number,
                    'items' => array_values($items),
                ];
            }
            $out[(string) $program->id] = [
                'sections' => self::sectionsForName($program->displayName(), $program->code),
                'weeks' => $weeks,
            ];
        }

        return $out;
    }

    protected static function moduleFor($name, $code = null)
    {
        $wanted = self::key(self::alias($name, $code));
        if ($wanted === '') {
            return null;
        }
        foreach (self::modules() as $module) {
            if (self::key(isset($module['title']) ? $module['title'] : '') === $wanted) {
                return $module;
            }
        }

        return null;
    }

    protected static function alias($name, $code = null)
    {
        $map = [
            'NETWORKING' => 'Network Infrastructure Design',
            'CYBER_SECURITY' => 'Cyber Security',
            'CLOUD_COMPUTING' => 'Cloud Computing',
            'ARTIFICIAL_INTELLIGENCE' => 'Artificial Intelligence',
        ];
        $code = strtoupper(trim((string) $code));
        if ($code !== '' && isset($map[$code])) {
            return $map[$code];
        }

        return (string) $name;
    }

    protected static function modules()
    {
        static $modules;
        if ($modules !== null) {
            return $modules;
        }
        $path = config_path('training-modules.json');
        if (! is_file($path)) {
            return $modules = [];
        }
        $decoded = json_decode(file_get_contents($path), true);

        return $modules = is_array($decoded) ? $decoded : [];
    }

    protected static function normalize($sections)
    {
        $out = [];
        foreach ($sections as $section) {
            if (is_string($section)) {
                $out[] = ['title' => $section, 'items' => []];
                continue;
            }
            if (! is_array($section)) {
                continue;
            }
            $items = isset($section['items']) ? $section['items'] : (isset($section['topics']) ? $section['topics'] : []);
            if (! is_array($items)) {
                $items = [];
            }
            $out[] = [
                'title' => (string) (isset($section['title']) ? $section['title'] : 'Outline'),
                'items' => array_values(array_filter(array_map('strval', $items))),
            ];
        }

        return $out;
    }

    protected static function key($value)
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim($value);
    }
}
