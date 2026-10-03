<?php

namespace App\Assistant;

use Illuminate\Database\Eloquent\Model;

class AssistantBrief extends Model
{
    protected $table = 'assistant_briefs';

    protected $fillable = [
        'cloud_tenant_id', 'title', 'starts_at', 'ends_at', 'details', 'enabled', 'updated_by',
    ];

    protected $dates = ['starts_at', 'ends_at'];

    protected $casts = ['enabled' => 'boolean'];
}
