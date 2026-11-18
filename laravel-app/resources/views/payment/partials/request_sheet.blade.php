<div id="request-payment" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left pay-sheet-modal">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request payment</h5>
                <strong class="pay-sheet-who" id="request-payment-who"></strong>
                <div class="pay-sheet-ref" id="request-payment-ref"></div>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <form method="POST" id="request-payment-form" action="">
                @csrf
                <div class="modal-body">
                    <div class="pay-sheet-due">
                        <span>Still due</span>
                        <strong id="request-payment-due" data-due="0">0 XAF</strong>
                    </div>
                    <div class="pay-sheet-grid">
                        <button type="button" class="pay-choice is-on" data-request-fill="full"><strong>Full amount</strong><small>Request everything due</small></button>
                        <button type="button" class="pay-choice" data-request-fill="part"><strong>Partial</strong><small>Ask for part now</small></button>
                    </div>
                    <label class="pay-sheet-label" for="request-payment-amount">Amount to request (XAF)</label>
                    <input type="text" inputmode="numeric" name="amount" id="request-payment-amount" class="form-control" required autocomplete="off">
                    <p class="pay-sheet-left" id="request-payment-left">Left after this request: 0 XAF</p>
                    <p class="pay-sheet-hint">The client gets a WhatsApp link. They open it and pay with MTN, Orange, or VISA.</p>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="pay-sheet-submit">Send request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    function money(value) {
        var number = parseFloat(String(value || '').replace(/[^\d.]/g, ''));
        return isNaN(number) ? 0 : number;
    }
    function paintRequest() {
        var due = money($('#request-payment-due').attr('data-due'));
        var asked = money($('#request-payment-amount').val());
        if (asked > due) {
            asked = due;
        }
        $('#request-payment-left').text('Left after this request: ' + Math.max(0, due - asked).toLocaleString() + ' XAF');
    }
    $(document).on('click', '#request-payment .pay-choice', function () {
        var due = money($('#request-payment-due').attr('data-due'));
        $('#request-payment .pay-choice').removeClass('is-on');
        $(this).addClass('is-on');
        if ($(this).data('request-fill') === 'full') {
            $('#request-payment-amount').val(due ? due.toLocaleString() : '').prop('readonly', true);
        } else {
            $('#request-payment-amount').prop('readonly', false).trigger('focus');
        }
        paintRequest();
    });
    $(document).on('input', '#request-payment-amount', paintRequest);
    $('#request-payment-form').on('submit', function () {
        $('#request-payment-amount').val(String($('#request-payment-amount').val() || '').replace(/[^\d]/g, ''));
    });
    window.fillRequestPayment = function (due) {
        due = parseInt(due, 10) || 0;
        $('#request-payment-due').attr('data-due', due).text(due.toLocaleString() + ' XAF');
        $('#request-payment .pay-choice').removeClass('is-on');
        $('#request-payment .pay-choice[data-request-fill="full"]').addClass('is-on');
        $('#request-payment-amount').prop('readonly', true).val(due ? due.toLocaleString() : '');
        paintRequest();
    };
})();
</script>
