<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class InternshipIntakeFile extends Model
{
    protected $table = 'whatsapp_internship_intake_files';

    protected $fillable = [
        'intake_id', 'provider_message_id', 'kind', 'disk', 'path', 'original_name',
        'mime', 'size', 'checksum', 'url', 'status', 'error',
    ];

    public function intake()
    {
        return $this->belongsTo(InternshipIntake::class, 'intake_id');
    }
}
