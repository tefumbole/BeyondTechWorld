<?php

namespace App\Services\Contracts;

use App\BtwContract;
use App\ContractDocument;
use App\GeneralSetting;
use App\Support\Letterhead;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDF;

class ContractPdfService
{
    protected $instances;

    public function __construct(ContractInstanceService $instances)
    {
        $this->instances = $instances;
    }

    public function previewHtml(BtwContract $contract, $draftWatermark = true)
    {
        $body = $this->instances->renderedHtml($contract);
        $letterhead = Letterhead::ensureSynced();
        $general_setting = GeneralSetting::query()->orderByDesc('id')->first();

        return view('pdf.contract_pdf', [
            'contract' => $contract,
            'bodyHtml' => $body,
            'letterhead' => $letterhead,
            'general_setting' => $general_setting,
            'draftWatermark' => $draftWatermark && ! $contract->isSigned(),
            'isCertificate' => false,
        ])->render();
    }

    public function streamPreview(BtwContract $contract)
    {
        $html = $this->previewHtml($contract, true);
        $pdf = PDF::loadHTML($html)->setPaper('A4', 'portrait');

        return $pdf->stream($contract->number.'-preview.pdf');
    }

    public function generateFinal(BtwContract $contract)
    {
        $revision = $contract->currentRevision;
        if (! $revision) {
            throw new \RuntimeException('No revision to finalize.');
        }

        $dir = storage_path('app/contracts/'.$contract->id);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $html = $this->previewHtml($contract, false);
        $pdf = PDF::loadHTML($html)->setPaper('A4', 'portrait');
        $relative = 'contracts/'.$contract->id.'/final-'.$revision->revision_no.'.pdf';
        $absolute = storage_path('app/'.$relative);
        File::put($absolute, $pdf->output());
        $checksum = hash_file('sha256', $absolute);

        $doc = ContractDocument::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'revision_id' => $revision->id,
            'kind' => 'final',
            'file_path' => $relative,
            'checksum' => $checksum,
            'immutable' => true,
            'generated_at' => now(),
            'render_engine' => 'dompdf',
        ]);

        $certHtml = view('pdf.contract_certificate', [
            'contract' => $contract,
            'revision' => $revision,
            'checksum' => $checksum,
            'letterhead' => Letterhead::ensureSynced(),
            'general_setting' => GeneralSetting::query()->orderByDesc('id')->first(),
        ])->render();
        $certPdf = PDF::loadHTML($certHtml)->setPaper('A4', 'portrait');
        $certRel = 'contracts/'.$contract->id.'/certificate-'.$revision->revision_no.'.pdf';
        File::put(storage_path('app/'.$certRel), $certPdf->output());
        ContractDocument::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'revision_id' => $revision->id,
            'kind' => 'certificate',
            'file_path' => $certRel,
            'checksum' => hash_file('sha256', storage_path('app/'.$certRel)),
            'immutable' => true,
            'generated_at' => now(),
            'render_engine' => 'dompdf',
        ]);

        return $doc;
    }
}
