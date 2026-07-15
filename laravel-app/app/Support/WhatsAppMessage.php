<?php

namespace App\Support;

use App\GeneralSetting;

class WhatsAppMessage
{
    public static function companyName()
    {
        $fromEnv = trim((string) config('services.whatsapp.company_name', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $general = GeneralSetting::first();

        return $general->site_title ?? config('app.name', 'Application');
    }

    public static function statusBlock($emoji, $title)
    {
        return $emoji . ' *' . strtoupper($title) . "*\n━━━━━━━━━━━━━━━━\n";
    }

    public static function greeting($name)
    {
        return 'Hello *' . trim($name) . "*,\n\n";
    }

    public static function bullet($label, $value)
    {
        return "◾ *{$label}:* {$value}\n";
    }

    public static function actionLink($label, $url)
    {
        return "\n👉 *{$label}:*\n{$url}\n";
    }

    public static function footer()
    {
        return "\n_" . self::companyName() . '_';
    }

    public static function signatureRequest($customerName, $bookingRef, $signUrl, $company = null, $contractType = null)
    {
        $company = $company ?: self::companyName();
        if ($contractType === 'accommodation') {
            $heading = 'Accommodation Agreement';
            $body = "Please review and sign your student accommodation agreement with *{$company}*.\n\n";
        } elseif ($contractType === 'software_license') {
            $heading = 'Software License Subscription';
            $body = "Please review and sign your software license / subscription agreement with *{$company}*.\n\n";
        } else {
            $heading = 'Rental Agreement';
            $body = "Please review and sign your equipment rental agreement with *{$company}*.\n\n";
        }

        $msg = self::statusBlock('📝', $heading);
        $msg .= self::greeting($customerName);
        $msg .= $body;
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= self::actionLink('Sign agreement', $signUrl);
        $msg .= "\nYour booking receipt will be generated after admin review. You will read the agreement, sign digitally, and upload your ID card. After approval you can access your client portal.";
        $msg .= self::footer();

        return $msg;
    }

    public static function pendingReviewNotice($adminName, $customerName, $bookingRef, $reviewUrl)
    {
        $msg = self::statusBlock('⏳', 'Contract Pending Review');
        $msg .= self::greeting($adminName);
        $msg .= "*{$customerName}* has signed rental agreement *{$bookingRef}*. Please review and countersign.\n\n";
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= self::bullet('Customer', $customerName);
        $msg .= self::actionLink('Review & sign', $reviewUrl);
        $msg .= self::footer();

        return $msg;
    }

    public static function contractApprovedClient($customerName, $bookingRef, $portalUrl, $username = null, $password = null)
    {
        $msg = self::statusBlock('✅', 'Contract Approved');
        $msg .= self::greeting($customerName);
        $msg .= "Your signed rental agreement for booking *{$bookingRef}* has been approved.\n\n";
        $msg .= "Signed PDF and QR code are attached.\n";
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= self::actionLink('Client portal', $portalUrl);
        if ($username && $password) {
            $msg .= self::bullet('Username', $username);
            $msg .= self::bullet('Password', $password);
        }
        $msg .= "\nScan the QR code to view rented equipment and return dates.";
        $msg .= self::footer();

        return $msg;
    }

    public static function contractApprovedStaff($staffName, $customerName, $bookingRef, $scanUrl)
    {
        $msg = self::statusBlock('✅', 'Contract Finalized');
        $msg .= self::greeting($staffName);
        $msg .= "Rental agreement *{$bookingRef}* for *{$customerName}* is fully signed.\n\n";
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= self::bullet('Customer', $customerName);
        $msg .= self::actionLink('QR scan page', $scanUrl);
        $msg .= "\nSigned PDF copy is attached.";
        $msg .= self::footer();

        return $msg;
    }

    public static function awaitingSignatureNotice($staffName, $customerName, $bookingRef, $awaitingUrl)
    {
        $msg = self::statusBlock('📨', 'Awaiting Client Signature');
        $msg .= self::greeting($staffName);
        $msg .= "Rental agreement *{$bookingRef}* is waiting for *{$customerName}* to sign.\n\n";
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= self::bullet('Customer', $customerName);
        $msg .= self::actionLink('View awaiting list', $awaitingUrl);
        $msg .= self::footer();

        return $msg;
    }

    public static function bookingConfirmation($customerName, $referenceNo, $orderDate, array $lines, $grandTotal, $payingMethod, $facilityName, $facilityAddress, $facilityPhone, $bookingNote = '')
    {
        $msg = self::statusBlock('✅', 'Booking Confirmed');
        $msg .= self::greeting($customerName);
        $msg .= self::bullet('Order Number', $referenceNo);
        $msg .= self::bullet('Order Date', $orderDate);
        $msg .= "\n*Products:*\n";

        foreach ($lines as $index => $line) {
            $msg .= ($index + 1) . ") {$line['name']} × {$line['qty']} = {$line['total']}\n";
            $msg .= "   Start: {$line['start']}\n";
            $msg .= "   End: {$line['end']}\n";
        }

        if ($bookingNote !== '') {
            $msg .= "\n*Special Requests:*\n{$bookingNote}\n";
        }

        $msg .= "\n*Facility:*\n";
        $msg .= self::bullet('Name', $facilityName);
        $msg .= self::bullet('Address', $facilityAddress);
        $msg .= self::bullet('Contact', $facilityPhone);
        $msg .= "\n*Payment:*\n";
        $msg .= self::bullet('Total', $grandTotal);
        $msg .= self::bullet('Method', $payingMethod);
        $msg .= "\nThank you for choosing *" . self::companyName() . '*.';
        $msg .= self::footer();

        return $msg;
    }

    public static function lateReturnNotice($customerName, $company, $productName, $returnAt, $bookingRef, $dailyRate)
    {
        $msg = self::statusBlock('⚠️', 'Late Equipment Return');
        $msg .= self::greeting($customerName);
        $msg .= "Our records show rented equipment from *{$company}* was not returned by the agreed date.\n\n";
        $msg .= self::bullet('Equipment', $productName);
        $msg .= self::bullet('Required return', $returnAt);
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= "\nPer your signed agreement, late return incurs an additional full-day charge (approx. {$dailyRate}) per day or part thereof, plus repair/replacement costs for damage.\n\n";
        $msg .= 'Please return the equipment immediately or contact us to resolve this matter.';
        $msg .= self::footer();

        return $msg;
    }

    public static function otpMessage($otp)
    {
        $msg = self::statusBlock('🔐', 'Verification Code');
        $msg .= 'Your OTP for *' . self::companyName() . "* is:\n\n";
        $msg .= "*{$otp}*\n\n";
        $msg .= "This code expires in 5 minutes. Do not share it.";
        $msg .= self::footer();

        return $msg;
    }

    public static function clientSignedPendingReview($customerName, $bookingRef, $reviewUrl = null)
    {
        $msg = self::statusBlock('✅', 'Agreement Signed');
        $msg .= self::greeting($customerName);
        $msg .= "Thank you for signing rental agreement *{$bookingRef}*.\n\n";
        $msg .= "Your signed contract PDF is attached. Our team will review and countersign shortly.\n";
        $msg .= self::bullet('Booking Ref', $bookingRef);
        if ($reviewUrl) {
            $msg .= self::actionLink('View status', $reviewUrl);
        }
        $msg .= self::footer();

        return $msg;
    }

    public static function bookingQuotationCc($recipientName, $bookingRef, array $lines, $customerName, $bookingNote = '')
    {
        $msg = self::statusBlock('📋', 'Quotation Copy');
        $msg .= self::greeting($recipientName);
        $msg .= "You are copied on equipment quotation *{$bookingRef}* for *{$customerName}*.\n\n";
        $msg .= "*Equipment (no pricing):*\n";

        foreach ($lines as $index => $line) {
            $msg .= ($index + 1) . ") {$line['name']} × {$line['qty']}\n";
            $msg .= "   From: {$line['start']}\n";
            $msg .= "   To: {$line['end']}\n";
        }

        if ($bookingNote !== '') {
            $plainNote = \App\Support\BookingNoteFormatter::forPlainText($bookingNote);
            if ($plainNote !== '') {
                $msg .= "\n*Notes:*\n";
                foreach (preg_split('/\r\n|\r|\n/', $plainNote) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $msg .= "• {$line}\n";
                    }
                }
            }
        }

        $msg .= "\nThis copy excludes pricing. For full details contact the booking team.";
        $msg .= self::footer();

        return $msg;
    }

    public static function goodsReceivedSignatureRequest($customerName, $bookingRef, $deliveryRef, $signUrl, array $items = [], $role = 'received')
    {
        $isDelivered = $role === 'delivered';

        $msg = self::statusBlock('📦', 'Goods Delivery');
        $msg .= self::greeting($customerName);

        if ($isDelivered) {
            $msg .= "Please confirm you *delivered* the equipment for booking *{$bookingRef}*.\n\n";
        } else {
            $msg .= "Please confirm receipt of equipment delivered under booking *{$bookingRef}*.\n\n";
        }

        $msg .= self::bullet('Delivery Note', $deliveryRef);
        $msg .= self::bullet('Booking Ref', $bookingRef);

        if (!empty($items)) {
            $msg .= "\n*Equipment (no pricing):*\n";
            foreach ($items as $index => $item) {
                $msg .= ($index + 1) . ') ' . $item['name'] . ' × ' . $item['qty'] . "\n";
            }
        }

        if ($isDelivered) {
            $msg .= self::actionLink('Sign as delivered', $signUrl);
            $msg .= "\nReview the item list and sign to confirm you delivered the goods.";
        } else {
            $msg .= self::actionLink('Sign goods received', $signUrl);
            $msg .= "\nReview the item list and sign to confirm you received the goods.";
        }

        $msg .= self::footer();

        return $msg;
    }

    public static function goodsReceivedSignedClient($customerName, $bookingRef, $deliveryRef)
    {
        $msg = self::statusBlock('✅', 'Goods Received');
        $msg .= self::greeting($customerName);
        $msg .= "Thank you for confirming receipt of equipment for booking *{$bookingRef}*.\n\n";
        $msg .= self::bullet('Delivery Note', $deliveryRef);
        $msg .= self::bullet('Booking Ref', $bookingRef);
        $msg .= "\nSigned goods received document is attached.";
        $msg .= self::footer();

        return $msg;
    }

    public static function bookingScheduledReminder($customerName, $referenceNo, $remindAtFormatted, $customMessage = '')
    {
        $msg = self::statusBlock('🔔', 'Booking Reminder');
        $msg .= self::greeting($customerName);
        $msg .= "This is your scheduled reminder for booking *{$referenceNo}*.\n\n";
        $msg .= self::bullet('Scheduled for', $remindAtFormatted);
        if (trim($customMessage) !== '') {
            $msg .= "\n*Message:*\n{$customMessage}\n";
        }
        $msg .= "\nPlease contact us if you have any questions about your booking.";
        $msg .= self::footer();

        return $msg;
    }
}
