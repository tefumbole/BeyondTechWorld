<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixShow extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_shows';

    protected $fillable = [
        'console_id', 'template_version_id', 'name', 'slug', 'event_type', 'version_label',
        'main_notes', 'monitor_notes', 'subwoofer_notes', 'bus_notes', 'notes', 'published',
    ];

    protected $casts = ['published' => 'boolean'];

    public function console()
    {
        return $this->belongsTo(MixConsole::class, 'console_id');
    }

    public function templateVersion()
    {
        return $this->belongsTo(MixTemplateVersion::class, 'template_version_id');
    }
}
