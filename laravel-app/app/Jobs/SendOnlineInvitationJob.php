<?php

namespace App\Jobs;

use App\Http\Controllers\Controller as BaseController;
use App\OnlineInvitation;
use App\Employee;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Support\OnlineInvitationQr;
use App\Support\OnlineInvitationUrl;
use PDF;

class SendOnlineInvitationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60000;

    protected $invitationId;
    protected $triggeredBy;
    protected $batchId;

    public function __construct($invitationId, $triggeredBy = null, $batchId = null)
    {
        $this->invitationId = $invitationId;
        $this->triggeredBy = $triggeredBy;
        $this->batchId = $batchId ? (int) $batchId : null;
    }

    public function handle()
    {
        $invitation = OnlineInvitation::with(['event.template', 'event.categories', 'user', 'customer', 'category'])
            ->where('is_active', 1)
            ->find($this->invitationId);

        if (!$invitation) {
            return;
        }

        if (!in_array($invitation->status, ['awaiting_sending', 'failed'], true)) {
            return;
        }

        $invitation->send_attempts = (int) $invitation->send_attempts + 1;
        $invitation->save();

        if (!$invitation->token) {
            $invitation->token = Str::random(48);
            $invitation->save();
        }

        $user = $invitation->user;
        $customer = $invitation->customer;
        $event = $invitation->event;

        try {
            if (!$event) {
                throw new \Exception('Event not found');
            }

            $controller = new BaseController();
            $acceptUrl = rtrim(env('APP_URL'), '/').'/online-invitation/invite/'.$invitation->token;

            // In this codebase, WhatsApp sending commonly uses `phone_number` (Employee/Customer),
            // while App\\User stores `phone`. Try both (User.phone first, then Employee.phone_number).
            $recipientName = $invitation->recipient_name ?: ($customer ? $customer->name : ($user ? $user->name : null));
            $recipientEmail = $invitation->recipient_email ?: ($customer ? $customer->email : ($user ? $user->email : null));
            $recipientEmail = trim((string) $recipientEmail);
            $recipientEmailLower = strtolower($recipientEmail);
            if ($recipientEmail === '' || in_array($recipientEmailLower, ['—', '-', 'n/a', 'na', 'null', 'none'], true)) {
                $recipientEmail = null;
            }

            $phone = $invitation->recipient_phone;
            if (!$phone && $customer) {
                $phone = $customer->phone_number;
            }
            if (!$phone && $user) {
                $phone = $user->phone;
            }
            if (!$phone && $user) {
                $employee = Employee::where('user_id', $user->id)->select('phone_number')->first();
                $phone = $employee ? $employee->phone_number : null;
            }
            if (!$phone) {
                throw new \Exception('Recipient phone is missing');
            }

            // Build a PDF invitation (with embedded QR code) and send as a WhatsApp document.
            // Hostinger PHP often lacks Imagick; OnlineInvitationQr uses GD/SVG instead.
            $qrDataUri = OnlineInvitationQr::dataUri($acceptUrl, 320, 1);

            $eventAt = $event->event_at;
            $eventAtText = $eventAt;
            try {
                if ($eventAt) {
                    $eventAtText = Carbon::parse($eventAt)->format('D, M d, Y h:i A');
                }
            } catch (\Throwable $e) {
                $eventAtText = $eventAt;
            }

            $templateBackground = $event->template ? (string) $event->template->background : '';
            $pdfBackground = $this->resolvePdfBackground($templateBackground);
            if (filter_var(env('ONLINE_INVITATION_DEBUG_BG', false), FILTER_VALIDATE_BOOLEAN)) {
                Log::info('OnlineInvitation PDF background resolved', [
                    'invitation_id' => $invitation->id,
                    'event_id' => $event->id ?? null,
                    'template_id' => $event->template->id ?? null,
                    'template_background' => $templateBackground,
                    'resolved' => [
                        'color' => $pdfBackground['color'] ?? null,
                        'has_image' => !empty($pdfBackground['image']),
                        'image_prefix' => !empty($pdfBackground['image']) ? substr((string) $pdfBackground['image'], 0, 32) : null,
                        'needs_remote' => (bool) ($pdfBackground['needsRemote'] ?? false),
                    ],
                ]);
            }

            $pdfData = [
                'recipientName' => $recipientName ?: 'Guest',
                'recipientPhone' => $phone,
                'recipientEmail' => $recipientEmail,
                'optionalMessage' => $invitation->message,
                'rsvp' => $invitation->rsvp,
                'borderColor' => $invitation->border_color ?: '#c8a75e',
                'fontColor' => $invitation->font_color ?: '#f3e7c1',
                'fontSize' => (int) ($invitation->font_size ?: optional($event->template)->font_size ?: 16),
                'eventName' => $event->name,
                'eventLocation' => $event->location,
                'eventAtText' => $eventAtText,
                'categoryName' => $invitation->category ? $invitation->category->name : null,
                'acceptUrl' => $acceptUrl,
                'qrDataUri' => $qrDataUri,
                'pdfBgColor' => $pdfBackground['color'],
                'pdfBgImage' => $pdfBackground['image'],
            ];

            // Write under storage/app/public (www-data writable). public/documents is often root-owned.
            $pdfDir = storage_path('app/public/online_invitation/');
            if (! File::exists($pdfDir)) {
                File::makeDirectory($pdfDir, 0775, true);
            }
            if (! is_dir($pdfDir) || ! is_writable($pdfDir)) {
                throw new \Exception('Invitation PDF directory is not writable: '.$pdfDir);
            }
            $pdfFilename = 'invitation_'.$invitation->id.'.pdf';
            $pdfPath = $pdfDir.$pdfFilename;

            $pdf = PDF::loadView('pdf.online_invitation_pdf', $pdfData);
            if ($pdfBackground['needsRemote']) {
                $pdf->setOptions(['isRemoteEnabled' => true]);
            }
            $pdf
                ->setPaper('A4', 'portrait')
                ->save($pdfPath);

            $attachmentPath = $pdfPath;
            $attachmentFilename = $pdfFilename;
            // Served via public/storage symlink → /public/storage/online_invitation/...
            $attachmentUrl = OnlineInvitationUrl::publicAsset(
                'storage/online_invitation/'.$pdfFilename
            );

            // Optional: convert the generated PDF into a PNG (first page) and send as image.
            // Enable by setting ONLINE_INVITATION_ATTACHMENT=png in .env.
            if (strtolower((string) env('ONLINE_INVITATION_ATTACHMENT', 'pdf')) === 'png' && extension_loaded('imagick')) {
                try {
                    $pngFilename = 'invitation_' . $invitation->id . '.png';
                    $pngPath = $pdfDir . $pngFilename;

                    $im = new \Imagick();
                    $im->setResolution(160, 160);
                    $im->readImage($pdfPath . '[0]');
                    $im->setImageFormat('png');
                    $im->setImageCompressionQuality(92);
                    $im->writeImage($pngPath);
                    $im->clear();
                    $im->destroy();

                    if (is_file($pngPath)) {
                        $attachmentPath = $pngPath;
                        $attachmentFilename = $pngFilename;
                        $attachmentUrl = OnlineInvitationUrl::publicAsset(
                            'storage/online_invitation/'.$pngFilename
                        );
                    }
                } catch (\Throwable $e) {
                    // Fallback to PDF silently.
                }
            }

            $pdfSent = false;
            $pdfSendError = null;
            try {
                if (!file_exists($attachmentPath)) {
                    throw new \Exception('Invitation file was not created at: ' . $attachmentPath);
                }
                $result = $controller->wpAttachMessage($attachmentPath, $phone, $attachmentFilename, $attachmentUrl);

                if (env('WHATSAPP_SERVICE') === 'WASENDER' && is_array($result) && ($result['status'] ?? null) === 'error') {
                    throw new \Exception('WASENDER attachment error: ' . ($result['message'] ?? 'Unknown error'));
                }
                $pdfSent = true;
            } catch (\Throwable $e) {
                $pdfSendError = substr((string) $e->getMessage(), 0, 2000);
                // If attachment fails, still try sending a minimal fallback text message.
                $fallback = "*Invitation*\n\n";
                if ($recipientName) {
                    $fallback .= "*Name:* " . $recipientName . "\n";
                }
                $fallback .= "*Event:* " . $event->name . "\n";
                $fallback .= "*Date & Time:* " . $eventAtText . "\n";
                if ($event->location) {
                    $fallback .= "*Location:* " . $event->location . "\n";
                }
                if ($invitation->rsvp) {
                    $fallback .= "*RSVP:* " . trim((string) $invitation->rsvp) . "\n";
                }
                $fallback .= "\nAccept / View:\n" . $acceptUrl . "\n";
                $controller->wpMessage($phone, $fallback);
            }

            // WASENDER fetches the document from `documentUrl` asynchronously, so deleting immediately can break delivery.
            if (env('WHATSAPP_SERVICE') !== 'WASENDER') {
                if (file_exists($pdfPath)) {
                    @unlink($pdfPath);
                }
                $pngPath = $pdfDir . 'invitation_' . $invitation->id . '.png';
                if (file_exists($pngPath)) {
                    @unlink($pngPath);
                }
            }

            $invitation->status = 'sent';
            $invitation->sent_at = date('Y-m-d H:i:s');
            $invitation->last_error = $pdfSent ? null : $pdfSendError;
            $invitation->save();
        } catch (\Throwable $e) {
            $invitation->status = 'failed';
            $invitation->last_error = substr((string) $e->getMessage(), 0, 2000);
            $invitation->save();
        }
    }

    private function resolvePdfBackground(?string $background): array
    {
        $background = trim((string) $background);
        if ($background === '') {
            return ['color' => '#ffffff', 'image' => null, 'needsRemote' => false];
        }

        $color = $this->normalizeCssColor($background);
        if ($color !== null) {
            return ['color' => $color, 'image' => null, 'needsRemote' => false];
        }

        $imageRef = $this->extractBackgroundImageReference($background);
        if ($imageRef === null) {
            return ['color' => '#ffffff', 'image' => null, 'needsRemote' => false];
        }

        if (preg_match('#^data:image/[^;]+;base64,#i', $imageRef)) {
            return ['color' => '#ffffff', 'image' => $imageRef, 'needsRemote' => false];
        }

        if (preg_match('#^https?://#i', $imageRef)) {
            // Prefer local embedding for same-domain URLs (more reliable than dompdf remote fetching in production).
            $sameHostDataUri = $this->tryResolveSameHostUrlToDataUri($imageRef);
            if ($sameHostDataUri !== null) {
                return ['color' => '#ffffff', 'image' => $sameHostDataUri, 'needsRemote' => false];
            }

            // Production setup serves public assets under `/public/...` on the same domain.
            // Keep the domain as-is, but ensure the path contains `/public` so remote PDF rendering can fetch it.
            $imageRef = OnlineInvitationUrl::ensurePublicInAppUrl($imageRef);
            return ['color' => '#ffffff', 'image' => $imageRef, 'needsRemote' => true];
        }

        $publicRelativePath = ltrim($imageRef, '/');
        $dataUri = $this->tryResolvePublicRelativePathToDataUri($publicRelativePath);
        if ($dataUri === null) {
            return ['color' => '#ffffff', 'image' => null, 'needsRemote' => false];
        }
        return [
            'color' => '#ffffff',
            'image' => $dataUri,
            'needsRemote' => false,
        ];
    }

    private function tryResolveSameHostUrlToDataUri(string $url): ?string
    {
        $appUrl = trim((string) env('APP_URL'));
        if ($appUrl === '' || !preg_match('#^https?://#i', $appUrl) || !preg_match('#^https?://#i', $url)) {
            return null;
        }

        $appParts = @parse_url($appUrl) ?: null;
        $urlParts = @parse_url($url) ?: null;
        if (!$appParts || !$urlParts) {
            return null;
        }

        $appHost = strtolower((string) ($appParts['host'] ?? ''));
        $urlHost = strtolower((string) ($urlParts['host'] ?? ''));
        $appHost = preg_replace('/^www\\./i', '', $appHost);
        $urlHost = preg_replace('/^www\\./i', '', $urlHost);
        if ($appHost === '' || $urlHost === '' || $appHost !== $urlHost) {
            return null;
        }

        $path = (string) ($urlParts['path'] ?? '');
        if ($path === '') {
            return null;
        }

        return $this->tryResolvePublicRelativePathToDataUri(ltrim($path, '/'));
    }

    private function tryResolvePublicRelativePathToDataUri(string $publicRelativePath): ?string
    {
        $publicRelativePath = ltrim($publicRelativePath, '/');
        if ($publicRelativePath === '') {
            return null;
        }

        $pathsToTry = [public_path($publicRelativePath)];
        if (strpos($publicRelativePath, 'public/') === 0) {
            $pathsToTry[] = public_path(substr($publicRelativePath, 7));
        }

        foreach (array_values(array_unique($pathsToTry)) as $path) {
            $dataUri = $this->localImagePathToDataUri($path);
            if ($dataUri !== null) {
                return $dataUri;
            }
        }

        return null;
    }

    private function localImagePathToDataUri(string $localPath): ?string
    {
        if (!is_file($localPath)) {
            return null;
        }

        $mimeType = null;
        if (function_exists('mime_content_type')) {
            $mimeType = @mime_content_type($localPath) ?: null;
        }
        if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
            $extension = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
            $extensionMap = [
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'bmp' => 'image/bmp',
                'svg' => 'image/svg+xml',
            ];
            $mimeType = $extensionMap[$extension] ?? null;
        }
        if (!$mimeType || strpos($mimeType, 'image/') !== 0) {
            return null;
        }

        $imageData = @file_get_contents($localPath);
        if ($imageData === false) {
            return null;
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
    }

    private function extractBackgroundImageReference(string $background): ?string
    {
        $background = trim($background);
        if ($background === '') {
            return null;
        }

        if (preg_match('/url\\(([^)]+)\\)/i', $background, $matches)) {
            $background = $matches[1];
        }

        $background = trim($background, " \t\n\r\0\x0B'\"");
        if ($background === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $background)) {
            return $background;
        }

        if (preg_match('#^data:image/[^;]+;base64,#i', $background)) {
            return $background;
        }

        if (preg_match('#\\.(png|jpe?g|gif|webp|bmp|svg)$#i', $background) || substr($background, 0, 1) === '/') {
            return $background;
        }

        return null;
    }

    private function normalizeCssColor(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return $value;
        }

        if (preg_match('/^rgba?\\(\\s*\\d{1,3}\\s*,\\s*\\d{1,3}\\s*,\\s*\\d{1,3}(\\s*,\\s*(0(\\.\\d+)?|1(\\.0+)?))?\\s*\\)$/i', $value)) {
            return $value;
        }

        if (preg_match('/^hsla?\\(\\s*\\d{1,3}\\s*,\\s*\\d{1,3}%\\s*,\\s*\\d{1,3}%(\\s*,\\s*(0(\\.\\d+)?|1(\\.0+)?))?\\s*\\)$/i', $value)) {
            return $value;
        }

        if (strcasecmp($value, 'transparent') === 0) {
            return 'transparent';
        }

        return null;
    }
}
