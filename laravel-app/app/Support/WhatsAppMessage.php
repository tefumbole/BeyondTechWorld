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

    public static function otpPurposeLabel($purpose = null)
    {
        $key = strtolower(trim((string) $purpose));
        $map = [
            'login' => 'Login verification',
            'password_reset' => 'Password reset',
            'password reset' => 'Password reset',
            'reset' => 'Password reset',
            'register' => 'Account registration',
            'verify' => 'Account verification',
        ];

        return $map[$key] ?? ($purpose ? ucwords(str_replace('_', ' ', (string) $purpose)) : 'Login verification');
    }

    /**
     * Standard OTP / authentication WhatsApp template.
     * Heading is always "Authentication".
     */
    public static function otpMessage($otp, $purpose = 'login', $expiresMinutes = 10)
    {
        $company = self::companyName();
        $purposeLabel = self::otpPurposeLabel($purpose);
        $minutes = max(1, (int) $expiresMinutes);

        $msg = self::statusBlock('🔐', 'Authentication');
        $msg .= "Welcome to *{$company}*.\n\n";
        $msg .= "Your one-time passcode (OTP) is:\n\n";
        $msg .= "👉 *{$otp}*\n\n";
        $msg .= "━━━━━━━━━━━━━━━━\n";
        $msg .= self::bullet('Purpose', $purposeLabel);
        $msg .= self::bullet('Expires in', "{$minutes} minutes");
        $msg .= "\n⚠️ *Security notice:* Never share this code with anyone. Our team will never ask for your OTP.";
        $msg .= self::footer();

        return $msg;
    }

    public static function accountCreated($name, $phone, $password, $loginUrl = null, $note = null)
    {
        $msg = self::statusBlock('🎉', 'Account Created');
        $msg .= self::greeting($name);
        $msg .= "Your account on *" . self::companyName() . "* has been created.\n\n";
        $msg .= self::bullet('Name', $name);
        $msg .= self::bullet('Phone', $phone);
        $msg .= self::bullet('Password', $password);
        if ($loginUrl) {
            $msg .= self::actionLink('Sign in', $loginUrl);
        }
        if ($note) {
            $msg .= "\n*Note:* {$note}\n";
        }
        $msg .= "\nPlease change your password after first login.";
        $msg .= self::footer();

        return $msg;
    }

    public static function applicationUnderReview($name, $jobTitle, $reference, $isInternship = false)
    {
        $kind = $isInternship ? 'Internship' : 'Job';
        $msg = self::statusBlock('📩', $kind.' Application');
        $msg .= self::greeting($name);
        $msg .= "Your application for *{$jobTitle}* has been received and is now *under review*.\n\n";
        $msg .= self::bullet('Reference', $reference);
        $msg .= self::bullet('Type', $kind);
        $msg .= "\nWe will notify you on WhatsApp at every stage. Please keep this number available.";
        $msg .= self::footer();

        return $msg;
    }

    public static function applicationSelected($name, $jobTitle, $reference, $agreementUrl, $isInternship = false)
    {
        $kind = $isInternship ? 'Internship' : 'Employment';
        $msg = self::statusBlock('✅', 'Selected');
        $msg .= self::greeting($name);
        $msg .= "Congratulations! You have been *selected* for the {$kind} role *{$jobTitle}*.\n\n";
        $msg .= self::bullet('Reference', $reference);
        $msg .= self::actionLink('Sign your agreement', $agreementUrl);
        $msg .= "\nAfter signing, you will receive a WhatsApp confirmation.";
        $msg .= self::footer();

        return $msg;
    }

    public static function applicationRejected($name, $jobTitle, $reference, $reason = null)
    {
        $msg = self::statusBlock('❌', 'Application Update');
        $msg .= self::greeting($name);
        $msg .= "Thank you for applying for *{$jobTitle}* at *" . self::companyName() . "*.\n\n";
        $msg .= self::bullet('Reference', $reference);
        $msg .= "\nAfter careful review, we are unable to proceed with your application at this time.\n";
        if ($reason) {
            $msg .= self::bullet('Reason', $reason);
        }
        $msg .= "\nWe wish you the best in your future opportunities.";
        $msg .= self::footer();

        return $msg;
    }

    public static function applicationAgreementSigned($name, $jobTitle, $reference, $isInternship = false)
    {
        $kind = $isInternship ? 'Internship' : 'Employment';
        $msg = self::statusBlock('📝', $kind.' Agreement Signed');
        $msg .= self::greeting($name);
        $msg .= "Your {$kind} agreement for *{$jobTitle}* has been signed and received.\n\n";
        $msg .= self::bullet('Reference', $reference);
        $msg .= self::bullet('Working hours', '7:30 AM – 4:00 PM');
        $msg .= self::bullet('Timesheets', 'Daily · minimum 40 hours per week');
        $msg .= "\nFailure to complete assigned tasks may result in termination.\n\n";
        $msg .= 'Welcome to *' . self::companyName() . '*.';
        $msg .= self::footer();

        return $msg;
    }

    public static function shareholderRegistration($name, $reference, $shares, $investmentLabel, $verifyUrl)
    {
        $msg = self::statusBlock('📈', 'Shareholder Registration');
        $msg .= self::greeting($name);
        $msg .= "Your shareholder registration with *" . self::companyName() . "* has been received.\n\n";
        $msg .= self::bullet('Reference', $reference);
        $msg .= self::bullet('Shares', $shares);
        $msg .= self::bullet('Investment', $investmentLabel);
        $msg .= "\nOur team will contact you with payment instructions.";
        $msg .= self::actionLink('Verify signed agreement', $verifyUrl);
        $msg .= self::footer();

        return $msg;
    }

    public static function trainingRegistration($name, $reference, $courses)
    {
        $msg = self::statusBlock('🎓', 'Training Registration');
        $msg .= self::greeting($name);
        $msg .= "Your training registration with *" . self::companyName() . "* has been received.\n\n";
        $msg .= self::bullet('Reference', $reference);
        $msg .= self::bullet('Courses', $courses);
        $msg .= "\nOur team will contact you shortly with the next steps.";
        $msg .= self::footer();

        return $msg;
    }

    public static function eventContractSignRequest($workerName, $eventName, $signUrl)
    {
        $msg = self::statusBlock('📝', 'Event Contract');
        $msg .= self::greeting($workerName ?: 'Team member');
        $msg .= "Please review and sign your event contract with *" . self::companyName() . "*.\n\n";
        $msg .= self::bullet('Event', $eventName);
        $msg .= self::actionLink('Sign contract', $signUrl);
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
