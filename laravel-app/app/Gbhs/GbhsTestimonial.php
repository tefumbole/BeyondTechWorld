<?php

namespace App\Gbhs;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class GbhsTestimonial extends Model
{
    use BelongsToCloudTenant;

    protected $table = 'gbhs_testimonials';

    protected $guarded = ['id', 'cloud_tenant_id'];

    public function paragraphs()
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $this->body));
        $blocks = preg_split("/\n{2,}/", $text);
        if (count($blocks) === 1) {
            $blocks = preg_split("/\n/", $text);
        }

        return array_values(array_filter(array_map('trim', $blocks), function ($p) {
            return $p !== '';
        }));
    }
}
