<?php

namespace App\Mix;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class MixImportFile extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'mix_import_files';

    protected $fillable = ['original_name', 'stored_path', 'status', 'extracted_text'];

    public function reviews()
    {
        return $this->hasMany(MixImportReview::class, 'import_file_id')->orderBy('id');
    }
}
