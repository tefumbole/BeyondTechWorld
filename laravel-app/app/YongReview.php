<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YongReview extends Model
{
    protected $table = 'yong_reviews';

    protected $fillable = [
        'invitation_id',
        'name',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];
}
