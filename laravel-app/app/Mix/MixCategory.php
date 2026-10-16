<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixCategory extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_categories';

    protected $fillable = ['name', 'slug', 'sort'];

    public function instruments()
    {
        return $this->hasMany(MixInstrument::class, 'category_id')->orderBy('sort');
    }
}
