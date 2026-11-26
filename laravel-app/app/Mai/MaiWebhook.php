<?php

namespace App\Mai;

use Illuminate\Database\Eloquent\Model;

class MaiWebhook extends Model
{
    protected $table = 'mai_webhooks';

    protected $fillable = ['user_id', 'mai_session_id', 'url', 'secret', 'active'];

    protected $hidden = ['secret'];

    public function secretPlain()
    {
        if (! isset($this->attributes['secret']) || $this->attributes['secret'] === '') {
            return '';
        }
        try {
            $plain = decrypt($this->attributes['secret']);
        } catch (\Throwable $e) {
            return '';
        }

        return is_string($plain) ? $plain : '';
    }
}
