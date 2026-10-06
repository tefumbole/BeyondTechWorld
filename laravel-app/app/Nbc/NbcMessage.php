<?php

namespace App\Nbc;

class NbcMessage extends NbcModel
{
    protected $table = 'nbc_messages';

    protected $fillable = ['audience', 'member_id', 'body', 'author_id'];

    public function member()
    {
        return $this->belongsTo(NbcMember::class, 'member_id');
    }
}
