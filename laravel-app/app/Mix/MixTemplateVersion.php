<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixTemplateVersion extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_template_versions';

    protected $fillable = ['template_id', 'version_number', 'notes'];

    public function template()
    {
        return $this->belongsTo(MixTemplate::class, 'template_id');
    }

    public function channels()
    {
        return $this->hasMany(MixTemplateChannel::class, 'template_version_id')->orderBy('position');
    }
}
