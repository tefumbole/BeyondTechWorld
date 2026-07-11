<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TrainingRegistration extends Model
{
    protected $table = 'registrations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'reference_number', 'client_name', 'client_email', 'client_phone',
        'company_name', 'course_ids', 'course_names', 'total_price', 'status',
        'payment_status', 'user_id',
    ];

    public function getCourseIdListAttribute()
    {
        return json_decode($this->course_ids ?: '[]', true) ?: [];
    }

    public function progress()
    {
        return $this->hasMany(StudentProgress::class, 'registration_id', 'id');
    }
}
