<?php

use App\ContractRateCategory;
use App\ContractSetting;
use App\ContractTemplate;
use App\ContractTemplateVersion;
use App\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SeedContractsModule extends Migration
{
    public function up()
    {
        $perms = [
            'contracts_module', 'contracts.view', 'contracts.create', 'contracts.edit',
            'contracts.send', 'contracts.sign_admin', 'contracts.cancel',
            'contracts.templates', 'contracts.settings', 'contracts.audit',
        ];
        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach (Role::whereIn('id', [1, 2])->get() as $role) {
            foreach ($perms as $name) {
                try {
                    $role->givePermissionTo($name);
                } catch (\Exception $e) {
                }
            }
        }

        $defaults = [
            'number_prefix' => 'CNT',
            'default_validity_days' => 14,
            'reminder_first_hours' => 48,
            'reminder_every_hours' => 72,
            'reminder_max' => 5,
            'default_admin_signer_user_id' => null,
            'company_legal_name' => 'Beyond Enterprise',
            'company_address' => '',
            'default_jurisdiction' => 'Republic of Cameroon',
            'default_signature_workflow' => 'hybrid',
        ];
        foreach ($defaults as $k => $v) {
            if (! ContractSetting::where('key', $k)->exists()) {
                ContractSetting::setValue($k, $v);
            }
        }

        $rates = [
            ['lead_engineer', 'Lead Engineer', 6000],
            ['engineer', 'Engineer', 5000],
            ['associate_engineer', 'Associate Engineer', 4500],
            ['senior_technician', 'Senior Technician', 4000],
            ['technician', 'Technician', 3500],
            ['manoeuvre', 'Manoeuvre / General Labourer', 2500],
        ];
        foreach ($rates as [$code, $name, $rate]) {
            ContractRateCategory::firstOrCreate(
                ['code' => $code],
                ['id' => (string) Str::uuid(), 'name' => $name, 'daily_rate' => $rate, 'active' => true]
            );
        }

        $types = [
            // Rentals / subscriptions — exact legacy agreement wording published by later migration
            ['RNT', 'Rentals', 'rental', 'Lessor / Beyond Enterprise', 'Lessee / Client'],
            ['SFT', 'Software License', 'subscription', 'Licensor / Beyond Enterprise', 'Licensee / Client'],
            ['EVT', 'Concert/Event Production', 'event', 'Service Provider', 'Client / Event Organizer'],
            ['WEB', 'Website Development', 'service', 'Developer / Service Provider', 'Client'],
            ['SAL', 'Sales Agreement', 'sales', 'Seller', 'Buyer'],
            ['JOB', 'Job/Service Delivery', 'service', 'Service Provider', 'Client'],
            ['HR', 'Employment / Internship', 'workforce', 'Beyond Enterprise (Employer)', 'Employee / Intern'],
            ['ENG', 'Engineer Engagement', 'workforce', 'Engaging Company', 'Contract Engineer/Technician'],
            ['SHR', 'Shareholder Agreement', 'corporate', 'Company / Beyond Enterprise', 'Shareholder / Investor'],
            ['GEN', 'Generic Two-Party Agreement', 'generic', 'Party A', 'Party B'],
        ];

        $typeIds = [];
        foreach ($types as [$code, $name, $cat, $a, $b]) {
            $type = ContractType::firstOrCreate(
                ['code' => $code],
                [
                    'id' => (string) Str::uuid(),
                    'name' => $name,
                    'category' => $cat,
                    'default_party_a_label' => $a,
                    'default_party_b_label' => $b,
                    'active' => true,
                ]
            );
            $typeIds[$code] = $type->id;
        }

        $this->seedEngineerTemplate($typeIds['ENG']);
        $this->seedStubTemplate($typeIds['EVT'], 'EVT-PROD', 'Concert/Event Production Agreement', $this->eventStubHtml());
        $this->seedStubTemplate($typeIds['WEB'], 'WEB-DEV', 'Website Development Agreement', $this->webStubHtml());
        $this->seedStubTemplate($typeIds['RNT'], 'RNT-BOOKING', 'Rental Agreement (Equipment / Accommodation)', $this->rentalUnifiedStubHtml());
        $this->seedStubTemplate($typeIds['JOB'], 'JOB-SVC', 'Service/Job Delivery Agreement', $this->jobStubHtml());
        $this->seedStubTemplate($typeIds['GEN'], 'GEN-2P', 'Generic Two-Party Agreement', $this->genericStubHtml());
    }

    protected function seedEngineerTemplate($typeId)
    {
        $html = $this->engineerEngagementHtml();
        $schema = [
            'required' => [
                'contract.effective_date', 'party_a.name', 'party_a.address', 'party_b.name', 'party_b.address',
                'worker.role', 'event.name', 'event.venue', 'event.start_date', 'event.end_date',
                'worker.daily_rate', 'work.estimated_days',
            ],
            'optional' => [
                'work.schedule', 'work.day_rate_rule', 'work.reporting_time', 'work.supervisor_name',
                'payment.due_rule', 'payment.method', 'cancellation.rule', 'contract.jurisdiction',
                'party_a.signer_name', 'party_a.signer_title', 'worker.food_allowance',
            ],
        ];
        $workflow = [
            'mode' => 'sequential',
            'stages' => [
                ['stage' => 1, 'roles' => ['party_b']],
                ['stage' => 2, 'roles' => ['witness_b']],
                ['stage' => 3, 'roles' => ['admin', 'party_a']],
                ['stage' => 4, 'roles' => ['witness_a']],
            ],
        ];

        $tpl = ContractTemplate::firstOrCreate(
            ['code' => 'ENG-EVENT'],
            [
                'id' => (string) Str::uuid(),
                'type_id' => $typeId,
                'name' => 'Event-Based Engineer / Technician Engagement',
                'description' => 'Temporary event engagement with daily rates, food allowance and safety clauses.',
                'active' => true,
            ]
        );

        if (! $tpl->current_version_id) {
            $version = ContractTemplateVersion::create([
                'id' => (string) Str::uuid(),
                'template_id' => $tpl->id,
                'version_no' => 1,
                'content_html' => $html,
                'placeholder_schema' => $schema,
                'signature_workflow_json' => $workflow,
                'checksum' => hash('sha256', $html),
                'published_at' => now(),
            ]);
            $tpl->current_version_id = $version->id;
            $tpl->save();
        }
    }

    protected function seedStubTemplate($typeId, $code, $name, $html)
    {
        $tpl = ContractTemplate::firstOrCreate(
            ['code' => $code],
            [
                'id' => (string) Str::uuid(),
                'type_id' => $typeId,
                'name' => $name,
                'description' => 'Starter template — customize clauses before production use.',
                'active' => true,
            ]
        );
        if (! $tpl->current_version_id) {
            $version = ContractTemplateVersion::create([
                'id' => (string) Str::uuid(),
                'template_id' => $tpl->id,
                'version_no' => 1,
                'content_html' => $html,
                'placeholder_schema' => [
                    'required' => ['contract.effective_date', 'party_a.name', 'party_b.name'],
                    'optional' => ['contract.number', 'quotation.number', 'quotation.total'],
                ],
                'signature_workflow_json' => [
                    'mode' => 'hybrid',
                    'stages' => [
                        ['stage' => 1, 'roles' => ['party_a', 'party_b']],
                        ['stage' => 2, 'roles' => ['witness_a', 'witness_b']],
                        ['stage' => 3, 'roles' => ['admin']],
                    ],
                ],
                'checksum' => hash('sha256', $html),
                'published_at' => now(),
            ]);
            $tpl->current_version_id = $version->id;
            $tpl->save();
        }
    }

    protected function engineerEngagementHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;">EVENT-BASED ENGINEER / TECHNICIAN ENGAGEMENT AGREEMENT</h1>
<p>This Event-Based Engagement Agreement (“Agreement”) is made on <strong>{{contract.effective_date}}</strong> between
<strong>{{party_a.name}}</strong>, of {{party_a.address}}, hereafter called the “Engaging Company,” and
<strong>{{party_b.name}}</strong>, of {{party_b.address}}, hereafter called the “Contract Engineer/Technician.”</p>
<p><strong>1. Selection and Event.</strong> The Contract Engineer/Technician has been selected to serve as {{worker.role}} for
{{event.name}} at {{event.venue}}. The engagement is expected to run from {{event.start_date}} to
{{event.end_date}}, including the setup, event, dismantling and other work periods stated in {{work.schedule}}.</p>
<p><strong>2. Nature of Engagement.</strong> This is a temporary, event-based engagement for the event identified above. It does not
by itself create permanent employment, a guarantee of future engagements or entitlement beyond the terms
expressly stated in this Agreement, subject to applicable law.</p>
<p><strong>3. Duties.</strong> The Contract Engineer/Technician shall report at the stated time; follow the Lead Engineer, Technical
Lead or assigned supervisor; perform duties safely and professionally; protect equipment; cooperate with the team;
and remain available for the agreed work period.</p>
<p><strong>4. Daily Rate.</strong> The agreed rate is {{worker.daily_rate}} FCFA per completed workday. The estimated number of
payable days is {{work.estimated_days}}. Final payable days/hours are based on verified attendance and authorized
timesheets, subject to the contract’s stated minimum/partial-day rules: {{work.day_rate_rule}}.</p>
<p><strong>5. Food.</strong> Food may be provided during the assignment. Where food is not provided for an eligible workday, the
Contract Engineer/Technician shall receive a food allowance of {{worker.food_allowance}} FCFA for that day. No food allowance is due
where food is provided.</p>
<p><strong>6. Transportation.</strong> Transportation from the Contract Engineer/Technician’s residence or chosen departure point to
the job site and back is the sole responsibility and cost of the Contract Engineer/Technician, unless a written
exception is recorded in this Agreement.</p>
<p><strong>7. Personal Safety Equipment and Tools.</strong> The Contract Engineer/Technician must report with suitable personal
safety equipment and basic tools, including safety boots, protective gloves, at least 1.5 litres of drinkable water,
star/Phillips and flat screwdrivers, pliers, a measuring tape, and any additional role-specific tools communicated
before the assignment.</p>
<p><strong>8. Safety and Conduct.</strong> All safety instructions, venue rules and lawful directions must be followed. Alcohol, illegal
drugs, violence, harassment, theft, deliberate equipment damage, reckless conduct and unauthorized absence are
prohibited. Hazards, injuries, near misses and damaged equipment must be reported immediately.</p>
<p><strong>9. Attendance and Communication.</strong> The Contract Engineer/Technician shall arrive by {{work.reporting_time}} and
notify {{work.supervisor_name}} promptly if an emergency may cause delay or absence.</p>
<p><strong>10. Equipment and Confidentiality.</strong> Company or client equipment, access details, technical plans, pricing, customer
information, recordings and operational information shall be used only for this engagement and kept confidential.</p>
<p><strong>11. Payment.</strong> Payment is due {{payment.due_rule}} through {{payment.method}} after approved
attendance/timesheets and completion of assigned duties.</p>
<p><strong>12. Cancellation or Early Termination.</strong> {{cancellation.rule}}</p>
<p><strong>13. Liability and Damage.</strong> Each party remains responsible for loss or damage caused by its proven negligence, wilful
misconduct or unauthorized acts, subject to applicable law.</p>
<p><strong>14. Disputes and Governing Terms.</strong> The parties will first attempt good-faith resolution through Beyond Enterprise
management. If unresolved, the dispute shall follow the law and competent forum stated in {{contract.jurisdiction}}.</p>
<p><strong>15. Entire Agreement and Changes.</strong> This Agreement, its incorporated event schedule and listed annexes constitute
the agreement for this engagement. Electronic signatures and copies are accepted to the extent permitted by applicable law.</p>
<p><strong>16. Acceptance.</strong> By signing, the parties confirm that they have read, understood and accepted this Agreement.</p>
<h2>Signatures</h2>
<table style="width:100%;border-collapse:collapse;" border="1" cellpadding="8">
<tr><th>Engaging Company</th><th>Contract Engineer/Technician</th></tr>
<tr>
<td>Name: {{party_a.signer_name}}<br>Title: {{party_a.signer_title}}<br>Signature: {{signature.party_a}}<br>Date: {{signature.party_a_date}}</td>
<td>Name: {{party_b.name}}<br>Role: {{worker.role}}<br>Signature: {{signature.party_b}}<br>Date: {{signature.party_b_date}}</td>
</tr>
<tr><th>Witness for Engaging Company</th><th>Witness for Contract Engineer/Technician</th></tr>
<tr>
<td>Name: {{witness_a.name}}<br>Signature: {{signature.witness_a}}<br>Date: {{signature.witness_a_date}}</td>
<td>Name: {{witness_b.name}}<br>Signature: {{signature.witness_b}}<br>Date: {{signature.witness_b_date}}</td>
</tr>
</table>
HTML;
    }

    protected function eventStubHtml()
    {
        return '<h1>Concert/Event Production Agreement</h1><p>Made on {{contract.effective_date}} between {{party_a.name}} (Service Provider) and {{party_b.name}} (Client).</p><h2>1. Scope</h2><p>Sound, lights, screens, stage/rigging, power/fuel, transport/logistics and technical personnel as scheduled.</p><h2>2. Dates</h2><p>Setup/show/dismantling per linked event {{event.name}} ({{event.start_date}} – {{event.end_date}}).</p><h2>3. Equipment &amp; Personnel</h2><p>{{payment.schedule}}</p><h2>4. Payment</h2><p>Deposit and milestones as agreed. Quotation: {{quotation.number}} / {{quotation.total}}.</p><h2>5. Cancellation / Force Majeure</h2><p>As configured in annexes.</p><p>Signature Party A: {{signature.party_a}} &nbsp; Party B: {{signature.party_b}}</p>';
    }

    protected function webStubHtml()
    {
        return '<h1>Website Development Agreement</h1><p>{{contract.effective_date}} — {{party_a.name}} (Developer) and {{party_b.name}} (Client).</p><h2>1. Scope &amp; Exclusions</h2><p>As per quotation {{quotation.number}}.</p><h2>2. Milestones &amp; Acceptance</h2><p>{{payment.schedule}}</p><h2>3. IP &amp; Payment</h2><p>IP transfers after full payment of {{quotation.total}}. Late payment may suspend work.</p><p>Signatures: {{signature.party_a}} / {{signature.party_b}}</p>';
    }

    protected function rentalUnifiedStubHtml()
    {
        return '<h1 style="text-align:center;color:#0b3f90;">Rental Agreement</h1>'
            .'<p style="text-align:center;"><em>{{rental.kind_label}}</em></p>'
            .'<p>This Agreement is made on <strong>{{contract.effective_date}}</strong> between '
            .'<strong>{{party_a.name}}</strong> (“Lessor / Beyond Enterprise”) and '
            .'<strong>{{party_b.name}}</strong> (“Lessee / Client”).</p>'
            .'<p><strong>Booking reference:</strong> {{booking.reference}}<br>'
            .'<strong>Rental period:</strong> {{contract.start_date}} to {{contract.end_date}}<br>'
            .'<strong>Contract value:</strong> {{booking.grand_total}} {{contract.currency}}</p>'
            .'<h2 style="color:#0b3f90;">1. Scope</h2>'
            .'<p>Covers equipment and/or accommodation linked to the booking, per Beyond Enterprise Rental Module terms.</p>'
            .'<h2 style="color:#0b3f90;">2. Schedule of items</h2>'
            .'{{booking.schedule_html}}'
            .'<h2 style="color:#0b3f90;">3. Return, damage &amp; payment</h2>'
            .'<p>Late return, damage/loss, and accommodation house rules apply as in the Rental module. '
            .'Grand total {{booking.grand_total}}; paid {{booking.paid_amount}}; balance {{booking.balance}} {{contract.currency}}.</p>'
            .'<p>{{booking.notes}}</p>'
            .'<p><strong>Lessor:</strong> {{signature.party_a}} &nbsp; <strong>Lessee:</strong> {{signature.party_b}}</p>';
    }

    protected function jobStubHtml()
    {
        return '<h1>Service / Job Delivery Agreement</h1><p>{{contract.effective_date}} — {{party_a.name}} / {{party_b.name}}.</p><h2>1. Scope &amp; Deliverables</h2><p>As agreed.</p><h2>2. Price &amp; Payment</h2><p>{{quotation.total}}</p><p>Signatures: {{signature.party_a}} / {{signature.party_b}}</p>';
    }

    protected function genericStubHtml()
    {
        return '<h1>Two-Party Agreement</h1><p>Made on {{contract.effective_date}} between {{party_a.role_label}} {{party_a.name}} and {{party_b.role_label}} {{party_b.name}}.</p><p>{{contract.purpose}}</p><p>Signatures: {{signature.party_a}} / {{signature.party_b}}</p>';
    }

    public function down()
    {
        // keep seed data
    }
}
