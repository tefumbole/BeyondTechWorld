<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BirthdayFlyer extends Model
{
    protected $table = 'birthday_flyers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id', 'phone', 'display_name', 'call_name', 'template', 'has_selfie', 'output_file',
    ];

    protected $casts = [
        'has_selfie' => 'boolean',
    ];

    public function publicUrl()
    {
        return asset('public/birthday/mambole/out/'.$this->output_file);
    }

    public function absolutePath()
    {
        return public_path('birthday/mambole/out/'.$this->output_file);
    }
}
