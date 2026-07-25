<?php

use App\ContractTemplate;
use App\ContractTemplateVersion;
use App\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnifyRentalContractType extends Migration
{
    public function up()
    {
        $rentalType = ContractType::firstOrCreate(
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
        $rentalType->name = 'Rentals';
        $rentalType->category = 'rental';
        $rentalType->default_party_a_label = 'Lessor / Beyond Enterprise';
        $rentalType->default_party_b_label = 'Lessee / Client';
        $rentalType->active = true;
        $rentalType->save();

        // Point existing equipment/house contracts & templates at unified Rentals type
        foreach (['EQP', 'HSE'] as $oldCode) {
            $old = ContractType::where('code', $oldCode)->first();
            if (! $old) {
                continue;
            }
            DB::table('contracts')->where('type_id', $old->id)->update(['type_id' => $rentalType->id]);
            DB::table('contract_templates')->where('type_id', $old->id)->update(['type_id' => $rentalType->id]);
            $old->active = false;
            $old->save();
        }

        // Deactivate old split templates
        ContractTemplate::whereIn('code', ['EQP-RENT', 'HSE-RENT'])->update(['active' => false]);

        $html = $this->rentalAgreementHtml();
        $schema = [
            'required' => [
                'contract.effective_date', 'contract.start_date', 'contract.end_date',
                'party_a.name', 'party_b.name', 'booking.reference', 'booking.grand_total',
            ],
            'optional' => [
                'booking.schedule_html', 'booking.paid_amount', 'booking.balance',
                'booking.notes', 'rental.kind_label', 'contract.value', 'contract.currency',
            ],
        ];
        $workflow = [
            'mode' => 'hybrid',
            'stages' => [
                ['stage' => 1, 'roles' => ['party_b']],
                ['stage' => 2, 'roles' => ['admin', 'party_a']],
            ],
        ];

        $tpl = ContractTemplate::firstOrCreate(
            ['code' => 'RNT-BOOKING'],
            [
                'id' => (string) Str::uuid(),
                'type_id' => $rentalType->id,
                'name' => 'Rental Agreement (Equipment / Accommodation)',
                'description' => 'Unified rental agreement aligned with the Booking / Rental module. Use for equipment or accommodation bookings.',
                'active' => true,
            ]
        );
        $tpl->type_id = $rentalType->id;
        $tpl->name = 'Rental Agreement (Equipment / Accommodation)';
        $tpl->description = 'Unified rental agreement aligned with the Booking / Rental module. Use for equipment or accommodation bookings.';
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
        // Non-destructive: leave RNT in place; re-activate old types if present
        ContractType::whereIn('code', ['EQP', 'HSE'])->update(['active' => true]);
        ContractTemplate::whereIn('code', ['EQP-RENT', 'HSE-RENT'])->update(['active' => true]);
    }

    protected function rentalAgreementHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;">Rental Agreement</h1>
<p style="text-align:center;"><em>{{rental.kind_label}}</em></p>
<p>This Agreement is made on <strong>{{contract.effective_date}}</strong> between
<strong>{{party_a.name}}</strong> (“Lessor / Beyond Enterprise”) and
<strong>{{party_b.name}}</strong> (“Lessee / Client”).</p>

<p><strong>Booking reference:</strong> {{booking.reference}}<br>
<strong>Rental period:</strong> {{contract.start_date}} to {{contract.end_date}}<br>
<strong>Contract value:</strong> {{booking.grand_total}} {{contract.currency}}</p>

<h2 style="color:#0b3f90;">1. Scope</h2>
<p>This agreement covers the rental of equipment and/or accommodation (rooms) linked to the booking above,
as listed in the schedule. Terms of the Beyond Enterprise Rental Module apply to the items rented.</p>

<h2 style="color:#0b3f90;">2. Schedule of items</h2>
<p>The following items / rooms are rented under this booking:</p>
{{booking.schedule_html}}

<h2 style="color:#0b3f90;">3. Return time &amp; late return (equipment)</h2>
<p>Where equipment is rented, all items must be returned by the agreed return date and time shown for each item.
Late return incurs penalties including an additional full-day rental charge per day (or part thereof) for each item
kept beyond the agreed return time, plus any applicable administrative fees.</p>

<h2 style="color:#0b3f90;">4. Damage, loss &amp; responsibility</h2>
<p>Broken, lost, stolen, or damaged equipment or accommodation fittings are the full responsibility of the Client.
The Client agrees to pay repair or replacement costs at current market value.</p>

<h2 style="color:#0b3f90;">5. Accommodation terms (when applicable)</h2>
<p>Where the booking includes accommodation / rooms: use is limited to the assigned room(s) for residential purposes
during the agreed term. Single occupancy applies unless otherwise approved in writing. Unauthorized additional occupants
may incur a 50% rent increase. The Client must keep the premises in good condition and comply with house rules.</p>

<h2 style="color:#0b3f90;">6. Payment</h2>
<p>Grand total: <strong>{{booking.grand_total}} {{contract.currency}}</strong><br>
Amount paid: <strong>{{booking.paid_amount}} {{contract.currency}}</strong><br>
Balance due: <strong>{{booking.balance}} {{contract.currency}}</strong></p>

<h2 style="color:#0b3f90;">7. Notes</h2>
<p>{{booking.notes}}</p>

<h2 style="color:#0b3f90;">8. Acceptance</h2>
<p>By signing, the Client confirms they have read this rental agreement, accept all terms, and authorize identity
verification as required by Beyond Enterprise.</p>

<p><strong>Lessor / Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Lessee / Client:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }
}
