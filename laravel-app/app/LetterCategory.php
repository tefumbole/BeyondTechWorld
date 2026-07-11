<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LetterCategory extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    public function category() {
        return $this->hasMany('App\Letter', 'category_id', 'id');
    }
}
