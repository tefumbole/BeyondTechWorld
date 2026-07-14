<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GalleryItem extends Model
{
    protected $fillable = [
        'type',
        'title',
        'description',
        'file_path',
        'media_url',
        'sort_order',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Public URL for an uploaded file. */
    public function fileUrl()
    {
        if (! $this->file_path) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $this->file_path) || strpos($this->file_path, '/') === 0) {
            return $this->file_path;
        }

        return url('public/' . ltrim($this->file_path, '/'));
    }
}
