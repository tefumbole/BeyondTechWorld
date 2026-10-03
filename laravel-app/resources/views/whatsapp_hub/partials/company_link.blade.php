@php
    $companyLink = null;
    try {
        $companyLink = app(\App\Services\Cloud\CloudLocalWhatsAppLink::class)->panel();
    } catch (\Throwable $e) {
        $companyLink = null;
    }
@endphp
@if($companyLink)
<div class="wa-card" id="company-whatsapp-link" style="margin-bottom:16px">
    <h2 class="wa-title" style="font-size:1.15rem">Link your WhatsApp</h2>
    <p class="wa-sub" style="margin-bottom:8px">Scan the code with the phone number for this company. WhatsApp will show it under Linked devices. This does not use a Wasender account, and it does not use another company's number.</p>
    <p><strong>Status:</strong> <span id="company-link-label">{{ $companyLink['label'] }}</span>
        @if($companyLink['connected'] && $companyLink['phone'])
            <span id="company-link-phone"> · {{ $companyLink['phone'] }}</span>
        @else
            <span id="company-link-phone"></span>
        @endif
    </p>
    <img id="company-link-qr" alt="WhatsApp link code" style="width:220px;height:220px;{{ $companyLink['awaiting'] ? '' : 'display:none' }}" />
    @if(!$companyLink['connected'])
        <form method="POST" action="{{ route('whatsapp.link.start') }}" style="margin-top:8px">
            @csrf
            <button type="submit" class="btn btn-primary">Show QR code</button>
        </form>
    @else
        <form method="POST" action="{{ route('whatsapp.link.disconnect') }}" style="margin-top:8px">
            @csrf
            <button type="submit" class="btn btn-default">Disconnect this WhatsApp</button>
        </form>
    @endif
</div>
@if($companyLink['awaiting'])
<script>
(function () {
    var label = document.getElementById('company-link-label');
    var phone = document.getElementById('company-link-phone');
    var image = document.getElementById('company-link-qr');
    function poll() {
        fetch(@json(route('whatsapp.link.status')), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (label && data.label) label.textContent = data.label;
                if (phone) phone.textContent = data.phone ? ' · ' + data.phone : '';
                if (image && data.qr) {
                    image.src = data.qr;
                    image.style.display = '';
                }
                if (data.connected) {
                    window.location.reload();
                    return;
                }
                setTimeout(poll, 3000);
            })
            .catch(function () { setTimeout(poll, 5000); });
    }
    poll();
})();
</script>
@endif
@endif
