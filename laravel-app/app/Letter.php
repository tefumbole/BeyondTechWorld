<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Letter extends Model
{
    protected $guarded = [];

    public function category() {
        return $this->belongsTo('App\LetterCategory', 'category_id', 'id');
    }

    public function createdBy() {
        return $this->belongsTo('App\User', 'created_by', 'id');
    }

    public function editedBy() {
        return $this->belongsTo('App\User', 'edit_by', 'id');
    }

    public function rejectedBy() {
        return $this->belongsTo('App\User', 'reject_by', 'id');
    }

    public function approvedBy() {
        return $this->belongsTo('App\User', 'approved_by', 'id');
    }

    public function signedBy() {
        return $this->belongsTo('App\User', 'signed_by', 'id');
    }

    public function sentBy() {
        return $this->belongsTo('App\User', 'sent_by', 'id');
    }

    public function attachmentLib() {
        return $this->hasMany('App\LetterAttachment', 'letter_id', 'id');
    }
}
