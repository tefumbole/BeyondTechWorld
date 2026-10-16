<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixImportReview extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_import_reviews';

    protected $fillable = [
        'import_file_id', 'preset_key', 'preset_name', 'instrument_slug', 'field',
        'extracted_value', 'status', 'note', 'confirmed_value', 'confirmed',
    ];

    protected $casts = ['confirmed' => 'boolean'];
}
