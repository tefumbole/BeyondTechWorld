<?php

namespace App\Services;

use App\CompanyReviewInvite;
use App\DocumentPaymentLink;

class CompanyReviewInviteService
{
    public function sendForDocumentPayment(DocumentPaymentLink $link)
    {
        $phone = trim((string) $link->phone);
        if ($phone === '') {
            return;
        }

        $existing = CompanyReviewInvite::where('source', 'document_payment')
            ->where('source_id', $link->id)
            ->first();
        if ($existing) {
            return;
        }

        $invite = new CompanyReviewInvite();
        $invite->token = bin2hex(random_bytes(16));
        $invite->phone = $phone;
        $invite->person_name = trim((string) $link->person_name);
        $invite->source = 'document_payment';
        $invite->source_id = $link->id;
        $invite->save();

        $name = $invite->person_name !== '' ? $invite->person_name : 'there';
        $url = url('/reviews/write/'.$invite->token);
        $text = 'Dear '.$name.', thank you for choosing Beyond Company Limited. Please review us: '.$url;

        try {
            app(ClientNoticeService::class)->send($phone, $text);
        } catch (\Throwable $e) {
            \Log::warning('Review invite notice failed', ['error' => $e->getMessage()]);
        }

        $invite->sent_at = date('Y-m-d H:i:s');
        $invite->save();
    }
}
