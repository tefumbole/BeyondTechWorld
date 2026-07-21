{{-- Quick-create customer & product for quotation create/edit --}}
<style>
.quotation-qc .input-with-action { display: flex; gap: 8px; align-items: stretch; }
.quotation-qc .input-with-action .bootstrap-select { flex: 1; min-width: 0; }
.quotation-qc .input-with-action .btn-default { flex: 0 0 auto; }
</style>

<div id="addCustomer" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{trans('file.Add Customer')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                <div class="form-group">
                    <label>{{trans('file.Customer Group')}} *</label>
                    <select required class="form-control" name="qc_customer_group_id" id="qc_customer_group_id">
                        @foreach($lims_customer_group_all as $customer_group)
                            <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{trans('file.name')}} *</label>
                    <input type="text" id="qc_customer_name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>{{trans('file.Email')}}</label>
                    <input type="email" id="qc_customer_email" class="form-control">
                </div>
                <div class="form-group">
                    <label>{{trans('file.Phone Number')}} *</label>
                    <input type="text" id="qc_customer_phone" required class="form-control">
                </div>
                <div class="form-group">
                    <label>{{trans('file.Address')}}</label>
                    <input type="text" id="qc_customer_address" class="form-control" value="NAN">
                </div>
                <div class="form-group">
                    <label>{{trans('file.City')}}</label>
                    <input type="text" id="qc_customer_city" class="form-control" value="NAN">
                </div>
                <button type="button" id="qc-customer-submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
        </div>
    </div>
</div>

<div id="addProduct" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{trans('file.add_product')}}</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                <div class="form-group">
                    <label>{{trans('file.name')}} *</label>
                    <input type="text" id="qc_product_name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Quote / unit price *</label>
                    <input type="number" id="qc_product_price" required class="form-control" step="any" min="0">
                </div>
                <div class="form-group">
                    <label>{{trans('file.category')}} *</label>
                    <select required id="qc_product_category" class="form-control">
                        @foreach($lims_category_list as $list)
                            <option value="{{$list->id}}" @if(($default_category_id ?? null) == $list->id) selected @endif>{{$list->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{trans('file.unit')}} *</label>
                    <select required id="qc_product_unit" class="form-control">
                        @foreach($lims_unit_list as $list)
                            <option value="{{$list->id}}" @if(($default_unit_id ?? null) == $list->id) selected @endif>{{$list->unit_name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{trans('file.profit percentage')}}</label>
                    <input type="number" id="qc_product_profit" class="form-control" value="{{ $default_profit ?? 25 }}" step="any">
                </div>
                <button type="button" id="qc-product-submit" class="btn btn-primary">{{trans('file.submit')}}</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var productSearchUrl = @json(route('product_quotation.search'));
    var getProductUrl = @json(url('quotations/getproduct'));
    var quickProductUrl = @json(route('quotation.quick_product'));
    var customerStoreUrl = @json(route('customer.store'));

    function refreshWarehouseProducts(done) {
        var wh = $('#warehouse_id').val();
        if (!wh) {
            if (typeof done === 'function') done();
            return;
        }
        $.get(getProductUrl + '/' + wh, function (data) {
            if (typeof lims_product_array !== 'undefined') {
                lims_product_array = [];
                product_code = data[0];
                product_name = data[1];
                product_qty = data[2];
                product_type = data[3];
                product_id = data[4];
                product_list = data[5];
                qty_list = data[6];
                product_warehouse_price = data[7];
                $.each(product_code, function (index) {
                    lims_product_array.push(product_code[index] + ' (' + product_name[index] + ')');
                });
            }
            if (typeof done === 'function') done();
        }).fail(function () {
            if (typeof done === 'function') done();
        });
    }

    $('#qc-customer-submit').on('click', function () {
        var name = $('#qc_customer_name').val().trim();
        var phone = $('#qc_customer_phone').val().trim();
        if (!name || !phone) {
            alert('Name and phone are required.');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: customerStoreUrl,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: csrf,
                customer_group_id: $('#qc_customer_group_id').val(),
                customer_name: name,
                name: name,
                email: $('#qc_customer_email').val(),
                phone_number: phone,
                address: $('#qc_customer_address').val() || 'NAN',
                city: $('#qc_customer_city').val() || 'NAN',
                quotation: 1
            },
            success: function (res) {
                var c = res.customer;
                if (!c) {
                    alert(res.message || 'Customer created. Refresh if not listed.');
                    location.reload();
                    return;
                }
                var $sel = $('#customer_id');
                if ($sel.find('option[value="' + c.id + '"]').length === 0) {
                    $sel.append($('<option>', { value: c.id, text: c.label || (c.name + ' (' + c.phone_number + ')') }));
                }
                $sel.val(c.id);
                if ($sel.hasClass('selectpicker')) {
                    $sel.selectpicker('refresh');
                }
                $sel.trigger('change');
                $('#addCustomer').modal('hide');
                $('#qc_customer_name,#qc_customer_phone,#qc_customer_email').val('');
            },
            error: function (xhr) {
                var msg = 'Could not create customer.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                alert(msg);
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    $('#qc-product-submit').on('click', function () {
        var name = $('#qc_product_name').val().trim();
        var price = $('#qc_product_price').val();
        if (!name || price === '' || price === null) {
            alert('Product name and quote price are required.');
            return;
        }
        if (!$('#customer_id').val()) {
            alert('Please select Customer first!');
            return;
        }
        if (!$('#warehouse_id').val()) {
            alert('Please select Warehouse first!');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            type: 'POST',
            url: quickProductUrl,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            data: {
                _token: csrf,
                product_name: name,
                product_price: price,
                category: $('#qc_product_category').val(),
                unit: $('#qc_product_unit').val(),
                profit: $('#qc_product_profit').val() || 25,
                warehouse_id: $('#warehouse_id').val()
            },
            success: function (res) {
                if (!res.success || !res.product) {
                    alert(res.message || 'Product create failed');
                    return;
                }
                $('#addProduct').modal('hide');
                $('#qc_product_name').val('');
                $('#qc_product_price').val('');
                var label = res.product.code + ' (' + res.product.name + ')';
                var quotedPrice = parseFloat(res.product.price);
                refreshWarehouseProducts(function () {
                    if (typeof productSearch === 'function') {
                        productSearch(label, {
                            qty: 1,
                            net_unit_price: quotedPrice
                        });
                    } else {
                        $('#lims_productcodeSearch').val(res.product.code).focus();
                        alert('Product created (' + res.product.code + '). Select it from search to add to the quote.');
                    }
                });
            },
            error: function (xhr) {
                var msg = 'Could not create product.';
                if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                alert(msg);
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });
})();
</script>
