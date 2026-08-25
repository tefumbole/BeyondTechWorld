<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InternshipProgramTask extends Model
{
    protected $table = 'internship_program_tasks';

    protected $fillable = [
        'program_id', 'day_number', 'title', 'objective', 'study_note', 'instructions_json', 'resources_json',
        'estimated_hours', 'tools', 'difficulty', 'submission_requirements', 'evidence_slots_json',
        'rubric_json', 'pass_mark', 'requires_supervisor_approval', 'is_active',
    ];

    protected $casts = [
        'estimated_hours' => 'float',
        'pass_mark' => 'integer',
        'requires_supervisor_approval' => 'boolean',
        'is_active' => 'boolean',
        'day_number' => 'integer',
    ];

    public function program()
    {
        return $this->belongsTo(InternshipProgram::class, 'program_id');
    }

    public function instructions()
    {
        $raw = $this->instructions_json;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : (array_filter([(string) $raw]));
    }

    /**
     * Evidence slots the student should fill when submitting.
     *
     * Admin-defined lines win, then submission requirements, then instruction
     * steps that ask for a file. Students can always add extra slots.
     *
     * @return array<int, array{label:string,required:bool}>
     */
    public function evidenceSlots()
    {
        $fromJson = $this->parseSlotLines($this->evidence_slots_json);
        if ($fromJson) {
            return $fromJson;
        }

        $fromRequirements = $this->parseSlotLines($this->submission_requirements);
        if ($fromRequirements) {
            return $fromRequirements;
        }

        $fromInstructions = $this->slotsFromInstructions();
        if ($fromInstructions) {
            return $fromInstructions;
        }

        return [
            ['label' => 'File 1 — finished work', 'required' => true],
        ];
    }

    /**
     * @return array<int, array{label:string,required:bool}>
     */
    protected function slotsFromInstructions()
    {
        $slots = [];
        foreach ($this->instructions() as $line) {
            $label = trim(is_string($line) ? $line : (string) ($line['text'] ?? $line['label'] ?? ''));
            $label = preg_replace('/^\s*(\d+[\.\)]\s*|[-*•]\s+)/', '', $label);
            if ($label === '' || strlen($label) > 240 || str_word_count($label) > 22) {
                continue;
            }
            if (! preg_match('/screenshot|screen\s*shot|upload|attach|photo|capture|evidence|notebook|pdf|report file|save (as|the) file/i', $label)) {
                continue;
            }
            $slots[] = ['label' => $label, 'required' => empty($slots)];
        }

        return $slots;
    }

    /**
     * @return array<int, array{label:string,required:bool}>
     */
    public function parseSlotLines($raw)
    {
        if (is_array($raw)) {
            $lines = $raw;
        } else {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $lines = $decoded;
            } else {
                $text = trim((string) $raw);
                $lines = preg_split('/\r\n|\r|\n|;/', $text);
            }
        }

        $slots = [];
        foreach ((array) $lines as $line) {
            if (is_array($line)) {
                $label = trim((string) ($line['label'] ?? $line['text'] ?? ''));
                $required = ! empty($line['required']);
            } else {
                $label = trim((string) $line);
                $required = false;
            }
            $label = preg_replace('/^\s*(\d+[\.\)]\s*|[-*•]\s+)/', '', $label);
            if ($label === '' || strlen($label) > 240) {
                continue;
            }
            // A prose paragraph is guidance, not a slot list.
            if (str_word_count($label) > 18) {
                continue;
            }
            $slots[] = ['label' => $label, 'required' => $required || empty($slots)];
        }

        return $slots;
    }

    public function rubric()
    {
        $raw = $this->rubric_json;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
