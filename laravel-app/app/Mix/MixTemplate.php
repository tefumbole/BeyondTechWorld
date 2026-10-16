<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixTemplate extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_templates';

    protected $fillable = ['console_id', 'name', 'slug', 'channel_count', 'notes', 'published', 'current_version_id'];

    protected $casts = ['published' => 'boolean'];

    public function console()
    {
        return $this->belongsTo(MixConsole::class, 'console_id');
    }

    public function versions()
    {
        return $this->hasMany(MixTemplateVersion::class, 'template_id')->orderBy('version_number');
    }

    public function currentVersion()
    {
        return $this->belongsTo(MixTemplateVersion::class, 'current_version_id');
    }
}
