{{-- Quick-create customer & product for quotation create/edit --}}
<style>
.quotation-qc.card {
    border: 0;
    border-radius: 22px;
    box-shadow: 0 18px 50px rgba(7, 47, 107, 0.08);
}
.quotation-qc > .card-header {
    background: var(--beyond-primary, #0b3f90);
    color: #fff;
    border: 0;
    padding: 18px 22px;
}
.quotation-qc > .card-header h4 { margin: 0; font-weight: 700; letter-spacing: 0.01em; color: #fff; }
.quotation-qc > .card-body { padding: 22px; background: var(--beyond-bg, #f3f6fb); }
.quotation-qc label { font-size: 12px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: var(--beyond-muted, #6f7b91); }
.quotation-qc .form-group { margin-bottom: 16px; }
.quotation-qc .input-with-action { display: flex; gap: 8px; align-items: stretch; }
.quotation-qc .input-with-action .bootstrap-select { flex: 1; min-width: 0; }
.quotation-qc .input-with-action .btn-default { flex: 0 0 auto; }
.quotation-qc .form-control,
.quotation-qc .bootstrap-select > .dropdown-toggle,
.quotation-qc select.form-control {
    border: 1.5px solid #d5deee !important;
    border-radius: 12px !important;
    background: var(--beyond-card, #fff) !important;
    min-height: 46px;
    color: var(--beyond-text, #1f2a44);
    box-shadow: none;
}
.quotation-qc .bootstrap-select.form-control {
    border: 0 !important;
    background: transparent !important;
    padding: 0;
    min-height: 0;
}
.quotation-qc .form-control:focus,
.quotation-qc .bootstrap-select > .dropdown-toggle:focus,
.quotation-qc .bootstrap-select.show > .dropdown-toggle {
    border-color: var(--beyond-accent, #c6ab47) !important;
    box-shadow: 0 0 0 3px rgba(198, 171, 71, 0.28);
    background: #fff !important;
}
.quotation-qc .search-box .form-control {
    border-color: var(--beyond-primary, #0b3f90) !important;
    background: #f7f9fd !important;
}
.ui-autocomplete.ui-front {
    z-index: 10050 !important;
    background: #fff !important;
    color: #1f2a44 !important;
    border: 1px solid #0b3f90 !important;
    border-radius: 12px;
    max-height: 280px;
    overflow-y: auto;
    width: min(420px, calc(100vw - 32px)) !important;
    padding: 6px !important;
}
.ui-autocomplete.ui-front li.ui-menu-item {
    color: #1f2a44 !important;
    padding: 8px 10px !important;
}
.quotation-qc .input-with-action .btn-default,
.quotation-qc .search-box .btn {
    border: 1.5px solid var(--beyond-accent, #c6ab47);
    border-radius: 8px;
    min-height: 42px;
    background: #fff8ea;
    color: var(--beyond-primary-dark, #072f6b);
}
.quotation-qc table.order-list {
    border: 1.5px solid var(--beyond-primary, #0b3f90);
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 8px;
    overflow: hidden;
    table-layout: fixed;
    width: 100%;
}
.quotation-qc table.order-list thead th:nth-child(1),
.quotation-qc table.order-list tbody td:nth-child(1) {
    width: 42%;
    white-space: normal;
}
.quotation-qc table.order-list thead th:nth-child(2),
.quotation-qc table.order-list tbody td:nth-child(2),
.quotation-qc table.order-list thead th:nth-child(3),
.quotation-qc table.order-list tbody td:nth-child(3) {
    width: 16%;
}
.quotation-qc table.order-list tbody td .form-control {
    width: 100%;
    min-height: 40px;
    border-radius: 10px !important;
}
.quotation-qc table.order-list th,
.quotation-qc table.order-list td {
    border: 0;
    border-bottom: 1px solid #e4ebf5;
    vertical-align: middle;
}
.quotation-qc table.order-list thead th {
    background: var(--beyond-primary, #0b3f90);
    color: #fff;
    border-color: var(--beyond-primary, #0b3f90);
    font-size: 12px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.quotation-qc table.order-list tfoot th { background: #e8eef8; color: var(--beyond-primary, #0b3f90); }
.quotation-qc #submit-button,
.quotation-qc input[type="submit"] {
    min-height: 48px;
    padding: 0 28px;
    border: 0;
    border-radius: 14px;
    background: var(--beyond-primary, #0b3f90);
    color: #fff;
    font-weight: 700;
}
.quote-file { margin-top: 6px; }
.quote-file input[type="file"] { display: none; }
.quote-file-drop {
    border: 1.5px dashed #9eb0d0;
    border-radius: 16px;
    background: #fff;
    padding: 16px;
    text-align: center;
    cursor: pointer;
    color: var(--beyond-muted, #6f7b91);
}
.quote-file-drop strong { display: block; color: var(--beyond-primary, #0b3f90); margin-bottom: 4px; }
.quote-file-drop span, .quote-file-drop em { display: block; font-style: normal; font-size: 13px; }
.quote-file-drop em { margin-top: 6px; color: var(--beyond-primary, #0b3f90); font-weight: 700; }
.quote-file-drop.is-hot { background: #eef3fb; border-color: var(--beyond-primary, #0b3f90); }
.quote-file img { display: none; margin-top: 10px; max-height: 120px; border-radius: 12px; }
.quote-summary {
    border: 0 !important;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 10px 30px rgba(7, 47, 107, 0.06);
}
.quote-summary td { border: 0 !important; padding: 14px 16px !important; }
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
                    <label>{{trans('file.Phone Number')}} *</label>
                    <input type="text" id="qc_customer_phone" required class="form-control js-phone-holder" autofocus>
                </div>
                <div class="form-group">
                    <label>{{trans('file.name')}} *</label>
                    <input type="text" id="qc_customer_name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>{{trans('file.Customer Group')}} *</label>
                    <select required class="form-control" name="qc_customer_group_id" id="qc_customer_group_id">
                        @foreach($lims_customer_group_all as $customer_group)
                            <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>{{trans('file.Email')}}</label>
                    <input type="email" id="qc_customer_email" class="form-control">
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

    $('.quote-file').each(function () {
        var box = this;
        var input = box.querySelector('input[type="file"]');
        var drop = box.querySelector('.quote-file-drop');
        var name = box.querySelector('.quote-file-name');
        var preview = box.querySelector('img');
        if (!input || !drop) return;
        function show(file) {
            if (name) name.textContent = file ? file.name : '';
            if (!preview) return;
            if (file && /^image\//.test(file.type)) {
                var reader = new FileReader();
                reader.onload = function (ev) { preview.src = ev.target.result; preview.style.display = 'inline-block'; };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
        function assign(file) {
            if (!file) return;
            try {
                var data = new DataTransfer();
                data.items.add(file);
                input.files = data.files;
            } catch (e) {}
            show(file);
        }
        drop.addEventListener('click', function () { input.click(); });
        drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        input.addEventListener('change', function () { show(input.files && input.files[0]); });
        ['dragenter', 'dragover'].forEach(function (evt) {
            drop.addEventListener(evt, function (e) { e.preventDefault(); drop.classList.add('is-hot'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            drop.addEventListener(evt, function () { drop.classList.remove('is-hot'); });
        });
        drop.addEventListener('drop', function (e) {
            e.preventDefault();
            if (e.dataTransfer.files && e.dataTransfer.files[0]) assign(e.dataTransfer.files[0]);
        });
        drop.addEventListener('paste', function (e) {
            var items = (e.clipboardData || window.clipboardData).items;
            if (!items) return;
            for (var i = 0; i < items.length; i++) {
                if (items[i].kind === 'file') {
                    e.preventDefault();
                    assign(items[i].getAsFile());
                    return;
                }
            }
        });
    });
})();
</script>
