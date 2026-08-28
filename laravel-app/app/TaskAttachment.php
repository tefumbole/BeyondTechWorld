<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
    protected $table = 'task_attachments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'task_id', 'update_id', 'file_name', 'file_url', 'attachment_type'];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    public function href()
    {
        $url = ltrim((string) $this->file_url, '/');
        if ($url === '') {
            return '#';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (strpos($url, 'public/') === 0) {
            return url($url);
        }

        return url('public/'.$url);
    }

    public function isImage()
    {
        $name = strtolower((string) $this->file_name.' '.$this->file_url);

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $name);
    }
}
