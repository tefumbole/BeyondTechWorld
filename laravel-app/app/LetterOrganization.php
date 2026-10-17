<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LetterOrganization extends Model
{
    protected $fillable = ['name', 'reference_prefix', 'logo', 'header', 'footer'];

    public function letters()
    {
        return $this->hasMany(Letter::class, 'organization_id');
    }
}
