<?php

namespace App\Support;

use App\InternshipGrade;
use App\InternshipProgramTask;

/**
 * Marking rubric for internship task submissions.
 *
 * Curriculum tasks carry a `rubric_json` map of criterion => weight. This class
 * turns any of the shapes that have been imported over time into one list of
 * criteria the supervisor can score against, adds the marking guidance shown
 * next to each criterion, and converts the marks into the 0–100 score that is
 * compared against the task pass mark.
 */
class InternshipRubric
{
    /** Used when a task has no rubric of its own. Weights sum to 100. */
    const DEFAULT_CRITERIA = [
        'technical_correctness' => 40,
        'evidence_and_reproducibility' => 20,
        'troubleshooting_or_reasoning' => 15,
        'documentation' => 15,
        'safety_security_professionalism' => 10,
    ];

    protected static $labels = [
        'technical_correctness' => 'Technical correctness',
        'evidence_and_reproducibility' => 'Evidence & reproducibility',
        'troubleshooting_or_reasoning' => 'Troubleshooting & reasoning',
        'documentation' => 'Documentation',
        'safety_security_professionalism' => 'Safety, security & professionalism',
    ];

    protected static $guides = [
        'technical_correctness' => 'Does the work meet the task specification? Correct method, correct settings, working result, no critical errors left behind.',
        'evidence_and_reproducibility' => 'Do the screenshots, photos, logs or files actually prove the work was done, and could another person repeat it from what was submitted?',
        'troubleshooting_or_reasoning' => 'Are the problems met along the way identified, diagnosed and explained, and are the choices made justified?',
        'documentation' => 'Is the write-up clear and complete: steps taken, tools used, results obtained, in an order a reader can follow?',
        'safety_security_professionalism' => 'Safe working practice, careful handling of credentials and customer data, tidy workmanship, professional conduct.',
    ];

    /**
     * Scorable criteria for a task.
     *
     * @param  InternshipProgramTask|null  $task
     * @return array<int, array{key:string,label:string,max:int,guide:string|null}>
     */
    public static function criteria($task = null)
    {
        $raw = $task instanceof InternshipProgramTask ? $task->rubric() : [];
        $criteria = self::parse(is_array($raw) ? $raw : []);

        return $criteria ?: self::parse(self::DEFAULT_CRITERIA);
    }

    /**
     * @return array<int, array{key:string,label:string,max:int,guide:string|null}>
     */
    protected static function parse(array $raw)
    {
        $criteria = [];
        foreach ($raw as $key => $value) {
            $item = self::parseOne($key, $value);
            if ($item) {
                $criteria[$item['key']] = $item;
            }
        }

        return array_values($criteria);
    }

    /**
     * @return array{key:string,label:string,max:int,guide:string|null}|null
     */
    protected static function parseOne($key, $value)
    {
        $label = null;
        $guide = null;

        if (is_array($value)) {
            $max = $value['max'] ?? $value['points'] ?? $value['weight'] ?? $value['score'] ?? 0;
            $label = $value['label'] ?? $value['criterion'] ?? $value['name'] ?? null;
            $guide = $value['guide'] ?? $value['descriptor'] ?? $value['description'] ?? null;
            if (is_int($key) && $label) {
                $key = $label;
            }
        } else {
            $max = $value;
        }

        $key = self::slug($key);
        $max = (int) round((float) $max);
        if ($key === '' || $max < 1) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label ? (string) $label : self::label($key),
            'max' => $max,
            'guide' => $guide ? (string) $guide : (self::$guides[$key] ?? null),
        ];
    }

    protected static function slug($key)
    {
        $key = strtolower(trim((string) $key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);

        return trim((string) $key, '_');
    }

    public static function label($key)
    {
        if (isset(self::$labels[$key])) {
            return self::$labels[$key];
        }

        return ucfirst(str_replace('_', ' ', (string) $key));
    }

    /**
     * @param  array<int, array{max:int}>  $criteria
     */
    public static function totalPoints(array $criteria)
    {
        $total = 0;
        foreach ($criteria as $c) {
            $total += (int) $c['max'];
        }

        return $total;
    }

    /**
     * Turn supervisor input into a 0–100 score plus the breakdown to store.
     *
     * `total` is null when the supervisor left the rubric blank, so the caller
     * can fall back to a manually typed score.
     *
     * @param  array<int, array{key:string,label:string,max:int}>  $criteria
     * @param  array  $input  criterion key => mark
     * @return array{graded:bool,total:int|null,errors:array<int,string>,stored:array}
     */
    public static function marks(array $criteria, array $input)
    {
        $scores = [];
        $max = [];
        $errors = [];
        $missing = [];

        foreach ($criteria as $c) {
            $max[$c['key']] = (int) $c['max'];
            $value = $input[$c['key']] ?? null;
            if ($value === null || $value === '') {
                $missing[] = $c['label'];

                continue;
            }
            $mark = (int) $value;
            if ($mark < 0 || $mark > (int) $c['max']) {
                $errors[] = $c['label'].' must be between 0 and '.$c['max'].'.';
            }
            $scores[$c['key']] = max(0, min((int) $c['max'], $mark));
        }

        if (! $scores) {
            return ['graded' => false, 'total' => null, 'errors' => [], 'stored' => []];
        }

        if ($missing) {
            $errors[] = 'Give a mark for every criterion. Missing: '.implode(', ', $missing).'.';
        }

        $possible = self::totalPoints($criteria);
        $earned = array_sum($scores);
        $percent = $possible > 0 ? (int) round($earned / $possible * 100) : 0;

        return [
            'graded' => true,
            'total' => max(0, min(100, $percent)),
            'errors' => $errors,
            'stored' => [
                'scores' => $scores,
                'max' => $max,
                'points_earned' => $earned,
                'points_possible' => $possible,
                'percent' => $percent,
            ],
        ];
    }

    /**
     * Rows for displaying a recorded mark sheet, tolerant of the legacy flat
     * `{criterion: mark}` shape written before the rubric form existed.
     *
     * @param  InternshipGrade|null  $grade
     * @param  InternshipProgramTask|null  $task
     * @return array{rows:array<int, array{label:string,score:int,max:int|null}>,earned:int|null,possible:int|null,percent:int|null}
     */
    public static function breakdown($grade, $task = null)
    {
        $empty = ['rows' => [], 'earned' => null, 'possible' => null, 'percent' => null];
        if (! $grade instanceof InternshipGrade) {
            return $empty;
        }

        $stored = $grade->rubricScores();
        if (! $stored) {
            return $empty;
        }

        $scores = isset($stored['scores']) && is_array($stored['scores']) ? $stored['scores'] : $stored;
        if (! is_array($scores) || ! $scores) {
            return $empty;
        }

        $max = isset($stored['max']) && is_array($stored['max']) ? $stored['max'] : [];
        if (! $max) {
            foreach (self::criteria($task) as $c) {
                $max[$c['key']] = $c['max'];
            }
        }

        $guides = [];
        foreach (self::criteria($task) as $c) {
            $guides[$c['key']] = $c['guide'] ?? null;
        }

        $rows = [];
        $earned = 0;
        $possible = 0;
        foreach ($scores as $key => $mark) {
            if (! is_numeric($mark)) {
                continue;
            }
            $rows[] = [
                'label' => self::label($key),
                'score' => (int) $mark,
                'max' => isset($max[$key]) ? (int) $max[$key] : null,
                'guide' => $guides[$key] ?? null,
            ];
            $earned += (int) $mark;
            $possible += isset($max[$key]) ? (int) $max[$key] : 0;
        }

        if (! $rows) {
            return $empty;
        }

        return [
            'rows' => $rows,
            'earned' => $earned,
            'possible' => $possible ?: null,
            'percent' => isset($stored['percent']) ? (int) $stored['percent'] : ($possible ? (int) round($earned / $possible * 100) : null),
        ];
    }

    /**
     * WhatsApp lines for the intern: each criterion and the total.
     */
    public static function whatsAppBreakdown($grade, $task = null)
    {
        $breakdown = self::breakdown($grade, $task);
        if (empty($breakdown['rows'])) {
            return '';
        }

        $out = "\n*Results breakdown:*\n";
        foreach ($breakdown['rows'] as $row) {
            $mark = $row['score'].(! is_null($row['max']) ? '/'.$row['max'] : '');
            $out .= WhatsAppMessage::bullet($row['label'], $mark);
        }
        if (! is_null($breakdown['earned']) && ! empty($breakdown['possible'])) {
            $out .= WhatsAppMessage::bullet(
                'Total',
                $breakdown['earned'].'/'.$breakdown['possible']
                .(! is_null($breakdown['percent']) ? ' ('.$breakdown['percent'].'%)' : '')
            );
        }

        return $out;
    }

    /**
     * Quick-fill marks offered next to each criterion in the grading form.
     *
     * @return array<int, array{label:string,value:int}>
     */
    public static function bands($max)
    {
        $max = max(1, (int) $max);

        return [
            ['label' => 'Excellent', 'value' => $max],
            ['label' => 'Good', 'value' => (int) round($max * 0.85)],
            ['label' => 'Adequate', 'value' => (int) round($max * 0.7)],
            ['label' => 'Weak', 'value' => (int) round($max * 0.45)],
            ['label' => 'Not shown', 'value' => 0],
        ];
    }
}
