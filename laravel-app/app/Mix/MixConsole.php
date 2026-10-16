<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixConsole extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_consoles';

    protected $fillable = ['manufacturer_id', 'name', 'model', 'channel_count', 'notes'];

    public function manufacturer()
    {
        return $this->belongsTo(MixManufacturer::class, 'manufacturer_id');
    }
}
