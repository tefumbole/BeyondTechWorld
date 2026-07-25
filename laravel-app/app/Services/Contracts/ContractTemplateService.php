<?php

namespace App\Services\Contracts;

use App\ContractTemplate;
use App\ContractTemplateVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ContractTemplateService
{
    public function publish(ContractTemplate $template, $contentHtml, array $schema = [], array $workflow = [])
    {
        $next = ((int) $template->versions()->max('version_no')) + 1;
        $version = ContractTemplateVersion::create([
            'id' => (string) Str::uuid(),
            'template_id' => $template->id,
            'version_no' => max(1, $next),
            'content_html' => $contentHtml,
            'placeholder_schema' => $schema,
            'signature_workflow_json' => $workflow,
            'checksum' => hash('sha256', (string) $contentHtml),
            'published_at' => now(),
            'published_by' => Auth::id(),
        ]);
        $template->current_version_id = $version->id;
        $template->save();

        return $version;
    }
}
