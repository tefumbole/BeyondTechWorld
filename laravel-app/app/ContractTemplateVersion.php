<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractTemplateVersion extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'template_id', 'version_no', 'content_html', 'content_json', 'placeholder_schema',
        'signature_workflow_json', 'checksum', 'published_at', 'published_by',
    ];

    protected $dates = ['published_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function template()
    {
        return $this->belongsTo(ContractTemplate::class, 'template_id');
    }

    public function getPlaceholderSchemaAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setPlaceholderSchemaAttribute($value)
    {
        $this->attributes['placeholder_schema'] = is_string($value) ? $value : json_encode($value ?: []);
    }

    public function getSignatureWorkflowJsonAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setSignatureWorkflowJsonAttribute($value)
    {
        $this->attributes['signature_workflow_json'] = is_string($value) ? $value : json_encode($value ?: []);
    }
}
