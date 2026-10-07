<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YongGallery extends Model
{
    protected $table = 'yong_gallery';

    protected $fillable = [
        'name',
        'image_file',
        'caption',
    ];

    public function imageUrl()
    {
        return asset('public/yong/gallery/'.$this->image_file);
    }
}
