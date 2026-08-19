<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone', 'whatsapp_number'];

    protected $table = 'applications';
    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_AWAITING = 'awaiting_approval';
    const STATUS_SELECTED = 'selected';
    const STATUS_REJECTED = 'rejected';
    const STATUS_HIRED = 'hired';

    const APPLICANT_STUDENT = 'student';
    const APPLICANT_WORKER = 'worker';

    const DOCS_COMPLETE = 'complete';
    const DOCS_INCOMPLETE = 'incomplete';
    const DOCS_UPDATE_REQUESTED = 'update_requested';

    protected $fillable = [
        'id', 'job_id', 'internship_program_id', 'internship_duration_days', 'working_week_json',
        'user_id', 'full_name', 'email', 'phone', 'whatsapp_number', 'country',
        'school', 'level_of_study', 'education_status', 'applicant_type', 'is_academic_required',
        'cover_letter', 'expected_salary', 'availability', 'availability_days',
        'cv_url', 'cv_path', 'student_id_path', 'student_id_back_path', 'internship_letter_path',
        'employment_letter_path', 'official_badge_path', 'selfie_path',
        'deferred_documents', 'documents_status', 'documents_update_token',
        'documents_requested_at', 'documents_request_note',
        'signature_image', 'agreement_token', 'agreement_sent_at', 'agreement_signed_at',
        'offer_accepted_at', 'offer_flow_version',
        'agreement_signature_image', 'status', 'reference_number', 'rejection_reason',
        'interview_date', 'submitted_at',
    ];

    protected $dates = [
        'interview_date', 'submitted_at', 'agreement_sent_at', 'agreement_signed_at',
        'offer_accepted_at', 'documents_requested_at',
    ];

    protected $casts = [
        'is_academic_required' => 'boolean',
        'internship_duration_days' => 'integer',
        'offer_flow_version' => 'integer',
    ];

    public function needsOfferPortal()
    {
        return (int) ($this->offer_flow_version ?? 0) >= 1 && empty($this->offer_accepted_at);
    }

    public function workingWeekData()
    {
        return \App\Support\WorkingWeekForm::fromArray($this->working_week_json);
    }

    public function hasWorkingWeekOnApplication()
    {
        try {
            \App\Support\WorkingWeekForm::assertValid($this->workingWeekData());

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Suggested preset lengths (UI helpers only — any 1–180 days is allowed). */
    public static function internshipDurationOptions()
    {
        return [30, 90, 180];
    }

    public static function internshipDurationMin()
    {
        return 1;
    }

    public static function internshipDurationMax()
    {
        return 180;
    }

    /** Validation rule fragment for free-form internship length (1–180 days). */
    public static function internshipDurationRule($required = true)
    {
        $base = ($required ? 'required' : 'nullable').'|integer|min:'.self::internshipDurationMin().'|max:'.self::internshipDurationMax();

        return $base;
    }

    public static function normalizeInternshipDurationDays($value, $default = 90)
    {
        $days = (int) $value;
        if ($days < self::internshipDurationMin()) {
            $days = (int) $default;
        }

        return max(self::internshipDurationMin(), min(self::internshipDurationMax(), $days));
    }

    public function internshipProgram()
    {
        return $this->belongsTo(InternshipProgram::class, 'internship_program_id');
    }

    public function internshipDurationLabel()
    {
        $days = (int) ($this->internship_duration_days ?: 0);

        return $days > 0 ? $days.' days' : '—';
    }

    public function educationStatusLabel()
    {
        $map = [
            'currently_studying' => 'Currently studying',
            'graduated' => 'Graduated',
            'not_a_student' => 'Not a student (worker)',
        ];

        return $map[$this->education_status] ?? ($this->education_status ?: '—');
    }

    public function isWorkerApplicant()
    {
        return ($this->applicant_type === self::APPLICANT_WORKER)
            || ($this->education_status === 'not_a_student');
    }

    public function isStudentApplicant()
    {
        return ! $this->isWorkerApplicant();
    }

    public function applicantTypeLabel()
    {
        return $this->isWorkerApplicant() ? 'Worker / Not a student' : 'Student';
    }

    public function deferredDocumentKeys()
    {
        $raw = $this->deferred_documents;
        if (is_array($raw)) {
            return array_values(array_unique(array_filter(array_map('strval', $raw))));
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded)
            ? array_values(array_unique(array_filter(array_map('strval', $decoded))))
            : [];
    }

    public function setDeferredDocumentKeys(array $keys)
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        $this->deferred_documents = empty($keys) ? null : json_encode(array_values($keys));
    }

    public static function documentKeyLabels()
    {
        return [
            'internship_letter' => 'School internship letter',
            'employment_letter' => 'Employment letter',
            'official_badge' => 'Official badge',
            'student_id' => 'ID card (front)',
            'student_id_back' => 'ID card (back)',
            'selfie' => 'Selfie / Photo',
        ];
    }

    public function documentPathForKey($key)
    {
        $map = [
            'internship_letter' => 'internship_letter_path',
            'employment_letter' => 'employment_letter_path',
            'official_badge' => 'official_badge_path',
            'student_id' => 'student_id_path',
            'student_id_back' => 'student_id_back_path',
            'selfie' => 'selfie_path',
        ];
        $col = $map[$key] ?? null;

        return $col ? ($this->{$col} ?: null) : null;
    }

    /**
     * Keys still expected but missing (deferred and not yet uploaded).
     * Worker employment letter OR badge satisfies both deferred worker-proof keys.
     */
    public function missingDocumentKeys()
    {
        $missing = [];
        $hasWorkerProof = $this->employment_letter_path || $this->official_badge_path;
        foreach ($this->deferredDocumentKeys() as $key) {
            if (in_array($key, ['employment_letter', 'official_badge'], true) && $hasWorkerProof) {
                continue;
            }
            if (! $this->documentPathForKey($key)) {
                $missing[] = $key;
            }
        }

        return array_values(array_unique($missing));
    }

    public function hasIncompleteDocuments()
    {
        return ! empty($this->missingDocumentKeys())
            || in_array((string) $this->documents_status, [self::DOCS_INCOMPLETE, self::DOCS_UPDATE_REQUESTED], true);
    }

    public function documentsStatusLabel()
    {
        if ($this->documents_status === self::DOCS_UPDATE_REQUESTED) {
            return 'Update requested';
        }
        if (! empty($this->missingDocumentKeys()) || $this->documents_status === self::DOCS_INCOMPLETE) {
            return 'Incomplete';
        }

        return 'Complete';
    }

    public function refreshDocumentsStatus($keepUpdateRequested = false)
    {
        $missing = $this->missingDocumentKeys();
        if (empty($missing)) {
            $this->documents_status = self::DOCS_COMPLETE;
            // Clear deferred keys that are now filled.
            $stillDeferred = [];
            foreach ($this->deferredDocumentKeys() as $key) {
                if (! $this->documentPathForKey($key)) {
                    $stillDeferred[] = $key;
                }
            }
            $this->setDeferredDocumentKeys($stillDeferred);
        } else {
            if ($keepUpdateRequested && $this->documents_status === self::DOCS_UPDATE_REQUESTED) {
                // leave as update_requested
            } else {
                $this->documents_status = self::DOCS_INCOMPLETE;
            }
        }

        return $this;
    }

    public function documentsUpdateUrl()
    {
        if (! $this->documents_update_token) {
            return null;
        }

        return url('/application-documents/'.$this->documents_update_token);
    }

    public function academicRequiredLabel()
    {
        if ($this->isWorkerApplicant()) {
            return 'N/A (not a student)';
        }
        if ($this->is_academic_required === null) {
            return '—';
        }

        return $this->is_academic_required ? 'Yes (academic requirement)' : 'No (voluntary)';
    }

    public function job()
    {
        return $this->belongsTo(JobPosting::class, 'job_id', 'id');
    }

    public function notificationPhone()
    {
        return $this->whatsapp_number ?: $this->phone;
    }

    public function statusLabel()
    {
        $map = [
            self::STATUS_AWAITING => 'Awaiting Approval',
            self::STATUS_SELECTED => 'Selected',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_HIRED => 'Hired',
            'new' => 'Awaiting Approval',
            'reviewed' => 'Awaiting Approval',
            'interview' => 'Awaiting Approval',
            'shortlisted' => 'Selected',
            'withdrawn' => 'Rejected',
        ];

        return $map[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    /** CSS modifier for status pills (jb-badge--*). */
    public function statusBadgeClass()
    {
        return self::badgeClassForStatus($this->status);
    }

    public static function badgeClassForStatus($status)
    {
        $status = (string) $status;
        if (in_array($status, [self::STATUS_REJECTED, 'withdrawn'], true)) {
            return 'jb-badge--danger';
        }
        if (in_array($status, [self::STATUS_SELECTED, 'shortlisted', self::STATUS_HIRED], true)) {
            return 'jb-badge--success';
        }
        if (in_array($status, [self::STATUS_AWAITING, 'new', 'reviewed', 'interview', 'pending'], true)) {
            return 'jb-badge--warn';
        }

        return '';
    }

    /**
     * Public URL for a stored upload path under public/uploads/...
     * Stored values look like "uploads/applications/file.jpg" or "/uploads/applications/file.jpg".
     */
    public static function publicUploadUrl($path)
    {
        if (! $path) {
            return null;
        }
        $path = trim((string) $path);
        if (preg_match('#^https?://#i', $path) || strpos($path, 'data:') === 0) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (strpos($path, 'public/') === 0) {
            return url($path);
        }

        return url('public/'.$path);
    }

    public function documentPublicUrl($field)
    {
        if ($field === 'cv') {
            return self::publicUploadUrl($this->cv_url ?: $this->cv_path);
        }

        return self::publicUploadUrl($this->{$field} ?? null);
    }

    public function absoluteUploadPath($relative)
    {
        if (! $relative) {
            return null;
        }
        $relative = ltrim((string) $relative, '/');
        if (strpos($relative, 'public/') === 0) {
            $relative = substr($relative, 7);
        }
        // Absolute filesystem path already
        if (strpos($relative, '/') === 0 || preg_match('#^[A-Za-z]:\\\\#', $relative)) {
            return is_file($relative) ? $relative : null;
        }
        $full = base_path('public/'.$relative);

        return is_file($full) ? $full : null;
    }
}
