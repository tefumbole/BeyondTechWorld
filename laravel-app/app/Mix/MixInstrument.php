<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixInstrument extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_instruments';

    protected $fillable = ['category_id', 'name', 'slug', 'sort'];

    public function category()
    {
        return $this->belongsTo(MixCategory::class, 'category_id');
    }

    public function presets()
    {
        return $this->hasMany(MixPreset::class, 'instrument_id');
    }
}
