<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LetterAttachment extends Model
{
    protected $guarded = [];

    public function letters() {
        return $this->belongsTo('App\Letter', 'letter_id', 'id');
    }
}
