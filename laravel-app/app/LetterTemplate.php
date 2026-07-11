<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LetterTemplate extends Model
{
    protected $guarded = [];

    public function category() {
        return $this->belongsTo('App\LetterCategory', 'category_id', 'id');
    }

    public function createdBy() {
        return $this->belongsTo('App\User', 'created_by', 'id');
    }
}
