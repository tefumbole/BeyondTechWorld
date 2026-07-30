<?php

use App\ContractTemplate;
use App\ContractTemplateVersion;
use App\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Add Studio Rental Agreement template to the Contracts module (RNT-STUDIO).
 */
class AddStudioRentalContractTemplate extends Migration
{
    public function up()
    {
        $rnt = ContractType::firstOrCreate(
            ['code' => 'RNT'],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Rentals',
                'category' => 'rental',
                'default_party_a_label' => 'Lessor / Beyond Enterprise',
                'default_party_b_label' => 'Lessee / Client',
                'active' => true,
            ]
        );

        $html = $this->studioHtml();
        $schema = [
            'required' => ['party_a.name', 'party_b.name', 'booking.reference', 'booking.grand_total', 'booking.schedule_html'],
            'optional' => ['booking.paid_amount', 'booking.balance', 'booking.notes', 'booking.notes_html'],
        ];
        $workflow = [
            'mode' => 'hybrid',
            'stages' => [
                ['stage' => 1, 'roles' => ['party_b']],
                ['stage' => 2, 'roles' => ['admin', 'party_a']],
            ],
        ];

        $tpl = ContractTemplate::firstOrCreate(
            ['code' => 'RNT-STUDIO'],
            [
                'id' => (string) Str::uuid(),
                'type_id' => $rnt->id,
                'name' => 'Studio Rental Agreement',
                'description' => 'Exact wording from the Booking / Rental module studio rental agreement (hourly / daily / monthly).',
                'active' => true,
            ]
        );
        $tpl->type_id = $rnt->id;
        $tpl->name = 'Studio Rental Agreement';
        $tpl->description = 'Exact wording from the Booking / Rental module studio rental agreement (hourly / daily / monthly).';
        $tpl->active = true;
        $tpl->save();

        $version = ContractTemplateVersion::create([
            'id' => (string) Str::uuid(),
            'template_id' => $tpl->id,
            'version_no' => ((int) $tpl->versions()->max('version_no')) + 1,
            'content_html' => $html,
            'placeholder_schema' => $schema,
            'signature_workflow_json' => $workflow,
            'checksum' => hash('sha256', $html),
            'published_at' => now(),
        ]);
        $tpl->current_version_id = $version->id;
        $tpl->save();
    }

    public function down()
    {
        ContractTemplate::where('code', 'RNT-STUDIO')->update(['active' => false]);
    }

    protected function studioHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;">Studio Rental Agreement</h1>
<p style="text-align:center;">Booking Ref: <strong>{{booking.reference}}</strong> | Client: <strong>{{party_b.name}}</strong></p>

<h2 style="color:#0b3f90;">1. Studio Session Summary</h2>
<p>Studio rentals may be booked on an <strong>Hourly</strong>, <strong>Daily</strong>, or <strong>Monthly</strong> basis. The session(s) below show your agreed booking method, duration, and schedule.</p>
{{booking.schedule_html}}
<p>Grand Total: <strong>{{booking.grand_total}}</strong></p>

<h2 style="color:#0b3f90;">2. Settling-In Time &amp; Extensions</h2>
<p>You must allow enough time in your booking to accommodate settling in and wrap-up. The studio is used by many other clients. When your booked time ends, any request to add another hour (or further time) is <strong>subject to approval</strong> and availability — extension is not guaranteed.</p>

<h2 style="color:#0b3f90;">3. Overtime Charges</h2>
<p>Overtime beyond the booked end time is billed at <strong>12,000 XAF for 0–60 minutes</strong> (or any part thereof), unless a longer extension is separately approved and priced. Overtime starts when your booked session ends.</p>

<h2 style="color:#0b3f90;">4. Non-Refundable &amp; Generator Fuel</h2>
<p>Studio rentals are <strong>not refundable</strong>. It is your responsibility to arrange generator fuel for power backup during your session.</p>
<p>Generator fuel is charged at <strong>3,200 XAF per hour</strong>. If you do not request this service and there is a power outage, the studio session will be lost and no refund or free remake is due.</p>

<h2 style="color:#0b3f90;">5. Care of Studio &amp; Equipment</h2>
<p>You agree to use the studio and any included equipment carefully and lawfully. Damage, loss, or misuse may be charged at repair or replacement cost. Leave the space tidy at the end of your session.</p>

<h2 style="color:#0b3f90;">6. Additional Notes</h2>
<p>{{booking.notes}}</p>

<h2 style="color:#0b3f90;">7. Payment Information</h2>
<p>Grand Total: <strong>{{booking.grand_total}}</strong></p>
<p>Amount Paid: <strong>{{booking.paid_amount}}</strong></p>
<p>Balance Due: <strong>{{booking.balance}}</strong></p>

<h2 style="color:#0b3f90;">8. Acceptance</h2>
<p>By signing below, the client confirms they have read this Studio Rental Agreement, accept the session schedule and rates above (including overtime and generator fuel terms), and authorize identity verification via ID card upload.</p>

<p><strong>Lessor / Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Lessee / Client:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }
}
