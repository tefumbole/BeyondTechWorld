<div class="p-6 md:p-8 space-y-6 text-sm text-gray-700">
    <div class="border-b border-gray-200 pb-4">
        <h2 class="text-lg font-bold text-brand-blue">Shareholder Agreement</h2>
        <p class="text-gray-500 mt-1">Reference: <span class="font-mono font-semibold">{{ $shareholder->reference_number }}</span></p>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Shareholder</p>
            <p class="font-semibold text-gray-900">{{ $shareholder->full_name }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Email</p>
            <p class="font-semibold text-gray-900">{{ $shareholder->email }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Phone</p>
            <p class="font-semibold text-gray-900">{{ $shareholder->full_phone_number }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Nationality</p>
            <p class="font-semibold text-gray-900">{{ $shareholder->nationality ?: '—' }}</p>
        </div>
        @if ($shareholder->company_name)
            <div class="md:col-span-2">
                <p class="text-xs uppercase tracking-wide text-gray-500">Company</p>
                <p class="font-semibold text-gray-900">{{ $shareholder->company_name }}</p>
            </div>
        @endif
        <div class="md:col-span-2">
            <p class="text-xs uppercase tracking-wide text-gray-500">Address</p>
            <p class="font-semibold text-gray-900">{{ $shareholder->address }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Shares</p>
            <p class="font-semibold text-gray-900">{{ number_format($shareholder->shares_assigned) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Investment</p>
            <p class="font-semibold text-brand-blue text-lg">{{ $investmentLabel }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
            <p class="font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $shareholder->status) }}</p>
        </div>
        <div>
            <p class="text-xs uppercase tracking-wide text-gray-500">Signed At</p>
            <p class="font-semibold text-gray-900">{{ $shareholder->agreement_signed_at->format('F j, Y g:i A') }}</p>
        </div>
    </div>

    <div class="prose prose-sm max-w-none text-gray-600">
        <p>This Shareholder Agreement is entered into between <strong>Beyond Enterprise</strong> ("Company") and the undersigned shareholder ("Shareholder").</p>
        <ol class="list-decimal pl-5 space-y-2">
            <li><strong>Share Ownership</strong> — The Shareholder agrees to purchase shares at the agreed price per share.</li>
            <li><strong>Rights &amp; Obligations</strong> — Voting rights proportional to ownership; dividends when declared by the Board.</li>
            <li><strong>Vesting Period</strong> — Shares are subject to a 24-month vesting period from purchase date.</li>
            <li><strong>Transfer Restrictions</strong> — Shares may not be transferred without prior written consent from the Company.</li>
            <li><strong>Confidentiality</strong> — Shareholders agree to maintain confidentiality regarding proprietary information.</li>
            <li><strong>Governing Law</strong> — This Agreement is governed by the laws of Rwanda.</li>
        </ol>
    </div>

    @if ($shareholder->signature)
        <div class="border-t border-gray-200 pt-6">
            <p class="text-xs uppercase tracking-wide text-gray-500 mb-2">Digital Signature</p>
            <img src="{{ $shareholder->signature }}" alt="Shareholder signature" class="max-h-24 border border-gray-200 rounded bg-white p-2">
        </div>
    @endif

    <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 flex items-center gap-2 text-green-800 text-sm">
        <i data-lucide="badge-check" class="w-5 h-5 flex-shrink-0"></i>
        This agreement was digitally signed and verified on {{ now()->format('F j, Y') }}.
    </div>
</div>
