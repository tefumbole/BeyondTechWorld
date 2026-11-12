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
            $columns = ['program_id', 'day_number', 'title'];
            if (Schema::hasColumn('internship_program_tasks', 'difficulty')) {
                $columns[] = 'difficulty';
            }
            $rows = InternshipProgramTask::whereIn('program_id', $ids)
                ->orderBy('day_number')
                ->get($columns);
            foreach ($rows as $row) {
                $week = (int) ceil(max(1, (int) $row->day_number) / 7);
                $title = trim((string) $row->title);
                if ($title === '') {
                    continue;
                }
                $byProgram[$row->program_id][$week][] = $title;
                $topic = self::phaseTopic($title);
                if ($topic !== null) {
                    $byProgram[$row->program_id]['phases'][] = [
                        'difficulty' => (string) ($row->difficulty ?? ''),
                        'topic' => $topic,
                    ];
                }
            }
        }

        $out = [];
        foreach ($programs as $program) {
            if (! $program instanceof InternshipProgram) {
                continue;
            }
            $bucket = $byProgram[$program->id] ?? [];
            $phases = $bucket['phases'] ?? [];
            unset($bucket['phases']);
            $weeks = [];
            foreach ($bucket as $number => $items) {
                $weeks[] = [
                    'title' => 'Week '.$number,
                    'items' => array_values($items),
                ];
            }
            $sections = self::sectionsForName($program->displayName(), $program->code);
            if (! $sections) {
                $sections = self::sectionsFromPhases($phases);
            }
            $out[(string) $program->id] = [
                'sections' => $sections,
                'weeks' => $weeks,
            ];
        }

        return $out;
    }

    /**
     * Cards for the Training page. Courses already listed by name are left to the caller.
     *
     * @param Collection|array $programs
     * @param array $outlines
     * @return array<int,array>
     */
    public static function trainingCards($programs, array $outlines)
    {
        $look = [
            'DATA_SCIENCE' => ['BarChart3', '#0284C7'],
            'MACHINE_LEARNING' => ['Brain', '#7C3AED'],
            'SOFTWARE_DEVELOPMENT' => ['Code', '#003D82'],
            'LIVE_SOUND_ENGINEERING' => ['Mic', '#DB2777'],
            'LIGHTING_ENGINEERING' => ['Lightbulb', '#D97706'],
            'SCREENS_VIDEO' => ['Video', '#0F766E'],
            'INTERCOM' => ['Radio', '#0369A1'],
            'NETWORKING' => ['Network', '#1D4ED8'],
            'CYBER_SECURITY' => ['Shield', '#DC2626'],
            'CLOUD_COMPUTING' => ['Cloud', '#2563EB'],
            'ARTIFICIAL_INTELLIGENCE' => ['Brain', '#8B5CF6'],
        ];
        $cards = [];
        foreach ($programs as $program) {
            if (! $program instanceof InternshipProgram) {
                continue;
            }
            $outline = isset($outlines[(string) $program->id]) ? $outlines[(string) $program->id] : ['sections' => []];
            $meta = isset($look[$program->code]) ? $look[$program->code] : ['Briefcase', '#003D82'];
            $cards[] = [
                'title' => $program->displayName(),
                'duration' => 'Up to 180 days',
                'deliveryMode' => 'Paid internship',
                'category' => 'Paid internship',
                'color' => $meta[1],
                'icon' => $meta[0],
                'sections' => isset($outline['sections']) ? $outline['sections'] : [],
            ];
        }

        return $cards;
    }

    protected static function sectionsFromPhases(array $phases)
    {
        $groups = [
            'Introduction' => [],
            'Beginner Level' => [],
            'Intermediate Level' => [],
            'Advanced Level' => [],
        ];
        $hasBeginner = false;
        foreach ($phases as $phase) {
            if (self::bucket(isset($phase['difficulty']) ? $phase['difficulty'] : '') === 'Beginner Level') {
                $hasBeginner = true;
                break;
            }
        }
        $foundation = [];
        foreach ($phases as $phase) {
            $topic = trim((string) (isset($phase['topic']) ? $phase['topic'] : ''));
            if ($topic === '') {
                continue;
            }
            $bucket = self::bucket(isset($phase['difficulty']) ? $phase['difficulty'] : '');
            if ($bucket === 'Introduction') {
                $foundation[] = $topic;
                continue;
            }
            $groups[$bucket][] = $topic;
        }
        if ($hasBeginner || count($foundation) < 2) {
            $groups['Introduction'] = $foundation;
        } else {
            $cut = (int) ceil(count($foundation) / 2);
            $groups['Introduction'] = array_slice($foundation, 0, $cut);
            $groups['Beginner Level'] = array_merge(array_slice($foundation, $cut), $groups['Beginner Level']);
        }

        $sections = [];
        foreach ($groups as $title => $items) {
            $items = array_values(array_unique($items));
            if (! $items) {
                continue;
            }
            $sections[] = ['title' => $title, 'items' => $items];
        }

        return $sections;
    }

    protected static function phaseTopic($title)
    {
        $title = trim((string) $title);
        $rules = [
            'Phase assessment:' => '/:\s*Assessment and Professional Report\s*$/i',
            'Assessment and presentation:' => '/\s+[—\-]\s+Assessment.*$/u',
        ];
        foreach ($rules as $prefix => $strip) {
            if (stripos($title, $prefix) !== 0) {
                continue;
            }
            $topic = trim(substr($title, strlen($prefix)));
            $topic = trim((string) preg_replace($strip, '', $topic));

            return $topic !== '' ? $topic : null;
        }

        return null;
    }

    protected static function bucket($difficulty)
    {
        $difficulty = strtolower(trim((string) $difficulty));
        if ($difficulty === 'foundation') {
            return 'Introduction';
        }
        if (strpos($difficulty, 'foundation') !== false) {
            return 'Beginner Level';
        }
        if ($difficulty === 'intermediate') {
            return 'Intermediate Level';
        }

        return 'Advanced Level';
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
