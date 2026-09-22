<?php

namespace App\Assistant;

use App\User;
use Illuminate\Database\Eloquent\Model;

class AssistantKnowledge extends Model
{
    protected $table = 'assistant_knowledge';

    protected $fillable = ['title', 'category', 'content', 'enabled', 'updated_by'];

    protected $casts = ['enabled' => 'boolean'];

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
