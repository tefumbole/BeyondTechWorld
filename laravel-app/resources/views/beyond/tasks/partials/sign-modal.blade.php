{{-- Signature modal. Requires an x-data ancestor exposing `signFor`. --}}
<div x-show="signFor === '{{ $assignmentId }}'" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4"
     @keydown.escape.window="signFor = null">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full overflow-hidden" @click.outside="signFor = null">
        <div class="bg-brand-blue text-white px-6 py-4">
            <h3 class="text-lg font-bold">Sign to Accept Task</h3>
            <p class="text-blue-100 text-sm">Draw your signature to confirm you accept this task assignment.</p>
        </div>
        <div class="p-6 space-y-4">
            <div class="border-2 border-dashed border-brand-blue rounded-xl bg-blue-50/50 p-3">
                <canvas id="sigpad_{{ $assignmentId }}" width="420" height="150" class="w-full bg-white rounded-lg touch-none"></canvas>
            </div>
            <div class="flex justify-between">
                <button type="button" class="text-sm text-gray-600 hover:text-brand-blue underline" onclick="beyondSig['{{ $assignmentId }}'] && beyondSig['{{ $assignmentId }}'].clear()">Clear</button>
                <div class="flex gap-2">
                    <button type="button" @click="signFor = null" class="px-4 py-2 rounded-md border border-gray-200 text-gray-700 text-sm">Cancel</button>
                    <form method="POST" action="{{ $action }}" onsubmit="return beyondSigSubmit('{{ $assignmentId }}', this)">
                        @csrf
                        <input type="hidden" name="signature" id="sigval_{{ $assignmentId }}">
                        <button type="submit" class="px-5 py-2 rounded-md bg-brand-gold text-brand-blue font-bold text-sm inline-flex items-center gap-2">
                            <i data-lucide="check" class="w-4 h-4"></i> Accept &amp; Sign
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
window.beyondSig = window.beyondSig || {};
function beyondInitSig(id) {
    var canvas = document.getElementById('sigpad_' + id);
    if (!canvas || typeof SignaturePad === 'undefined' || window.beyondSig[id]) return;
    window.beyondSig[id] = new SignaturePad(canvas, { backgroundColor: 'rgb(255,255,255)', penColor: 'rgb(0,61,130)' });
}
function beyondSigSubmit(id, form) {
    var pad = window.beyondSig[id];
    if (!pad || pad.isEmpty()) {
        alert('Please provide your signature before continuing.');
        return false;
    }
    document.getElementById('sigval_' + id).value = pad.toDataURL('image/png');
    return true;
}
if (!window.__beyondSigBoot) {
    window.__beyondSigBoot = true;
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('canvas[id^="sigpad_"]').forEach(function (c) {
            beyondInitSig(c.id.replace('sigpad_', ''));
        });
    });
}
</script>
@endpush
