<div id="add-payment" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left pay-sheet-modal">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add payment</h5>
                <strong class="pay-sheet-who" id="pay-sheet-who"></strong>
                <div class="pay-sheet-ref" id="pay-sheet-ref"></div>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                {!! Form::open(['route' => 'sale.add-payment', 'method' => 'post', 'files' => true, 'class' => 'payment-form', 'id' => 'pay-sheet-form']) !!}
                <div class="pay-sheet-due">
                    <span>Still due</span>
                    <strong id="pay-sheet-due">0 XAF</strong>
                </div>
                <label class="pay-sheet-label" for="pay-sheet-phone">Phone number</label>
                <input type="tel" name="momo_phone" id="pay-sheet-phone" class="form-control" autocomplete="off" placeholder="6xxxxxxxx">
                <p id="pay-sheet-holder" class="pay-sheet-holder"></p>
                <div class="pay-sheet-grid">
                    <button type="button" class="pay-choice is-on" data-paid-by="1"><strong>Cash</strong><small>Submit</small></button>
                    <button type="button" class="pay-choice" data-paid-by="8"><strong>MoMo / OM</strong><small>Wait for the phone</small></button>
                    <button type="button" class="pay-choice is-on" data-fill="full"><strong>Full amount</strong><small>Pay everything due</small></button>
                    <button type="button" class="pay-choice" data-fill="part"><strong>Partial</strong><small>Pay part now</small></button>
                    <button type="button" class="pay-choice is-wide" data-fill="split"><strong>Split</strong><small>Several numbers pay this bill</small></button>
                </div>
                <div class="pay-split" id="pay-split" hidden>
                    <table>
                        <thead><tr><th>Number</th><th>Amount (XAF)</th><th></th></tr></thead>
                        <tbody id="pay-split-body"></tbody>
                    </table>
                    <button type="button" class="pay-plus" id="pay-split-add" title="Add a number">+</button>
                </div>
                <div id="pay-single-amount">
                    <label class="pay-sheet-label" for="amount">Amount (XAF)</label>
                    <input type="text" id="amount" name="amount" class="form-control" step="any" required>
                </div>
                <p class="pay-sheet-left" id="pay-sheet-left">Left after this payment: 0 XAF</p>
                <input type="hidden" name="pay_mode" id="pay-mode" value="">
                <input type="hidden" name="balance">
                <input type="hidden" name="paying_amount">
                <input type="hidden" name="sale_id">
                <div class="pay-legacy">
                    <select name="paid_by_id">
                        <option value="1">Cash</option>
                        <option value="8">MOMO/OM</option>
                        @if(in_array("JE-method", $all_permission))
                            <option value="3">JE</option>
                        @endif
                        <option value="6">Deposit</option>
                    </select>
                    <select name="account_id">
                        @foreach($lims_account_list as $account)
                            <option value="{{ $account->id }}" {{ $account->is_default ? 'selected' : '' }}>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <select name="account_id_debit">
                        @foreach($lims_account_list as $account)
                            <option value="{{ $account->id }}" {{ ! empty($account->is_default_debit) ? 'selected' : '' }}>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    <textarea name="payment_note"></textarea>
                    <p class="change">0.00</p>
                </div>
                {{ Form::close() }}
            </div>
            <div class="modal-footer">
                <button type="submit" form="pay-sheet-form" class="pay-sheet-submit">Submit</button>
                <p class="pay-sheet-hint" id="pay-sheet-hint">This records the cash payment.</p>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var lookupUrl = @json(route('deposit.phone'));
    function money(value) {
        var number = parseFloat(String(value || '').replace(/,/g, ''));
        return isNaN(number) ? 0 : number;
    }
    function splitSum() {
        var sum = 0;
        $('#pay-split-body .pay-split-amount').each(function () { sum += money($(this).val()); });
        return sum;
    }
    function paint() {
        var due = money($('#add-payment input[name="paying_amount"]').val() || $('#add-payment input[name="balance"]').val());
        var paid = $('#pay-mode').val() === 'split' ? splitSum() : money($('#amount').val());
        $('#pay-sheet-due').text(due.toLocaleString() + ' XAF');
        $('#pay-sheet-left').text('Left after this payment: ' + Math.max(0, due - paid).toLocaleString() + ' XAF');
        var momo = String($('#add-payment select[name="paid_by_id"]').val()) === '8';
        $('#pay-sheet-hint').text($('#pay-mode').val() === 'split'
            ? 'Each number pays its own amount.'
            : (momo ? 'This waits for the phone to approve.' : 'This records the cash payment.'));
    }
    function row(phone, amount) {
        var tr = $('<tr>');
        tr.append($('<td>').append($('<input>', {type: 'tel', name: 'split_phone[]', class: 'form-control pay-split-phone', value: phone || '', placeholder: '6xxxxxxxx'})));
        tr.append($('<td>').append($('<input>', {type: 'number', name: 'split_amount[]', class: 'form-control pay-split-amount', min: '1', step: '1', value: amount || ''})));
        tr.append($('<td>').append($('<button>', {type: 'button', class: 'btn btn-link pay-split-remove', text: 'Remove'})));
        $('#pay-split-body').append(tr);
    }
    function split(on) {
        $('#pay-mode').val(on ? 'split' : '');
        $('#pay-split').prop('hidden', !on);
        $('#pay-single-amount').toggle(!on);
        $('#amount').prop('required', !on);
        $('#pay-split-body input').prop('disabled', !on);
        if (on && !$('#pay-split-body tr').length) {
            row($('#pay-sheet-phone').val(), '');
            row('', '');
        }
        $('#add-payment .pay-choice[data-fill]').removeClass('is-on');
        if (on) $('#add-payment .pay-choice[data-fill="split"]').addClass('is-on');
        paint();
    }
    function lookup(phone) {
        var label = $('#pay-sheet-holder');
        var digits = String(phone || '').replace(/\D/g, '');
        if (digits.length < 9) { label.text(''); return; }
        label.text('Looking up the name…');
        fetch(lookupUrl + '?phone=' + encodeURIComponent(phone), {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data || !data.ok) { label.text(data && data.error ? data.error : ''); return; }
                label.text((data.name || 'Name not found on MoMo') + (data.operator ? ' · ' + data.operator : ''));
            })
            .catch(function () { label.text(''); });
    }
    $(document).on('click', '.add-payment', function () {
        var button = $(this);
        $('#pay-sheet-who').text(button.data('name') || '');
        $('#pay-sheet-ref').text(button.data('ref') || '');
        $('#pay-sheet-phone').val(button.data('phone') || '');
        lookup(button.data('phone') || '');
        window.setTimeout(paint, 0);
    });
    $('#add-payment').on('shown.bs.modal', paint);
    $('#pay-sheet-phone').on('input', function () {
        var value = this.value;
        window.clearTimeout(window.paySheetLookup);
        window.paySheetLookup = window.setTimeout(function () { lookup(value); }, 400);
    });
    $('#pay-split-add').on('click', function () { row('', ''); });
    $('#pay-split-body').on('click', '.pay-split-remove', function () { $(this).closest('tr').remove(); paint(); });
    $('#pay-split-body').on('input', '.pay-split-amount', paint);
    $('#amount').on('input', paint);
    $(document).on('click', '#add-payment .pay-choice', function () {
        var by = $(this).attr('data-paid-by');
        if (by) {
            $('#add-payment select[name="paid_by_id"]').val(by).trigger('change');
            $('#add-payment .pay-choice[data-paid-by]').removeClass('is-on');
            $(this).addClass('is-on');
            paint();
        }
        if ($(this).attr('data-fill') === 'full') {
            split(false);
            $('#amount').val($('#add-payment input[name="paying_amount"]').val()).trigger('input');
            $('#add-payment .pay-choice[data-fill]').removeClass('is-on');
            $(this).addClass('is-on');
        }
        if ($(this).attr('data-fill') === 'part') {
            split(false);
            $('#amount').val('').focus();
            $('#add-payment .pay-choice[data-fill]').removeClass('is-on');
            $(this).addClass('is-on');
            paint();
        }
        if ($(this).attr('data-fill') === 'split') split(true);
    });
    $('#pay-sheet-form').on('submit', function (event) {
        if ($('#pay-mode').val() !== 'split') return;
        var rows = 0;
        $('#pay-split-body .pay-split-amount').each(function () { if (money($(this).val()) > 0) rows++; });
        if (rows < 2) {
            event.preventDefault();
            alert('Add at least two numbers and amounts.');
            return;
        }
        var due = money($('#add-payment input[name="balance"]').val());
        if (splitSum() - due > 0.01) {
            event.preventDefault();
            alert('The split is more than the amount still due.');
            return;
        }
        $('#amount').prop('required', false).val(splitSum().toFixed(2));
    });
})();
</script>
