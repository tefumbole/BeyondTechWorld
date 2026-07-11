@php
    $pricePerShare = $settings['price_per_share'];
    $availableShares = $settings['available_shares'];
@endphp

<div class="bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden" x-data="shareholderForm({{ $pricePerShare }}, {{ $availableShares }})">
    <div class="bg-brand-blue text-white px-6 py-5">
        <h2 class="text-xl font-bold flex items-center gap-2">
            <i data-lucide="file-signature" class="w-6 h-6"></i> Shareholder Registration
        </h2>
        <p class="text-blue-100 text-sm mt-1">Complete the form below to register your share purchase request.</p>
    </div>

    @if ($errors->any())
        <div class="mx-6 mt-6 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
            <ul class="list-disc pl-5 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('shareholders.store') }}" id="shareholder-registration-form" class="p-6 md:p-8 space-y-6">
        @csrf

        <div class="grid md:grid-cols-2 gap-5">
            <div>
                <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                <input type="text" name="full_name" value="{{ old('full_name') }}" required
                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Email Address *</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Country Code *</label>
                <select name="country_code" required class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                    @foreach ($countryCodes as $code => $label)
                        <option value="{{ $code }}" @if(old('country_code', '+250') === $code) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Phone Number *</label>
                <input type="tel" name="phone_number" value="{{ old('phone_number') }}" required pattern="[0-9]{6,15}"
                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none"
                       placeholder="Digits only">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Company Name (optional)</label>
                <input type="text" name="company_name" value="{{ old('company_name') }}"
                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Nationality (optional)</label>
                <input type="text" name="nationality" value="{{ old('nationality') }}"
                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
            </div>
            <div class="md:col-span-2">
                <label class="text-sm font-semibold text-gray-700">Address *</label>
                <textarea name="address" rows="2" required
                          class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">{{ old('address') }}</textarea>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-5 p-5 bg-gray-50 rounded-xl border border-gray-100">
            <div>
                <label class="text-sm font-semibold text-gray-700">Number of Shares *</label>
                <input type="number" name="shares_count" x-model.number="shares" min="1" :max="available" required
                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                <p class="text-xs text-gray-500 mt-1">Max available: {{ number_format($availableShares) }}</p>
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Total Investment</label>
                <div class="mt-1 text-2xl font-bold text-brand-blue" x-text="formatTotal()">{{ $priceLabel }}</div>
            </div>
        </div>

        <div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="terms_accepted" value="1" required class="mt-1" @if(old('terms_accepted')) checked @endif>
                <span class="text-sm text-gray-600">
                    I confirm that I have read and accept the
                    <a href="{{ route('shareholders.landing') }}" class="text-brand-light underline" target="_blank">Shareholder Agreement</a>
                    and authorize Beyond Enterprise to process my registration.
                </span>
            </label>
        </div>

        <div>
            <label class="text-sm font-semibold text-gray-700 block mb-2">Digital Signature *</label>
            <p class="text-xs text-gray-500 mb-2">Sign below with your mouse or finger. Your signature will be stored with your registration.</p>
            <div class="border-2 border-dashed border-brand-blue rounded-xl bg-blue-50/50 p-3 max-w-lg">
                <canvas id="shareholder-signature-pad" width="500" height="140" class="w-full max-w-[500px] bg-white rounded-lg touch-none"></canvas>
            </div>
            <input type="hidden" name="signature" id="shareholder_signature_input" value="{{ old('signature') }}">
            <button type="button" id="clear-shareholder-signature" class="mt-2 text-sm text-gray-600 hover:text-brand-blue underline">Clear signature</button>
        </div>

        <button type="submit" class="w-full bg-brand-gold hover:bg-[#b5952f] text-brand-blue font-bold py-3.5 rounded-lg flex items-center justify-center gap-2 shadow-lg">
            <i data-lucide="send" class="w-5 h-5"></i> Submit Registration
        </button>
    </form>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
function shareholderForm(pricePerShare, available) {
    return {
        shares: {{ old('shares_count', 1) }},
        available: available,
        pricePerShare: pricePerShare,
        formatTotal() {
            const total = (this.shares || 0) * this.pricePerShare;
            return '$' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}

(function () {
    var canvas = document.getElementById('shareholder-signature-pad');
    if (!canvas || typeof SignaturePad === 'undefined') return;

    var pad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(255, 255, 255)',
        penColor: 'rgb(0, 61, 130)'
    });

    var clearBtn = document.getElementById('clear-shareholder-signature');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () { pad.clear(); });
    }

    var form = document.getElementById('shareholder-registration-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (pad.isEmpty()) {
                e.preventDefault();
                alert('Please provide your signature before submitting.');
                return false;
            }
            document.getElementById('shareholder_signature_input').value = pad.toDataURL('image/png');
        });
    }
})();
</script>
@endpush
