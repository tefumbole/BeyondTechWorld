<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvitationGuest extends Model
{
    protected $connection = 'beyond_data';

    protected $table = 'guests';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'phone',
        'email',
        'company',
        'title',
        'user_id',
    ];
}
