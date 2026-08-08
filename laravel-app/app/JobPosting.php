<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobPosting extends Model
{
    protected $table = 'job_postings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'location', 'type', 'employment_type', 'posting_type',
        'internship_program_ids',
        'department', 'salary', 'min_requirements', 'requirements', 'qualifications',
        'responsibilities', 'deadline', 'max_positions', 'max_applicants',
        'expected_applicants', 'enable_countdown', 'current_applicants', 'status',
        'posted_at', 'expires_at',
    ];

    protected $dates = ['deadline', 'posted_at', 'expires_at'];

    protected $casts = [
        'enable_countdown' => 'boolean',
        'max_positions' => 'integer',
        'expected_applicants' => 'integer',
        'current_applicants' => 'integer',
    ];

    public function applications()
    {
        return $this->hasMany(Application::class, 'job_id', 'id');
    }

    public function getIsExpiredAttribute()
    {
        return $this->deadline && $this->deadline->copy()->endOfDay()->isPast();
    }

    public function isInternship()
    {
        return ($this->posting_type ?: 'job') === 'internship';
    }

    public function typeLabel()
    {
        return $this->isInternship() ? 'Internship' : 'Job';
    }

    /**
     * Selected 180-day internship program IDs offered on this posting.
     */
    public function internshipProgramIds()
    {
        $raw = $this->internship_program_ids;
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? array_values(array_filter(array_map('intval', $decoded))) : [];
    }

    public function internshipPrograms()
    {
        $ids = $this->internshipProgramIds();
        $query = InternshipProgram::query()
            ->where('status', 'published')
            ->where('is_active', true)
            ->orderBy('name');

        // Prefer programs linked on the posting; otherwise offer every published track.
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (! $this->isInternship()) {
            return collect();
        }

        return $query->get();
    }
}
