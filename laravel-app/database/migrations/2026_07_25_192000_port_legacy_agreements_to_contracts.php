<?php

use App\ContractTemplate;
use App\ContractTemplateVersion;
use App\ContractType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Port live legacy agreements into Contracts module templates.
 * Legal wording is preserved exactly; only dynamic fields become placeholders.
 */
class PortLegacyAgreementsToContracts extends Migration
{
    public function up()
    {
        $rnt = $this->ensureType('RNT', 'Rentals', 'rental', 'Lessor / Beyond Enterprise', 'Lessee / Client');
        $sft = $this->ensureType('SFT', 'Software License', 'subscription', 'Licensor / Beyond Enterprise', 'Licensee / Client');
        $shr = $this->ensureType('SHR', 'Shareholder Agreement', 'corporate', 'Company / Beyond Enterprise', 'Shareholder / Investor');
        $hr = $this->ensureType('HR', 'Employment / Internship', 'workforce', 'Beyond Enterprise (Employer)', 'Employee / Intern');

        // Thin unified stub → keep inactive; exact equipment + accommodation templates replace it
        ContractTemplate::where('code', 'RNT-BOOKING')->update(['active' => false]);

        $this->publishTemplate($rnt->id, 'RNT-EQUIPMENT', 'Equipment Rental Long-Term Agreement',
            'Exact wording from the Booking / Rental module equipment agreement.',
            $this->equipmentHtml(), [
                'required' => ['party_a.name', 'party_b.name', 'booking.reference', 'booking.grand_total', 'booking.schedule_html'],
                'optional' => ['booking.paid_amount', 'booking.balance', 'booking.notes', 'booking.notes_html'],
            ]);

        $this->publishTemplate($rnt->id, 'RNT-ACCOMMODATION', 'Student Accommodation Agreement',
            'Exact wording from the Booking / Rental module accommodation agreement.',
            $this->accommodationHtml(), [
                'required' => ['party_a.name', 'party_b.name', 'booking.reference', 'booking.grand_total', 'booking.schedule_html'],
                'optional' => ['booking.paid_amount', 'booking.balance', 'booking.notes', 'booking.notes_html'],
            ]);

        $this->publishTemplate($sft->id, 'SFT-LICENSE', 'Software License Subscription Agreement',
            'Exact wording from the Booking module software license / subscription agreement.',
            $this->softwareHtml(), [
                'required' => ['party_a.name', 'party_b.name', 'booking.reference', 'booking.grand_total', 'booking.schedule_html'],
                'optional' => ['booking.paid_amount', 'booking.balance', 'booking.notes', 'booking.notes_html'],
            ]);

        $this->publishTemplate($shr->id, 'SHR-MAIN', 'Shareholder Agreement',
            'Exact wording from the Shareholders portal agreement (landing + signed summary).',
            $this->shareholderHtml(), [
                'required' => ['party_a.name', 'party_b.name', 'contract.effective_date', 'share.price_label'],
                'optional' => [
                    'shareholder.reference', 'shareholder.shares', 'shareholder.investment',
                    'shareholder.email', 'shareholder.phone', 'shareholder.nationality',
                    'shareholder.company', 'shareholder.address', 'contract.jurisdiction',
                ],
            ]);

        $this->publishTemplate($hr->id, 'EMP-INTERNSHIP', 'Internship Agreement',
            'Exact wording from the Job Board / Application internship agreement.',
            $this->internshipHtml(), [
                'required' => ['party_b.name', 'job.title', 'applicant.reference'],
                'optional' => ['party_a.name'],
            ]);

        $this->publishTemplate($hr->id, 'EMP-EMPLOYMENT', 'Employment Agreement',
            'Exact wording from the Job Board / Application employment agreement.',
            $this->employmentHtml(), [
                'required' => ['party_b.name', 'job.title', 'applicant.reference'],
                'optional' => ['party_a.name', 'job.salary'],
            ]);
    }

    public function down()
    {
        ContractTemplate::whereIn('code', [
            'RNT-EQUIPMENT', 'RNT-ACCOMMODATION', 'SFT-LICENSE', 'SHR-MAIN',
            'EMP-INTERNSHIP', 'EMP-EMPLOYMENT',
        ])->update(['active' => false]);
        ContractTemplate::where('code', 'RNT-BOOKING')->update(['active' => true]);
    }

    protected function ensureType($code, $name, $category, $a, $b)
    {
        $type = ContractType::firstOrCreate(
            ['code' => $code],
            [
                'id' => (string) Str::uuid(),
                'name' => $name,
                'category' => $category,
                'default_party_a_label' => $a,
                'default_party_b_label' => $b,
                'active' => true,
            ]
        );
        $type->name = $name;
        $type->category = $category;
        $type->default_party_a_label = $a;
        $type->default_party_b_label = $b;
        $type->active = true;
        $type->save();

        return $type;
    }

    protected function publishTemplate($typeId, $code, $name, $description, $html, array $schema)
    {
        $workflow = [
            'mode' => 'hybrid',
            'stages' => [
                ['stage' => 1, 'roles' => ['party_b']],
                ['stage' => 2, 'roles' => ['admin', 'party_a']],
            ],
        ];

        $tpl = ContractTemplate::firstOrCreate(
            ['code' => $code],
            [
                'id' => (string) Str::uuid(),
                'type_id' => $typeId,
                'name' => $name,
                'description' => $description,
                'active' => true,
            ]
        );
        $tpl->type_id = $typeId;
        $tpl->name = $name;
        $tpl->description = $description;
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

    protected function equipmentHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;">Equipment Rental Long-Term Agreement</h1>
<p style="text-align:center;">Booking Ref: <strong>{{booking.reference}}</strong> | Client: <strong>{{party_b.name}}</strong></p>

<h2 style="color:#0b3f90;">1. Rental Term &amp; Return Time</h2>
<p>This agreement covers the long-term rental of equipment listed below. All rented equipment must be returned by the agreed return date and time shown for each item. Failure to return on time will incur penalties.</p>

<h2 style="color:#0b3f90;">2. Late Return Penalties</h2>
<p>Late return of any equipment will incur penalties including an <strong>additional full-day rental charge per day</strong> (or part thereof) for each item kept beyond the agreed return time, plus any applicable administrative fees.</p>

<h2 style="color:#0b3f90;">3. Client Responsibility for Damage</h2>
<p>Broken, lost, stolen, or damaged equipment is the <strong>full responsibility of the client</strong>. The client agrees to pay repair or replacement costs at the current market value of the affected equipment.</p>

<h2 style="color:#0b3f90;">4. Equipment List &amp; Pricing</h2>
{{booking.schedule_html}}
<p>Grand Total: <strong>{{booking.grand_total}}</strong></p>

<h2 style="color:#0b3f90;">5. Booking Notes</h2>
<p>{{booking.notes}}</p>

<h2 style="color:#0b3f90;">6. Payment Information</h2>
<p>Grand Total: <strong>{{booking.grand_total}}</strong></p>
<p>Amount Paid: <strong>{{booking.paid_amount}}</strong></p>
<p>Balance Due: <strong>{{booking.balance}}</strong></p>

<h2 style="color:#0b3f90;">7. Acceptance</h2>
<p>By signing below, the client confirms they have read this rental long agreement, accept all terms, and authorize identity verification via ID card upload.</p>

<p><strong>Lessor / Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Lessee / Client:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }

    protected function accommodationHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;">Student Accommodation Agreement</h1>
<p style="text-align:center;">Booking Ref: <strong>{{booking.reference}}</strong> | Tenant: <strong>{{party_b.name}}</strong></p>

<h2 style="color:#0b3f90;">1. Room Assignment &amp; Term</h2>
<p>This agreement covers student accommodation at our facility. The tenant is assigned the room(s) listed below for the rental period shown. The room is a student facility and must be used solely for residential purposes during the agreed term.</p>
{{booking.schedule_html}}

<h2 style="color:#0b3f90;">2. Pre-Occupancy Inspection</h2>
<p>Before you begin use of the room, you must inspect every item in the room and confirm that all fixtures, fittings, furniture, and equipment are in good working order. At checkout, every item will be inspected again. If you claim an item was defective or damaged but did not report it at move-in, you will be held responsible for repair or replacement costs.</p>

<h2 style="color:#0b3f90;">3. Single Occupancy</h2>
<p>This accommodation is for <strong>single occupancy only</strong> and is not intended for more than one person. Dual or multi-occupancy without prior written approval will incur an additional <strong>50% increase in rent</strong>, payable immediately upon discovery or upon approval of additional occupants.</p>

<h2 style="color:#0b3f90;">4. Parking</h2>
<p><strong>No parking space is available</strong> for tenants in this facility. Tenants must not park vehicles on the premises unless expressly authorized in writing by management.</p>

<h2 style="color:#0b3f90;">5. Security Deposit — 25,000 FRS</h2>
<p>A compulsory refundable deposit of <strong>25,000 FRS</strong> must be paid before occupancy. The deposit is refundable when you vacate the property, subject to inspection. If items in your room require repairs at exit, you will repair them and collect the deposit, or the deposit will be used for repairs. Any balance owed after repairs will be your responsibility; any surplus will be reimbursed to you.</p>

<h2 style="color:#0b3f90;">6. Room Condition &amp; Walls</h2>
<p>Nails on walls, dirtying of walls, or unauthorized markings are not allowed. Repainting will be required at exit if walls are damaged or defaced, and the cost may be deducted from your deposit or charged separately.</p>

<h2 style="color:#0b3f90;">7. Cleanliness &amp; Windows</h2>
<p>Throwing dirt or waste over windows or from the building is strictly prohibited. Tenants caught doing so will be required to clean the littered area, or part of the deposit will be used for professional cleaning.</p>

<h2 style="color:#0b3f90;">8. Additional Notes</h2>
<p>{{booking.notes}}</p>

<h2 style="color:#0b3f90;">9. Payment Information</h2>
<p>Grand Total: <strong>{{booking.grand_total}}</strong></p>
<p>Amount Paid: <strong>{{booking.paid_amount}}</strong></p>
<p>Balance Due: <strong>{{booking.balance}}</strong></p>

<h2 style="color:#0b3f90;">10. Acceptance</h2>
<p>By signing below, the tenant confirms they have read this Student Accommodation Agreement, accept all terms including the 25,000 FRS deposit and inspection requirements, and authorize identity verification via ID card upload.</p>

<p><strong>Lessor / Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Lessee / Tenant:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }

    protected function softwareHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;">Software License Subscription Agreement</h1>
<p style="text-align:center;">Booking Ref: <strong>{{booking.reference}}</strong> | Client: <strong>{{party_b.name}}</strong></p>

<h2 style="color:#0b3f90;">1. Subscription Summary</h2>
<p>You have subscribed for the product(s) / service(s) listed below. Your subscription period runs from the start date to the end (expiry) date shown for each item. This covers software licenses and digital services such as IPTV, antivirus, and related subscriptions.</p>
{{booking.schedule_html}}

<h2 style="color:#0b3f90;">2. Access &amp; Credentials</h2>
<p>After you sign this agreement and our team approves it, you will receive access to your <strong>client portal</strong> (login details via WhatsApp). Use the portal to view your subscription, signed contract, and related documents. Keep your login credentials confidential.</p>

<h2 style="color:#0b3f90;">3. Fair Use &amp; License Scope</h2>
<p>The subscription is for your personal or organizational use as registered under this booking. Sharing credentials beyond the agreed seats/qty, reselling access, or using the service for unlawful purposes is prohibited and may result in immediate suspension without refund.</p>

<h2 style="color:#0b3f90;">4. Renewal &amp; Expiry</h2>
<p>Service access continues through the expiry date listed above. Renewal is not automatic unless separately agreed. After expiry, access may be suspended until a new subscription is purchased or renewed.</p>

<h2 style="color:#0b3f90;">5. Support &amp; Service Changes</h2>
<p>Support is provided for the subscribed products during the active period. Third-party platforms (e.g. IPTV providers, antivirus vendors) may change features or availability; we will notify you of material changes where practical.</p>

<h2 style="color:#0b3f90;">6. Additional Notes</h2>
<p>{{booking.notes}}</p>

<h2 style="color:#0b3f90;">7. Payment Information</h2>
<p>Grand Total: <strong>{{booking.grand_total}}</strong></p>
<p>Amount Paid: <strong>{{booking.paid_amount}}</strong></p>
<p>Balance Due: <strong>{{booking.balance}}</strong></p>

<h2 style="color:#0b3f90;">8. Acceptance</h2>
<p>By signing below, the client confirms they have subscribed for the product(s) listed, accept the subscription period (From–To), and agree to the terms of this Software License Subscription Agreement. Identity verification via ID card upload is required.</p>

<p><strong>Licensor / Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Licensee / Client:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }

    protected function shareholderHtml()
    {
        return <<<'HTML'
<h1 style="text-align:center;color:#0b3f90;">Shareholder Agreement</h1>
<p>This document serves as a binding understanding between <strong>{{party_a.name}}</strong> (the "Company") and <strong>{{party_b.name}}</strong> (the "Investor" / "Shareholder").
By signing, you acknowledge that you have read, understood, and accepted these terms.</p>
<p><strong>Effective date:</strong> {{contract.effective_date}}<br>
<strong>Reference:</strong> {{shareholder.reference}}</p>

<h2 style="color:#0b3f90;">1. About the Company</h2>
<p><strong>Beyond Enterprise</strong> is a private limited company registered in Rwanda. We specialize in IT consultancy, networking, security systems, and AV engineering.</p>

<h2 style="color:#0b3f90;">2. Share Price</h2>
<p>The value of one (1) share is currently set at <strong>{{share.price_label}}</strong>. This price is subject to change based on future valuations and board approval.</p>

<h2 style="color:#0b3f90;">3. Share Issuance</h2>
<ul>
<li>Shares will be officially issued and allocated to the Investor after a vesting period of <strong>24 months (2 years)</strong> from the date of investment receipt.</li>
<li>During this 24-month period, your investment is treated as <em>Convertible Equity</em>—securing your future ownership stake.</li>
</ul>

<h2 style="color:#0b3f90;">4. Share Ownership</h2>
<p>Investors who purchase shares become partial owners of the company. Ownership percentage is calculated based on the number of shares held relative to the total authorized shares of the company.</p>

<h2 style="color:#0b3f90;">5. Share Value</h2>
<p>The value of shares can fluctuate. While we aim for growth, the value may go up or down based on market conditions and company performance.</p>

<h2 style="color:#0b3f90;">6. Dividends (Profit Sharing)</h2>
<ul>
<li>Dividends are payments made from company profits to shareholders.</li>
<li>Dividends are <strong>not guaranteed</strong>. They are declared only when the company is profitable and the Board of Directors recommends a distribution.</li>
<li>Reinvestment for growth may sometimes take priority over immediate dividend payouts.</li>
</ul>

<h2 style="color:#0b3f90;">7. Management &amp; Voting</h2>
<p>Day-to-day operations are managed by the Board of Directors and Executive Team. Shareholders execute their power by voting on critical matters such as:</p>
<ul>
<li>Election of Directors</li>
<li>Approval of financial statements</li>
<li>Mergers, acquisitions, or sale of assets</li>
<li>Changes to the company constitution</li>
</ul>

<h2 style="color:#0b3f90;">8. Share Transfer &amp; Exit</h2>
<p>Shares are not freely tradable on a public stock exchange.</p>
<ul>
<li><strong>Right of First Refusal:</strong> If you wish to sell your shares, existing shareholders and the Company have the first right to buy them at fair market value.</li>
<li><strong>Transfer Approval:</strong> Transfers to third parties require Board approval to ensure alignment with company values.</li>
</ul>

<h2 style="color:#c0392b;">Risk Disclosure</h2>
<p>Investing in startups and growing companies involves risk, including potential loss of capital. Past performance does not guarantee future results.</p>

<h2 style="color:#0b3f90;">Signed summary terms</h2>
<p>This Shareholder Agreement is entered into between <strong>Beyond Enterprise</strong> ("Company") and the undersigned shareholder ("Shareholder").</p>
<ol>
<li><strong>Share Ownership</strong> — The Shareholder agrees to purchase shares at the agreed price per share.</li>
<li><strong>Rights &amp; Obligations</strong> — Voting rights proportional to ownership; dividends when declared by the Board.</li>
<li><strong>Vesting Period</strong> — Shares are subject to a 24-month vesting period from purchase date.</li>
<li><strong>Transfer Restrictions</strong> — Shares may not be transferred without prior written consent from the Company.</li>
<li><strong>Confidentiality</strong> — Shareholders agree to maintain confidentiality regarding proprietary information.</li>
<li><strong>Governing Law</strong> — This Agreement is governed by the laws of Rwanda.</li>
</ol>

<p><em>Shares:</em> {{shareholder.shares}} &nbsp; <em>Investment:</em> {{shareholder.investment}}</p>

<p><strong>Company:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Shareholder:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }

    protected function internshipHtml()
    {
        return <<<'HTML'
<h1 style="color:#0b3f90;">Internship Agreement</h1>
<p>{{job.title}} · Ref {{applicant.reference}}</p>
<p>Dear <strong>{{party_b.name}}</strong>,</p>
<p>You have been selected for the internship <strong>{{job.title}}</strong> at Beyond Enterprise.</p>

<h2 style="color:#0b3f90;">Internship terms</h2>
<ul>
<li>This internship is <strong>unpaid</strong>.</li>
<li>Expected working hours: <strong>7:30 AM to 4:00 PM</strong>.</li>
<li>You must complete <strong>daily timesheets</strong> and work at least <strong>40 hours per week</strong>.</li>
<li>Failure to complete assigned tasks may result in <strong>termination or premature termination</strong> of the internship.</li>
</ul>

<p>By signing below you confirm that you have read and agree to these terms.</p>

<p><strong>Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Intern:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }

    protected function employmentHtml()
    {
        return <<<'HTML'
<h1 style="color:#0b3f90;">Employment Agreement</h1>
<p>{{job.title}} · Ref {{applicant.reference}}</p>
<p>Dear <strong>{{party_b.name}}</strong>,</p>
<p>You have been selected for the position <strong>{{job.title}}</strong> at Beyond Enterprise.</p>

<h2 style="color:#0b3f90;">Employment terms</h2>
<ul>
<li>Expected working hours: <strong>7:30 AM to 4:00 PM</strong>.</li>
<li>You must complete <strong>daily timesheets</strong> and work at least <strong>40 hours per week</strong>.</li>
<li>Failure to complete assigned tasks may result in <strong>termination or premature termination</strong> of employment.</li>
<li>Agreed compensation reference: <strong>{{job.salary}}</strong> (final offer subject to HR confirmation).</li>
</ul>

<p>By signing below you confirm that you have read and agree to these terms.</p>

<p><strong>Beyond Enterprise:</strong> {{signature.party_a}} &nbsp;&nbsp; Date: {{signature.party_a_date}}</p>
<p><strong>Employee:</strong> {{signature.party_b}} &nbsp;&nbsp; Date: {{signature.party_b_date}}</p>
HTML;
    }
}
