<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcMessage extends Model
{
    protected $table = 'nbc_messages';

    protected $fillable = ['audience', 'member_id', 'body', 'author_id'];

    public function member()
    {
        return $this->belongsTo(NbcMember::class, 'member_id');
    }
}
