<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcLetter extends Model
{
    protected $table = 'nbc_letters';

    protected $fillable = ['title', 'recipient_name', 'recipient_id', 'letter_date', 'body', 'author_id'];

    protected $dates = ['letter_date'];
}
