<?php

namespace App\Jobs;

use App\Services\Internship\InternshipWhatsAppService;
use App\WhatsApp\InternshipIntakeFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetrieveInternshipMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 60;

    protected $fileId;

    public function __construct($fileId)
    {
        $this->fileId = (int) $fileId;
    }

    public function handle(InternshipWhatsAppService $service)
    {
        $file = InternshipIntakeFile::find($this->fileId);
        if (! $file || $file->status === 'stored') {
            return;
        }
        $url = (string) $file->url;
        if (! preg_match('#^https://#i', $url)) {
            $service->failMedia($file, 'unsupported_url');

            return;
        }
        $handle = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT => 20,
        ];
        if (defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            if (defined('CURLOPT_REDIR_PROTOCOLS')) {
                $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
            }
        }
        curl_setopt_array($handle, $options);
        $bytes = curl_exec($handle);
        $error = curl_error($handle);
        $http = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $mime = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $effective = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        curl_close($handle);
        if (! preg_match('#^https://#i', $effective)) {
            $service->failMedia($file, 'unsupported_url');

            return;
        }
        if ($bytes === false || $http >= 400 || $bytes === '') {
            $service->failMedia($file, $error !== '' ? $error : ('http '.$http));

            return;
        }
        if (strlen($bytes) > \App\Support\InternshipSubmissionFileGuard::maxBytes()) {
            $service->failMedia($file, 'too_large');

            return;
        }
        $mime = is_string($mime) ? trim(strtok($mime, ';')) : $file->mime;
        $result = $service->completeMediaDownload($file, $bytes, $mime);
        if (empty($result['success'])) {
            $service->failMedia($file->fresh(), isset($result['error']) ? $result['error'] : 'rejected');
        }
    }
}
