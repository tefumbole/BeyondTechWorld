<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvitationEvent extends Model
{
    protected $connection = 'beyond_data';

    protected $table = 'events';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'event_date',
        'location',
        'status',
    ];
}
