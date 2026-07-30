@extends('layout.main') @section('content')
@php
    $selectedWarehouseId = ! empty($cloneBooking)
        ? (int) $cloneBooking->warehouse_id
        : (int) ($default_warehouse_id ?? 0);
    $selectedBillerId = ! empty($cloneBooking)
        ? (int) $cloneBooking->biller_id
        : (int) ($default_biller_id ?? 0);
@endphp
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
<style>
    /* Compact create-booking to fit more of the form (esp. order table) on screen */
    body:has(.booking-create-page) .beyond-module-tabs {
        margin-bottom: 8px;
    }
    body:has(.booking-create-page) .beyond-module-tabs-label {
        padding: 6px 12px 0;
        font-size: 10px;
    }
    body:has(.booking-create-page) .beyond-module-tabs-nav {
        padding: 6px 8px 8px;
        gap: 4px;
    }
    body:has(.booking-create-page) .beyond-module-tab {
        padding: 5px 9px;
        font-size: 11px;
        border-radius: 8px;
        border-width: 1px;
        gap: 5px;
    }
    body:has(.booking-create-page) .beyond-module-tab .beyond-attention-badge {
        min-width: 16px;
        height: 16px;
        font-size: 9px;
        top: -6px;
        left: -6px;
    }
    body:has(.booking-create-page) section.forms {
        padding-top: 0 !important;
        margin-top: 0 !important;
    }
    body:has(.booking-create-page) .container-fluid {
        padding-left: 10px;
        padding-right: 10px;
    }

    .booking-create-page .card {
        border: 0;
        border-radius: 12px;
        box-shadow: 0 8px 20px rgba(11, 63, 144, 0.07);
        overflow: hidden;
    }
    .booking-create-page .card-header {
        background: linear-gradient(135deg, #0b3f90 0%, #1456b8 100%);
        color: #fff;
        border: 0;
        padding: 10px 16px;
    }
    .booking-create-page .card-header h4 {
        margin: 0;
        font-weight: 800;
        font-size: 18px;
        line-height: 1.2;
    }
    .booking-create-page .card-header .booking-header-sub {
        margin: 2px 0 0;
        font-size: 11px;
        opacity: 0.8;
        font-style: italic;
    }
    .booking-create-page .card-body {
        padding: 12px 14px 14px;
        background: #f8fbff;
    }
    .booking-create-page .card-body > p.italic { display: none; }
    .booking-section {
        background: #fff;
        border: 1px solid #e3e9f4;
        border-radius: 10px;
        padding: 10px 12px 6px;
        margin-bottom: 8px;
    }
    .booking-section-title {
        font-size: 11px;
        font-weight: 800;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #0b3f90;
        margin-bottom: 6px;
    }
    .booking-create-page label {
        font-weight: 700;
        color: #1f2a44;
        font-size: 12px;
        margin-bottom: 2px;
    }
    .booking-create-page .form-group {
        margin-bottom: 0.45rem;
    }
    .booking-create-page .form-control,
    .booking-create-page .bootstrap-select > .dropdown-toggle {
        min-height: 34px !important;
        height: 34px;
        padding-top: 4px !important;
        padding-bottom: 4px !important;
        font-size: 13px;
        border-radius: 8px !important;
        border-color: #d7e0ef !important;
        box-shadow: none !important;
    }
    .booking-create-page .bootstrap-select .dropdown-toggle .filter-option-inner-inner {
        font-size: 13px;
        line-height: 24px;
    }
    .booking-create-page .form-control:focus {
        border-color: #c6ab47 !important;
        box-shadow: 0 0 0 0.12rem rgba(198, 171, 71, 0.16) !important;
    }
    .booking-create-page textarea.form-control {
        height: auto;
        min-height: 56px;
    }
    .booking-create-page .search-box .btn-secondary {
        background: #0b3f90;
        border-color: #0b3f90;
        border-radius: 8px 0 0 8px;
        padding: 4px 10px;
    }
    .booking-create-page .search-box .btn-lg {
        padding: 4px 10px;
        font-size: 14px;
    }
    .booking-create-page .search-box .form-control {
        border-radius: 0 8px 8px 0;
    }
    .booking-create-page .input-with-action .btn-default {
        border-radius: 8px;
        border-color: #c6ab47;
        color: #0b3f90;
        min-width: 34px;
        padding: 4px 8px;
    }
    .booking-create-page small.text-muted {
        font-size: 11px;
        line-height: 1.25;
    }
    .booking-create-page #global-dates-section > p.text-muted {
        margin-bottom: 6px !important;
        font-size: 11px;
    }
    #myTable {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 0;
        font-size: 12px;
    }
    #myTable thead th {
        background: #0b3f90;
        color: #fff;
        border: 0;
        white-space: nowrap;
        font-size: 11px;
        vertical-align: middle;
        padding: 6px 8px;
    }
    #myTable tbody td,
    #myTable tfoot th {
        vertical-align: middle;
        border-color: #edf2f9;
        padding: 4px 6px;
    }
    #myTable th:nth-child(3),
    #myTable td:nth-child(3) {
        min-width: 72px;
        width: 72px;
    }
    #myTable th:nth-child(6),
    #myTable td:nth-child(6) {
        min-width: 78px;
        width: 78px;
    }
    #myTable .number-duration {
        min-width: 64px;
        width: 100%;
        padding: 4px 6px;
        font-size: 13px;
        font-weight: 700;
        text-align: center;
        border-radius: 6px;
        min-height: 30px !important;
        height: 30px;
    }
    #myTable .booking-qty-input,
    #myTable .qty {
        min-width: 64px;
        width: 100%;
        padding: 4px 6px;
        font-size: 13px;
        font-weight: 700;
        border-radius: 6px;
        min-height: 30px !important;
        height: 30px;
    }
    #myTable .product_price_change {
        min-height: 30px !important;
        height: 30px;
        padding: 4px 6px;
        font-size: 13px;
    }
    #myTable .start,
    #myTable .end,
    .booking-create-page .rental-datetime {
        min-width: 148px;
        border-radius: 6px;
        padding: 4px 8px;
        border: 1px solid #d7e0ef;
        background: #fff;
        min-height: 30px !important;
        height: 30px;
        font-size: 12px;
    }
    .input-with-action { display: flex; gap: 6px; align-items: stretch; }
    .input-with-action .bootstrap-select { flex: 1; }
    .contract-panel {
        border: 1px solid #eadfa0;
        border-radius: 10px;
        padding: 10px 12px;
        background: linear-gradient(180deg, #fffdf3 0%, #fff8dc 100%);
        margin-top: 8px;
        font-size: 12px;
    }
    .contract-panel .custom-control { margin-bottom: 0.25rem !important; }
    .booking-summary-bar {
        background: #fff;
        border: 1px solid #e3e9f4;
        border-radius: 10px;
        padding: 10px 14px;
        margin-top: 8px;
    }
    .booking-summary-bar .summary-item {
        font-size: 12px;
        color: #5f6776;
    }
    .booking-summary-bar .summary-item strong {
        display: block;
        color: #0b3f90;
        font-size: 15px;
    }
    .btn-booking-primary {
        background: #0b3f90;
        border-color: #0b3f90;
        color: #fff;
        border-radius: 8px;
        padding: 8px 16px;
        font-weight: 700;
        font-size: 13px;
    }
    .btn-booking-accent {
        background: #c6ab47;
        border-color: #c6ab47;
        color: #10213d;
        border-radius: 8px;
        padding: 8px 16px;
        font-weight: 700;
        font-size: 13px;
    }
    .flatpickr-calendar {
        border-radius: 12px;
        box-shadow: 0 16px 40px rgba(11, 63, 144, 0.18);
        z-index: 10050 !important;
    }
    .booking-create-page select.selectpicker {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .booking-create-page .bootstrap-select {
        width: 100% !important;
    }
    .booking-create-page .bootstrap-select > .dropdown-toggle {
        background: #fff !important;
        border: 1px solid #d7e0ef !important;
    }
    #myTable select.booking_method {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        min-height: 30px !important;
        height: 30px;
        padding: 2px 28px 2px 8px;
        border-radius: 6px;
        border: 1px solid #d7e0ef;
        background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%230b3f90' d='M1.41 0L6 4.58 10.59 0 12 1.41l-6 6-6-6z'/%3E%3C/svg%3E") no-repeat right 8px center;
        background-size: 10px;
        color: #0b3f90;
        font-weight: 600;
        font-size: 12px;
        cursor: pointer;
    }
    #myTable select.booking_method:focus {
        border-color: #c6ab47;
        box-shadow: 0 0 0 0.12rem rgba(198, 171, 71, 0.16);
        outline: none;
    }
    #myTable .ibtnDup {
        background: #c6ab47;
        border-color: #c6ab47;
        color: #fff;
        margin-right: 4px;
        padding: 3px 8px;
        font-size: 12px;
    }
    #myTable .ibtnDup:hover {
        background: #b3983a;
        border-color: #b3983a;
        color: #fff;
    }
    #myTable .ibtnDel {
        padding: 3px 8px;
        font-size: 12px;
    }
    #myTable .booking-line-actions {
        white-space: nowrap;
    }
    #myTable tr.booking-extra-period td.booking-extra-blank {
        background: #f7f9fc;
        color: #9aa6b8;
        font-size: 11px;
    }
    #myTable td.duration {
        position: relative;
        z-index: 2;
        min-width: 160px;
    }
    #myTable td.duration .rental-datetime,
    #myTable td.duration .flatpickr-input {
        display: block;
        width: 100%;
        margin-bottom: 4px;
        pointer-events: auto;
    }
    #myTable td.duration .end,
    #myTable td.duration input.end {
        margin-bottom: 0;
    }
    .booking-create-page .booking-order-wrap {
        position: relative;
        z-index: 2;
        margin-bottom: 6px;
        max-height: min(38vh, 360px);
        overflow: auto;
        border: 1px solid #e3e9f4;
        border-radius: 10px;
        background: #fff;
    }
    .booking-create-page .booking-order-wrap thead th {
        position: sticky;
        top: 0;
        z-index: 3;
    }
    .booking-create-page .table-responsive {
        position: relative;
        z-index: 2;
        margin-bottom: 0;
    }
    @media (max-height: 780px) {
        .booking-create-page .booking-order-wrap {
            max-height: min(32vh, 280px);
        }
        body:has(.booking-create-page) .beyond-module-tabs-nav {
            max-height: 56px;
            overflow-x: auto;
            overflow-y: hidden;
            flex-wrap: nowrap;
        }
    }
</style>
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<section class="forms booking-create-page">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>{{ !empty($cloneBooking) ? 'Clone Equipment Rental Booking' : 'Create Equipment Rental Booking' }}</h4>
                        <p class="booking-header-sub">{{trans('file.The field labels marked with * are required input fields')}}.</p>
                    </div>
                    <div class="card-body">
                        @if(!empty($cloneBooking))
                            <div class="alert alert-info py-2 mb-2">Cloning booking <strong>{{ $cloneBooking->reference_no }}</strong>. You can change the client, dates, products, and send for signature when ready.</div>
                        @endif
                        {!! Form::open(['route' => 'booking.store', 'method' => 'post', 'files' => true, 'class' => 'payment-form']) !!}
                        <div class="booking-section">
                            <div class="booking-section-title">Client & Location</div>
                        <div class="row">
{{--                                    <div class="col-md-4">--}}
{{--                                        <div class="form-group">--}}
{{--                                            <label>--}}
{{--                                                {{trans('file.Reference No')}}--}}
{{--                                            </label>--}}
{{--                                            <input type="text" name="reference_no" class="form-control" />--}}
{{--                                        </div>--}}
{{--                                        @if($errors->has('reference_no'))--}}
{{--                                       <span>--}}
{{--                                           <strong>{{ $errors->first('reference_no') }}</strong>--}}
{{--                                        </span>--}}
{{--                                        @endif--}}
{{--                                    </div>--}}
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.customer')}} *</label>
                                            <div class="input-with-action">
                                                <select required name="customer_id" id="customer_id" class="selectpicker form-control" data-live-search="true" data-size="10" data-live-search-placeholder="Search name or phone..." title="Select customer...">
                                                <?php
                                                    $deposit = [];
                                                    $points = [];
                                                ?>
                                                @foreach($lims_customer_list as $customer)
                                                <?php
                                                    $deposit[$customer->id] = $customer->deposit - $customer->expense;

                                                    $points[$customer->id] = $customer->points;
                                                    $phoneDigits = preg_replace('/[^0-9]/', '', (string) $customer->phone_number);
                                                ?>
                                                <option value="{{$customer->id}}"
                                                    data-tokens="{{ $customer->name }} {{ $customer->phone_number }} {{ $phoneDigits }}"
                                                    @if(!empty($cloneBooking) && $cloneBooking->customer_id == $customer->id) selected @endif
                                                    @if(!$customer->is_active) data-subtext="Inactive" @endif>
                                                    {{$customer->name . ' (' . $customer->phone_number . ')'}}
                                                </option>
                                                @endforeach
                                                </select>
                                                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addBookingCustomer" title="Add Customer"><i class="dripicons-plus"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Warehouse')}} *</label>
                                            <select required name="warehouse_id" id="warehouse_id" class="selectpicker form-control" data-live-search="true"   title="Select warehouse...">
                                                @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}" @if($selectedWarehouseId && (int)$warehouse->id === $selectedWarehouseId) selected @endif>{{$warehouse->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Biller')}} *</label>
                                            <select required name="biller_id" id="biller_id" class="selectpicker form-control" data-live-search="true"   title="Select Biller...">
                                                @foreach($lims_biller_list as $biller)
                                                <option value="{{$biller->id}}" @if($selectedBillerId && (int)$biller->id === $selectedBillerId) selected @endif>{{$biller->name . ' (' . $biller->company_name . ')'}}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" id="default_warehouse_id" value="{{ $selectedWarehouseId ?: '' }}">
                                            <input type="hidden" id="default_biller_id" value="{{ $selectedBillerId ?: '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>CC (Engineer / Company Copy)</label>
                                            <select name="cc_customer[]" class="selectpicker form-control" data-live-search="true" data-size="8" multiple title="Select CC recipients...">
                                                @foreach($lims_customer_list as $customer)
                                                    <option value="{{ $customer->id }}"
                                                        data-tokens="{{ $customer->name }} {{ $customer->company_name }} {{ $customer->phone_number }}"
                                                        @if(!empty($cloneBooking) && !empty($cloneBooking->cc_customer_ids) && in_array($customer->id, explode(',', $cloneBooking->cc_customer_ids))) selected @endif>
                                                        {{ $customer->name }}@if($customer->company_name) ({{ $customer->company_name }})@endif — {{ $customer->phone_number }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">CC contacts receive the equipment list via WhatsApp without pricing.</small>
                                        </div>
                                    </div>
                                </div>
                        </div>
                        <div class="booking-section" id="global-dates-section">
                            <div class="booking-section-title">Default Rental Period</div>
                            <p class="text-muted small mb-1">New items inherit these dates. Use &ldquo;Apply to All Items&rdquo; to update existing rows.</p>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>From Date &amp; Time</label>
                                        <input type="text" id="global_start_date" class="form-control rental-datetime" placeholder="Start date &amp; time" autocomplete="off" value="{{ (!empty($cloneLines) && !empty($cloneLines[0]['start'])) ? $cloneLines[0]['start'] : '' }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>To Date &amp; Time</label>
                                        <input type="text" id="global_end_date" class="form-control rental-datetime" placeholder="Return date &amp; time" autocomplete="off" value="{{ (!empty($cloneLines) && !empty($cloneLines[0]['end'])) ? $cloneLines[0]['end'] : '' }}">
                                    </div>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="form-group w-100">
                                        <button type="button" id="apply-global-dates" class="btn btn-primary btn-sm btn-block">Apply to All Items</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="booking-section">
                            <div class="booking-section-title">Equipment Selection</div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <label>{{trans('file.Select Product')}}</label>
                                        <div class="input-with-action">
                                            <div class="search-box input-group" style="flex:1;">
                                                <button type="button" class="btn btn-secondary btn-lg"><i class="fa fa-barcode"></i></button>
                                                <input type="text" name="product_code_name" id="lims_productcodeSearch" placeholder="Please type product code and select..." class="form-control" />
                                            </div>
                                            <button type="button" class="btn btn-default" data-toggle="modal" data-target="#addBookingProduct" title="Add Product"><i class="dripicons-plus"></i></button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-md-12">
                                        <div class="booking-section-title">Order Table</div>
                                        <div class="booking-order-wrap">
                                        <div class="table-responsive">
                                            <table id="myTable" class="table table-hover order-list mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>{{trans('file.name')}}</th>
                                                        <th>{{trans('file.Code')}}</th>
                                                        <th>Qty</th>
                                                        <th>{{trans('file.Batch No')}}</th>
                                                        <th>Booking Method</th>
                                                        <th>Number</th>
                                                        <th>{{trans('file.Net Unit Price')}}</th>
                                                        <th>Duration</th>
                                                        <th>{{trans('file.Discount')}}</th>
                                                        <th>{{trans('file.Tax')}}</th>
                                                        <th>{{trans('file.Subtotal')}}</th>
                                                        <th><i class="dripicons-trash"></i></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                </tbody>
                                                <tfoot class="tfoot active">
                                                    <th colspan="2">{{trans('file.Total')}}</th>
                                                    <th id="total-qty">0</th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th></th>
                                                    <th id="total-discount">0.00</th>
                                                    <th id="total-tax">0.00</th>
                                                    <th id="total">0.00</th>
                                                    <th><i class="dripicons-trash"></i></th>
                                                </tfoot>
                                            </table>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_qty" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_discount" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_tax" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="total_price" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="item" />
                                            <input type="hidden" name="order_tax" />
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <input type="hidden" name="grand_total" />
                                            <input type="hidden" name="used_points" />
                                            <input type="hidden" name="pos" value="0" />
                                            <input type="hidden" name="coupon_active" value="0" />
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Order Tax')}}</label>
                                            <select class="form-control" name="order_tax_rate">
                                                <option value="0">No Tax</option>
                                                @foreach($lims_tax_list as $tax)
                                                <option value="{{$tax->rate}}">{{$tax->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                <strong>{{trans('file.Order Discount')}}</strong>
                                            </label>
                                            <input type="number" name="order_discount" class="form-control" step="any"/>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                <strong>{{trans('file.Shipping Cost')}}</strong>
                                            </label>
                                            <input type="number" name="shipping_cost" class="form-control" step="any"/>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
{{--                                    <div class="col-md-4">--}}
{{--                                        <div class="form-group">--}}
{{--                                            <label>{{trans('file.Attach Document')}}</label> <i class="dripicons-question" data-toggle="tooltip" title="Only jpg, jpeg, png, gif, pdf, csv, docx, xlsx and txt file is supported"></i>--}}
{{--                                            <input type="file" name="document" class="form-control" />--}}
{{--                                            @if($errors->has('extension'))--}}
{{--                                                <span>--}}
{{--                                                   <strong>{{ $errors->first('extension') }}</strong>--}}
{{--                                                </span>--}}
{{--                                            @endif--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Booking Status *</label>
                                            <select name="booking_status" class="form-control">
                                                <option value="2">{{trans('file.Pending')}}</option>
                                                <option value="1">{{trans('file.Completed')}}</option>
                                            </select>
                                        </div>
                                    </div>
{{--                                    <input name="payment_status" type="hidden" value="4" class="form-control">--}}
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Payment Status')}} *</label>
                                            <select name="payment_status" class="form-control">
                                                <option value="1">{{trans('file.Pending')}}</option>
                                                <option value="2">{{trans('file.Due')}}</option>
                                                <option value="3">{{trans('file.Partial')}}</option>
                                                <option value="4">{{trans('file.Paid')}}</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div id="payment">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>{{trans('file.Paid By')}}</label>
                                                <select name="paid_by_id" class="form-control">
                                                    <option value="1">Cash</option>
                                                    <option value="3">Credit Card</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>{{trans('file.Recieved Amount')}} *</label>
                                                <input type="number" name="paying_amount" class="form-control" id="paying-amount" step="any" />
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>{{trans('file.Paying Amount')}} *</label>
                                                <input type="number" name="paid_amount" class="form-control" id="paid-amount" step="any"/>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>{{trans('file.Change')}}</label>
                                                <p id="change" class="ml-2">0.00</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="card-element" class="form-control">
                                                </div>
                                                <div class="card-errors" role="alert"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="gift-card">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label> {{trans('file.Gift Card')}} *</label>
                                                <select id="gift_card_id" name="gift_card_id" class="selectpicker form-control" data-live-search="true"   title="Select Gift Card..."></select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row" id="cheque">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>{{trans('file.Cheque Number')}} *</label>
                                                <input type="text" name="cheque_no" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>{{trans('file.Payment Note')}}</label>
                                            <textarea rows="3" class="form-control" name="payment_note"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-1">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Booking Note</label>
                                            <textarea rows="3" class="form-control booking-note-editor" id="booking_note" name="booking_note"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Staff Note')}}</label>
                                            <textarea rows="3" class="form-control" name="staff_note"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="contract-panel">
                                    <p class="mb-2"><strong>Contract type</strong></p>
                                    <div class="custom-control custom-radio mb-2">
                                        <input type="radio" class="custom-control-input" id="contract_type_none" name="contract_type" value="none" checked>
                                        <label class="custom-control-label" for="contract_type_none">No contract — standard booking receipt</label>
                                    </div>
                                    <div class="custom-control custom-radio mb-2">
                                        <input type="radio" class="custom-control-input" id="contract_type_equipment" name="contract_type" value="equipment">
                                        <label class="custom-control-label" for="contract_type_equipment"><strong>Equipment Rental Contract</strong></label>
                                    </div>
                                    <div class="custom-control custom-radio mb-2">
                                        <input type="radio" class="custom-control-input" id="contract_type_accommodation" name="contract_type" value="accommodation">
                                        <label class="custom-control-label" for="contract_type_accommodation"><strong>Accommodation Contract</strong> (student housing / rooms)</label>
                                    </div>
                                    <div class="custom-control custom-radio mb-2">
                                        <input type="radio" class="custom-control-input" id="contract_type_software_license" name="contract_type" value="software_license">
                                        <label class="custom-control-label" for="contract_type_software_license"><strong>Licenses Software Subscription</strong> (IPTV, antivirus, software licenses)</label>
                                    </div>
                                    <div class="custom-control custom-radio mb-2">
                                        <input type="radio" class="custom-control-input" id="contract_type_studio_rental" name="contract_type" value="studio_rental">
                                        <label class="custom-control-label" for="contract_type_studio_rental"><strong>Studio Rental Agreement</strong> (hourly / daily / monthly sessions)</label>
                                    </div>
                                    <p class="text-muted mb-0">When a contract type is selected, use <strong>Save &amp; Send for Signature</strong> to WhatsApp the agreement link to the client. The booking receipt is generated only after the client signs. Signature is allowed only once.</p>
                                    <input type="hidden" name="send_for_signature" id="send_for_signature" value="0">
                                </div>
                                <div class="form-group mt-3">
                                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-booking-primary" id="submit-button">
                                    <button type="submit" class="btn btn-booking-accent" id="send-contract-button" style="display:none;">Save &amp; Send for Signature</button>
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <table class="table table-bordered table-condensed totals">
            <td><strong>{{trans('file.Items')}}</strong>
                <span class="pull-right" id="item">0.00</span>
            </td>
            <td><strong>{{trans('file.Total')}}</strong>
                <span class="pull-right" id="subtotal">0.00</span>
            </td>
            <td><strong>{{trans('file.Order Tax')}}</strong>
                <span class="pull-right" id="order_tax">0.00</span>
            </td>
            <td><strong>{{trans('file.Order Discount')}}</strong>
                <span class="pull-right" id="order_discount">0.00</span>
            </td>
            <td><strong>{{trans('file.Shipping Cost')}}</strong>
                <span class="pull-right" id="shipping_cost">0.00</span>
            </td>
            <td><strong>{{trans('file.grand total')}}</strong>
                <span class="pull-right" id="grand_total">0.00</span>
            </td>
        </table>
    </div>
    <div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="modal_header" class="modal-title"></h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-group">
                            <label>{{trans('file.Quantity')}}</label>
                            <input type="number" name="edit_qty" class="form-control" step="any">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Unit Discount')}}</label>
                            <input type="number" name="edit_discount" class="form-control" step="any">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Unit Price')}}</label>
                            <input type="number" name="edit_unit_price" class="form-control" step="any">
                        </div>
                        <?php
                $tax_name_all[] = 'No Tax';
                $tax_rate_all[] = 0;
                foreach($lims_tax_list as $tax) {
                    $tax_name_all[] = $tax->name;
                    $tax_rate_all[] = $tax->rate;
                }
            ?>
                            <div class="form-group">
                                <label>{{trans('file.Tax Rate')}}</label>
                                <select name="edit_tax_rate" class="form-control selectpicker">
                                    @foreach($tax_name_all as $key => $name)
                                    <option value="{{$key}}">{{$name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="edit_unit" class="form-group">
                                <label>{{trans('file.Product Unit')}}</label>
                                <select name="edit_unit" class="form-control selectpicker">
                                </select>
                            </div>
                            <button type="button" name="update_btn" class="btn btn-primary">{{trans('file.update')}}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- add cash register modal -->
    <div id="cash-register-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
          <div class="modal-content">
            {!! Form::open(['route' => 'cashRegister.store', 'method' => 'post']) !!}
            <div class="modal-header">
              <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Cash Register')}}</h5>
              <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
              <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                <div class="row">
                  <div class="col-md-6 form-group warehouse-section">
                      <label>{{trans('file.Warehouse')}} *</strong> </label>
                      <select required name="warehouse_id" class="selectpicker form-control" data-live-search="true"   title="Select warehouse...">
                          @foreach($lims_warehouse_list as $warehouse)
                          <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                          @endforeach
                      </select>
                  </div>
                  <div class="col-md-6 form-group">
                      <label>{{trans('file.Cash in Hand')}} *</strong> </label>
                      <input type="number" name="cash_in_hand" required class="form-control">
                  </div>
                  <div class="col-md-12 form-group">
                      <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                  </div>
                </div>
            </div>
            {{ Form::close() }}
          </div>
        </div>
    </div>

    <div id="addBookingCustomer" class="modal fade text-left" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Customer</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Customer Group *</label>
                        <select id="quick_customer_group_id" class="form-control">
                            @foreach($lims_customer_group_all as $customer_group)
                                <option value="{{ $customer_group->id }}">{{ $customer_group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" id="quick_customer_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="text" id="quick_customer_phone" class="form-control phone-sanitize" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" id="quick_customer_email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" id="quick_customer_address" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" id="quick_customer_city" class="form-control">
                    </div>
                    <button type="button" class="btn btn-primary" id="save-quick-customer">Save Customer</button>
                </div>
            </div>
        </div>
    </div>

    <div id="addBookingProduct" class="modal fade text-left" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Product</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" id="quick_product_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Product Code *</label>
                        <div class="input-group">
                            <input type="text" id="quick_product_code" class="form-control" required>
                            <div class="input-group-append">
                                <button type="button" id="quick-gen-product-code" class="btn btn-default" title="Generate code"><i class="fa fa-refresh"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select id="quick_product_category_id" class="form-control">
                            @foreach($lims_category_list as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rent Price / Hour</label>
                        <input type="number" step="any" id="quick_rent_hour" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Rent Price / Day</label>
                        <input type="number" step="any" id="quick_rent_day" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Rent Price / Month</label>
                        <input type="number" step="any" id="quick_rent_month" class="form-control" value="0">
                    </div>
                    <div class="form-group">
                        <label>Initial Stock Qty</label>
                        <input type="number" step="any" id="quick_product_qty" class="form-control" value="1">
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="quick_requires_quantity" checked>
                            <label class="custom-control-label" for="quick_requires_quantity">Require quantity tracking for this rental item</label>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" id="save-quick-product">Save Digital Rental Product</button>
                    <button type="button" class="btn btn-success" id="save-and-add-quick-product">Save and Add</button>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script type="text/javascript">

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function formatLocalDateTime(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate())
            + ' ' + pad2(date.getHours()) + ':' + pad2(date.getMinutes());
    }

    function roundToQuarterHour(date) {
        var d = new Date(date.getTime());
        var mins = d.getMinutes();
        var rounded = Math.ceil(mins / 15) * 15;
        if (rounded === 60) {
            d.setHours(d.getHours() + 1);
            d.setMinutes(0);
        } else {
            d.setMinutes(rounded);
        }
        d.setSeconds(0);
        d.setMilliseconds(0);
        return d;
    }

    function defaultRentalStartDate() {
        return formatLocalDateTime(roundToQuarterHour(new Date()));
    }

    function defaultRentalEndDate() {
        var d = roundToQuarterHour(new Date());
        d.setHours(d.getHours() + 2);
        return formatLocalDateTime(d);
    }

    function initRentalDatePickers(context) {
        if (typeof flatpickr === 'undefined') {
            return;
        }

        var scope = context ? context : document;
        var nodes = scope.querySelectorAll('.rental-datetime');
        nodes.forEach(function (node) {
            if (node._flatpickr) {
                return;
            }
            flatpickr(node, {
                enableTime: true,
                dateFormat: 'Y-m-d H:i',
                altInput: true,
                altFormat: 'd/m/Y, H:i',
                time_24hr: true,
                minuteIncrement: 15,
                allowInput: true,
                clickOpens: true,
                appendTo: document.body,
                defaultDate: node.value ? node.value : new Date(),
                onOpen: function (selectedDates, dateStr, instance) {
                    if (!instance.input.value) {
                        instance.setDate(new Date(), false);
                    }
                }
            });
        });
    }

    function getGlobalStartDate() {
        var el = document.getElementById('global_start_date');
        if (!el) {
            return '';
        }
        return el._flatpickr ? el._flatpickr.input.value : el.value;
    }

    function getGlobalEndDate() {
        var el = document.getElementById('global_end_date');
        if (!el) {
            return '';
        }
        return el._flatpickr ? el._flatpickr.input.value : el.value;
    }

    function setFlatpickrValue(node, value) {
        if (!node || !value) {
            return;
        }
        if (node._flatpickr) {
            node._flatpickr.setDate(value, true);
        } else {
            node.value = value;
        }
    }

    function ensureDefaultGlobalDates() {
        if (!getGlobalStartDate()) {
            setFlatpickrValue(document.getElementById('global_start_date'), defaultRentalStartDate());
        }
        if (!getGlobalEndDate()) {
            setFlatpickrValue(document.getElementById('global_end_date'), defaultRentalEndDate());
        }
    }

    function applyGlobalDatesToRow($row) {
        ensureDefaultGlobalDates();
        var start = getGlobalStartDate() || defaultRentalStartDate();
        var end = getGlobalEndDate() || defaultRentalEndDate();
        setFlatpickrValue($row.find('.start')[0], start);
        setFlatpickrValue($row.find('.end')[0], end);
    }

    function applyLineOptionsToRow($row, opts) {
        if (!opts) {
            applyGlobalDatesToRow($row);
            return;
        }

        if (opts.qty) {
            $row.find('.qty').val(opts.qty);
        }
        if (opts.booking_method !== undefined && opts.booking_method !== '') {
            $row.find('.booking_method').val(opts.booking_method);
        }
        if (opts.number_duration) {
            $row.find('.number-duration').val(opts.number_duration);
        }
        if (opts.start) {
            setFlatpickrValue($row.find('.start')[0], opts.start);
        } else {
            applyGlobalDatesToRow($row);
        }
        if (opts.end) {
            setFlatpickrValue($row.find('.end')[0], opts.end);
        }
        if (opts.net_unit_price && $row.find('.product_price_change').length) {
            $row.find('.product_price_change').val(opts.net_unit_price);
        }

        var productId = $row.find('.product-id').val();
        durationChange($row.find('.qty')[0], productId);
    }

    initRentalDatePickers(document.getElementById('global-dates-section'));
    ensureDefaultGlobalDates();

    $('#apply-global-dates').on('click', function () {
        $('table.order-list tbody tr').each(function () {
            var $row = $(this);
            applyGlobalDatesToRow($row);
            var productId = $row.find('.product-id').val();
            if (productId) {
                durationChange($row.find('.start')[0], productId, false);
            }
        });
    });

    $(document).on('input', '.phone-sanitize', function () {
        this.value = this.value.replace(/\s+/g, '');
    });

    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
    $("ul#booking #booking-create-menu").addClass("active");

    function selectedContractType() {
        return $('input[name="contract_type"]:checked').val() || 'none';
    }

    function isSignatureContractType(type) {
        return type === 'equipment' || type === 'accommodation' || type === 'software_license' || type === 'studio_rental';
    }

    function toggleContractSendButton() {
        var type = selectedContractType();
        if (isSignatureContractType(type)) {
            $('#send-contract-button').show();
        } else {
            $('#send-contract-button').hide();
            $('#send_for_signature').val('0');
        }
    }

    $('input[name="contract_type"]').on('change', toggleContractSendButton);
    toggleContractSendButton();

    $('#send-contract-button').on('click', function (e) {
        var type = selectedContractType();
        if (!isSignatureContractType(type)) {
            e.preventDefault();
            alert('Please select Equipment Rental, Accommodation, Licenses Software Subscription, or Studio Rental.');
            return false;
        }
        $('#send_for_signature').val('1');
        $('select[name="booking_status"]').val('2');
    });

    $('#submit-button').on('click', function () {
        if (selectedContractType() === 'none') {
            $('#send_for_signature').val('0');
        }
    });

    $('#save-quick-customer').on('click', function () {
        $.ajax({
            url: '{{ route("booking.quick-customer") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                customer_group_id: $('#quick_customer_group_id').val(),
                customer_name: $('#quick_customer_name').val(),
                phone_number: $('#quick_customer_phone').val(),
                email: $('#quick_customer_email').val(),
                address: $('#quick_customer_address').val(),
                city: $('#quick_customer_city').val()
            },
            success: function (response) {
                $('#customer_id').append('<option value="' + response.id + '" selected>' + response.label + '</option>');
                $('#customer_id').selectpicker('refresh');
                $('#addBookingCustomer').modal('hide');
                alert('Customer added successfully.');
            },
            error: function (xhr) {
                alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Could not add customer.');
            }
        });
    });

    function generateQuickProductCode() {
        $.get('{{ url("products/gencode") }}', function (data) {
            $('#quick_product_code').val(data);
        });
    }

    function resetQuickProductForm() {
        $('#quick_product_name').val('');
        $('#quick_rent_hour, #quick_rent_day, #quick_rent_month').val(0);
        $('#quick_product_qty').val(1);
        $('#quick_requires_quantity').prop('checked', true);
        generateQuickProductCode();
        $('#quick_product_name').focus();
    }

    function addQuickProductToBooking(code) {
        var warehouseId = $('select[name="warehouse_id"]').val();
        if (!warehouseId) {
            alert('Please select a warehouse first.');
            return;
        }
        $.get('getproduct/' + warehouseId, function () {
            productSearch(code);
        });
    }

    function saveQuickProduct(keepOpen) {
        if (!$('#quick_product_name').val() || !$('#quick_product_code').val()) {
            alert('Product name and code are required.');
            return;
        }

        $.ajax({
            url: '{{ route("booking.quick-product") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                name: $('#quick_product_name').val(),
                code: $('#quick_product_code').val(),
                category_id: $('#quick_product_category_id').val(),
                rent_price_per_hour: $('#quick_rent_hour').val(),
                rent_price_per_day: $('#quick_rent_day').val(),
                rent_price_per_month: $('#quick_rent_month').val(),
                qty: $('#quick_product_qty').val(),
                requires_quantity: $('#quick_requires_quantity').is(':checked') ? 1 : 0
            },
            success: function (response) {
                addQuickProductToBooking(response.code);
                if (keepOpen) {
                    resetQuickProductForm();
                } else {
                    $('#addBookingProduct').modal('hide');
                }
            },
            error: function (xhr) {
                var message = 'Could not add product.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    message = Object.values(xhr.responseJSON.errors).join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                alert(message);
            }
        });
    }

    $('#quick-gen-product-code').on('click', generateQuickProductCode);
    $('#addBookingProduct').on('shown.bs.modal', function () {
        if (!$('#quick_product_code').val()) {
            generateQuickProductCode();
        }
    });
    $('#save-quick-product').on('click', function () {
        saveQuickProduct(false);
    });
    $('#save-and-add-quick-product').on('click', function () {
        saveQuickProduct(true);
    });

    var public_key = <?php echo json_encode($lims_pos_setting_data->stripe_public_key) ?>;
    var currency = <?php echo json_encode($currency) ?>;

$("#payment").hide();
$(".card-element").hide();
$("#gift-card").hide();
$("#cheque").hide();

// array data depend on warehouse
var lims_product_array = [];
var product_code = [];
var product_name = [];
var product_qty = [];
var product_type = [];
var product_id = [];
var product_list = [];
var qty_list = [];

// array data with selection
var product_price = [];
var product_duration = [];
var product_discount = [];
var tax_rate = [];
var tax_name = [];
var tax_method = [];
var unit_name = [];
var unit_operator = [];
var unit_operation_value = [];
var gift_card_amount = [];
var gift_card_expense = [];
// temporary array
var temp_unit_name = [];
var temp_unit_operator = [];
var temp_unit_operation_value = [];

var deposit = <?php echo json_encode($deposit) ?>;
var points = <?php echo json_encode($points) ?>;
var reward_point_setting = <?php echo json_encode($lims_reward_point_setting_data) ?>;

var rowindex;
var customer_group_rate;
var row_product_price;
var pos;
var role_id = <?php echo json_encode(Auth::user()->role_id)?>;

$('.selectpicker').selectpicker({
    style: 'btn-link',
});

$('[data-toggle="tooltip"]').tooltip();

// Force-select defaults after bootstrap-select init (HTML selected is often ignored with title=)
(function applyBookingDefaults() {
    var wh = String($('#default_warehouse_id').val() || '');
    var bl = String($('#default_biller_id').val() || '');
    if (wh) {
        $('#warehouse_id').val(wh);
        try { $('#warehouse_id').selectpicker('val', wh); } catch (e) {}
    }
    if (bl) {
        $('#biller_id').val(bl);
        try { $('#biller_id').selectpicker('val', bl); } catch (e) {}
    }
    try {
        $('#warehouse_id').selectpicker('refresh');
        $('#biller_id').selectpicker('refresh');
    } catch (e) {}
})();

$('select[name="customer_id"]').on('change', function() {
    var id = $(this).val();
    $.get('getcustomergroup/' + id, function(data) {
        customer_group_rate = (data / 100);
    });
});

$('#warehouse_id').on('change', function() {
    var id = $(this).val();
    if (!id) {
        return;
    }
    $.get('getproduct/' + id, function(data) {
        lims_product_array = [];
        product_code = data[0];
        product_name = data[1];
        product_qty = data[2];
        product_type = data[3];
        product_id = data[4];
        product_list = data[5];
        qty_list = data[6];
        product_warehouse_price = data[7];
        batch_no = data[8];
        product_batch_id = data[9];
        $.each(product_code, function(index) {
            lims_product_array.push(product_code[index] + ' (' + product_name[index] + ')');
        });
    });

    isCashRegisterAvailable(id);
});

// Load products for the default/selected warehouse on open
if ($('#warehouse_id').val()) {
    $('#warehouse_id').trigger('change');
}

$('#lims_productcodeSearch').on('input', function(){
    var customer_id = $('#customer_id').val();
    var warehouse_id = $('#warehouse_id').val();
    temp_data = $('#lims_productcodeSearch').val();
    if(!customer_id){
        $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
        alert('Please select Customer!');
    }
    else if(!warehouse_id){
        $('#lims_productcodeSearch').val(temp_data.substring(0, temp_data.length - 1));
        alert('Please select Warehouse!');
    }

});

var lims_productcodeSearch = $('#lims_productcodeSearch');

lims_productcodeSearch.autocomplete({
    source: function(request, response) {
        var matcher = new RegExp(".?" + $.ui.autocomplete.escapeRegex(request.term), "i");
        response($.grep(lims_product_array, function(item) {
            return matcher.test(item);
        }));
    },
    response: function(event, ui) {
        if (ui.content.length == 1) {
            var data = ui.content[0].value;
            $(this).autocomplete( "close" );
            productSearch(data);
        };
    },
    select: function(event, ui) {
        var data = ui.item.value;
        productSearch(data);
    }
});

//Change quantity
$("#myTable").on('input', '.qty', function() {
    rowindex = $(this).closest('tr').index();
    if($(this).val() < 0 && $(this).val() != '') {
      $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(1);
      alert("Quantity can't be less than 0");
    }
    checkQuantity($(this).val(), true, rowindex);
});


//Delete product
$("table.order-list tbody").on("click", ".ibtnDel", function(event) {
    rowindex = $(this).closest('tr').index();
    product_price.splice(rowindex, 1);
    product_discount.splice(rowindex, 1);
    tax_rate.splice(rowindex, 1);
    tax_name.splice(rowindex, 1);
    tax_method.splice(rowindex, 1);
    unit_name.splice(rowindex, 1);
    unit_operator.splice(rowindex, 1);
    unit_operation_value.splice(rowindex, 1);
    $(this).closest("tr").remove();
    calculateTotal();
});

// Duplicate product line for another booking period (same item, new method/dates/price)
$("table.order-list tbody").on("click", ".ibtnDup", function(event) {
    var code = $(this).closest('tr').find('.product-code').val();
    if (!code) {
        return;
    }
    productSearch(code, { forceNew: true });
});

//Edit product
$("table.order-list").on("click", ".edit-product", function() {
    rowindex = $(this).closest('tr').index();
    edit();
});

//Update product

function changePrice(selectObject){
    var rowindex = selectObject.parentNode.closest('tr').rowIndex - 1;
    var row_index = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')');
    product_price[rowindex] = selectObject.value;
    product_duration[rowindex] = row_index.find('.number-duration').val();
    var edit_qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val();
    checkQuantity(edit_qty, false, rowindex);
}

function durationChange(selectObject, id, flag = true, price_change = false){
    var rowindex = selectObject.parentNode.closest('tr').rowIndex - 1;
    var row_index = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')');
    var start = row_index.find('.start').val();
    var end = row_index.find('.end').val();
    var number_duration = row_index.find('.number-duration').val();
    var method = row_index.find('.booking_method').val();

    var row_product_code = row_index.find('.product-code').val() || row_index.find('td:nth-child(2)').text();
    var pos = product_code.indexOf(row_product_code);

    var qty = row_index.find('.qty').val();
    var product_quantity = parseFloat(product_qty[pos]);

    if(product_type[pos] == 'standard') {
        if(qty > product_quantity) {
            $.ajax({
                type: 'GET',
                url: '{{ route("booking.search_qty_by_duration") }}',
                data: {
                    id: id,
                    start: start,
                    end: end,
                    qty: qty,
                    product_quantity: product_quantity,
                },
                success: function(data) {
                    if(+qty > +data) {
                        alert('We have not enough stock on this product');
                        row_index.find('.qty').val(data);
                    }
                }
            });
        }
    }
    if(flag) {
        $.ajax({
            type: 'GET',
            url: '{{ route("booking.search_by_duration") }}',
            data: {
                id: id,
                method: method
            },
            success: function(data) {
                var product_index = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')');
                if(price_change == 'price_change') {
                    product_price[rowindex] = data;
                }
                product_duration[rowindex] = number_duration;
                var edit_qty = product_index.find('.qty').val();
                checkQuantity(edit_qty, true, rowindex);
            }
        });
    }
}

$('button[name="update_btn"]').on("click", function() {
    var edit_discount = $('input[name="edit_discount"]').val();
    var edit_qty = $('input[name="edit_qty"]').val();
    var edit_unit_price = $('input[name="edit_unit_price"]').val();

    if (parseFloat(edit_discount) > parseFloat(edit_unit_price)) {
        alert('Invalid Discount Input!');
        return;
    }

    if(edit_qty < 1) {
        $('input[name="edit_qty"]').val(1);
        edit_qty = 1;
        alert("Quantity can't be less than 1");
    }

    var tax_rate_all = <?php echo json_encode($tax_rate_all) ?>;
    tax_rate[rowindex] = parseFloat(tax_rate_all[$('select[name="edit_tax_rate"]').val()]);
    tax_name[rowindex] = $('select[name="edit_tax_rate"] option:selected').text();
    if(product_type[pos] == 'standard'){
        var row_unit_operator = unit_operator[rowindex].slice(0, unit_operator[rowindex].indexOf(","));
        var row_unit_operation_value = unit_operation_value[rowindex].slice(0, unit_operation_value[rowindex].indexOf(","));
        if (row_unit_operator == '*') {
            product_price[rowindex] = $('input[name="edit_unit_price"]').val() / row_unit_operation_value;
        } else {
            product_price[rowindex] = $('input[name="edit_unit_price"]').val() * row_unit_operation_value;
        }
        var position = $('select[name="edit_unit"]').val();
        var temp_operator = temp_unit_operator[position];
        var temp_operation_value = temp_unit_operation_value[position];
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sale-unit').val(temp_unit_name[position]);
        temp_unit_name.splice(position, 1);
        temp_unit_operator.splice(position, 1);
        temp_unit_operation_value.splice(position, 1);

        temp_unit_name.unshift($('select[name="edit_unit"] option:selected').text());
        temp_unit_operator.unshift(temp_operator);
        temp_unit_operation_value.unshift(temp_operation_value);

        unit_name[rowindex] = temp_unit_name.toString() + ',';
        unit_operator[rowindex] = temp_unit_operator.toString() + ',';
        unit_operation_value[rowindex] = temp_unit_operation_value.toString() + ',';
    }
    else {
        product_price[rowindex] = $('input[name="edit_unit_price"]').val();
    }
    product_discount[rowindex] = $('input[name="edit_discount"]').val();
    checkQuantity(edit_qty, false, rowindex);
});

$("#myTable").on("change", ".batch-no", function () {
    rowindex = $(this).closest('tr').index();
    var product_id = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-id').val();
    var warehouse_id = $('#warehouse_id').val();
    $.get('../check-batch-availability/' + product_id + '/' + $(this).val() + '/' + warehouse_id, function(data) {
        if(data['message'] != 'ok') {
            alert(data['message']);
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.batch-no').val('');
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-batch-id').val('');
        }
        else {
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-batch-id').val(data['product_batch_id']);
            code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product-code').val();
            pos = product_code.indexOf(code);
            product_qty[pos] = data['qty'];
        }
    });
});

function isCashRegisterAvailable(warehouse_id) {
    $.ajax({
        url: '../cash-register/check-availability/'+warehouse_id,
        type: "GET",
        success:function(data) {
            if(data == 'false') {
                $('#cash-register-modal select[name=warehouse_id]').val(warehouse_id);
                $('.selectpicker').selectpicker('refresh');
                if(role_id <= 2){
                    $("#cash-register-modal .warehouse-section").removeClass('d-none');
                }
                else {
                    $("#cash-register-modal .warehouse-section").addClass('d-none');
                }
                $("#cash-register-modal").modal('show');
            }
        }
    });
}

function productSearch(searchTerm, lineOpts, callback) {
    if (typeof lineOpts === 'function') {
        callback = lineOpts;
        lineOpts = null;
    }

    var requestData = Array.isArray(searchTerm) ? searchTerm[1] : searchTerm;

    $.ajax({
        type: 'GET',
        url: 'lims_product_search',
        data: {
            data: requestData
        },
        success: function(data) {
            var flag = 1;
            var forceNew = !!(lineOpts && lineOpts.forceNew);
            if (!forceNew) {
                $(".product-code").each(function(i) {
                    if ($(this).val() == data[1]) {
                        rowindex = i;
                        var qty = parseFloat($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val()) + 1;
                        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .qty').val(qty);
                        checkQuantity(String(qty), true, rowindex);
                        flag = 0;
                    }
                });
            }
            $("input[name='product_code_name']").val('');
            if(flag){
                var newRow = $("<tr>");
                var cols = '';
                pos = product_code.indexOf(data[1]);
                temp_unit_name = (data[6]).split(',');
                if (forceNew) {
                    newRow.addClass('booking-extra-period');
                    // Extra period line: hide Name / Code / Qty / Batch (same product via hidden fields)
                    cols += '<td class="booking-extra-blank"></td>';
                    cols += '<td class="booking-extra-blank"></td>';
                    cols += '<td class="booking-extra-blank"><input type="hidden" class="qty booking-qty-input" name="qty[]" value="1" /></td>';
                    cols += '<td class="booking-extra-blank"><input type="hidden" class="product-batch-id" name="product_batch_id[]" value="'+(data[12] ? (product_batch_id[pos] || '') : '')+'" /></td>';
                } else {
                    cols += '<td>' + data[0] + '<br><small>Qty : '+ data[16] +'</small></td>';
                    cols += '<td>' + data[1] + '</td>';
                    cols += '<td><input type="number" onchange="durationChange(this, '+ data[9] +')" class="form-control qty booking-qty-input" name="qty[]" value="1" step="any" min="0" required/></td>';
                    if(data[12]) {
                        cols += '<td><input type="text" class="form-control batch-no" value="'+batch_no[pos]+'" required/> <input type="hidden" class="product-batch-id" name="product_batch_id[]" value="'+product_batch_id[pos]+'"/> </td>';
                    }
                    else {
                        cols += '<td><input type="text" class="form-control batch-no" disabled/> <input type="hidden" class="product-batch-id" name="product_batch_id[]"/> </td>';
                    }
                }

                cols += '<td><select class="form-control booking_method" name="booking_method[]" onchange="durationChange(this, '+ data[9] +', true, `price_change`)"  required><option value="">--choose--</option><option value="0">Hourly</option><option value="1">Daily</option><option value="2">Monthly</option></select></td>';

                cols += '<td><input type="number" class="form-control number-duration" onchange="durationChange(this, '+ data[9] +')" name="number[]" value="1" required /></td>';

                @if(in_array("price-change", $all_permission))
                    cols += '<td class="col-sm-2"><input onchange="changePrice(this)" onkeyup="changePrice(this)" type="number" class="product_price_change form-control" /></td>';
                @else
                    cols += '<td class="net_unit_cost"></td>';
                @endif

                cols += '<td class="duration"><input type="text" name="start[]" class="start form-control rental-datetime" placeholder="Start date & time" onchange="durationChange(this, '+ data[9] +', false)" required><input type="text" name="end[]" class="end form-control rental-datetime" placeholder="Return date & time" onchange="durationChange(this, '+ data[9] +', false)" required></td>';
                cols += '<td class="discount">0.00</td>';
                cols += '<td class="tax"></td>';
                cols += '<td class="sub-total"></td>';
                cols += '<td class="booking-line-actions"><button type="button" class="ibtnDup btn btn-md" title="Add another period for this product"><i class="dripicons-plus"></i></button><button type="button" class="ibtnDel btn btn-md btn-danger">{{trans("file.delete")}}</button></td>';
                cols += '<input type="hidden" class="product-code" name="product_code[]" value="' + data[1] + '"/>';
                cols += '<input type="hidden" class="product-id" name="product_id[]" value="' + data[9] + '"/>';
                cols += '<input type="hidden" class="sale-unit" name="sale_unit[]" value="' + temp_unit_name[0] + '"/>';
                cols += '<input type="hidden" class="net_unit_price" name="net_unit_price[]" />';
                cols += '<input type="hidden" class="discount-value" name="discount[]" />';
                cols += '<input type="hidden" class="tax-rate" name="tax_rate[]" value="' + data[3] + '"/>';
                cols += '<input type="hidden" class="tax-value" name="tax[]" />';
                cols += '<input type="hidden" class="subtotal-value" name="subtotal[]" />';

                newRow.append(cols);
                // Extra period (+) goes at the bottom; new products still prepend
                if (forceNew) {
                    $("table.order-list tbody").append(newRow);
                } else {
                    $("table.order-list tbody").prepend(newRow);
                }
                rowindex = newRow.index();
                initRentalDatePickers(newRow[0]);

                if(!data[11] && product_warehouse_price[pos]) {
                    product_price.splice(rowindex, 0, parseFloat(product_warehouse_price[pos] * currency['exchange_rate']) + parseFloat(product_warehouse_price[pos] * currency['exchange_rate'] * customer_group_rate));
                }
                else {
                    product_price.splice(rowindex, 0, parseFloat(data[13] * currency['exchange_rate']) + parseFloat(data[13] * currency['exchange_rate'] * customer_group_rate));
                }
                product_discount.splice(rowindex, 0, '0.00');
                tax_rate.splice(rowindex, 0, parseFloat(data[3]));
                tax_name.splice(rowindex, 0, data[4]);
                tax_method.splice(rowindex, 0, data[5]);
                unit_name.splice(rowindex, 0, data[6]);
                unit_operator.splice(rowindex, 0, data[7]);
                unit_operation_value.splice(rowindex, 0, data[8]);
                // forceNew: leave method empty; still default dates to today (editable)
                if (!forceNew) {
                    applyLineOptionsToRow($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')'), lineOpts);
                } else {
                    applyGlobalDatesToRow($('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')'));
                }
                checkQuantity(1, true, rowindex);
            }
            if (callback) {
                callback();
            }
        }
    });
}

function edit()
{
    var row_product_name = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(1)').text();
    var row_product_code = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('td:nth-child(2)').text();
    $('#modal_header').text(row_product_name + '(' + row_product_code + ')');

    var qty = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val();
    $('input[name="edit_qty"]').val(qty);

    $('input[name="edit_discount"]').val(parseFloat(product_discount[rowindex]).toFixed(2));

    var tax_name_all = <?php echo json_encode($tax_name_all) ?>;
    pos = tax_name_all.indexOf(tax_name[rowindex]);
    $('select[name="edit_tax_rate"]').val(pos);

    pos = product_code.indexOf(row_product_code);
    if(product_type[pos] == 'standard'){
        unitConversion(rowindex);
        temp_unit_name = (unit_name[rowindex]).split(',');
        temp_unit_name.pop();
        temp_unit_operator = (unit_operator[rowindex]).split(',');
        temp_unit_operator.pop();
        temp_unit_operation_value = (unit_operation_value[rowindex]).split(',');
        temp_unit_operation_value.pop();
        $('select[name="edit_unit"]').empty();
        $.each(temp_unit_name, function(key, value) {
            $('select[name="edit_unit"]').append('<option value="' + key + '">' + value + '</option>');
        });
        $("#edit_unit").show();
    }
    else{
        row_product_price = product_price[rowindex];
        $("#edit_unit").hide();
    }
    $('input[name="edit_unit_price"]').val(row_product_price.toFixed(2));
    $('.selectpicker').selectpicker('refresh');
}

function checkQuantity(sale_qty, flag, rowindex = false) {
    var $row = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')');
    var row_product_code = $row.find('.product-code').val() || $row.find('td:nth-child(2)').text();
    pos = product_code.indexOf(row_product_code);
    if(product_type[pos] == 'standard'){
        var operator = unit_operator[rowindex].split(',');
        var operation_value = unit_operation_value[rowindex].split(',');
        if(operator[0] == '*')
            total_qty = sale_qty * operation_value[0];
        else if(operator[0] == '/')
            total_qty = sale_qty / operation_value[0];
        // if (total_qty > parseFloat(product_qty[pos])) {
        //     alert('Quantity exceeds stock quantity!');
        //     if (flag) {
        //         sale_qty = sale_qty.substring(0, sale_qty.length - 1);
        //         $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
        //     }
        //     else {
        //         edit();
        //         return;
        //     }
        // }
    }
    else if(product_type[pos] == 'combo'){
        child_id = product_list[pos].split(',');
        child_qty = qty_list[pos].split(',');
        $(child_id).each(function(index) {
            var position = product_id.indexOf(parseInt(child_id[index]));
            // if( parseFloat(sale_qty * child_qty[index]) > product_qty[position] ) {
            //     alert('Quantity exceeds stock quantity!');
            //     if (flag) {
            //         sale_qty = sale_qty.substring(0, sale_qty.length - 1);
            //         $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
            //     }
            //     else {
            //         edit();
            //         flag = true;
            //         return false;
            //     }
            // }
        });
    }

    if(!flag){
        $('#editModal').modal('hide');
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.qty').val(sale_qty);
    }
    calculateRowProductData(sale_qty, rowindex, flag);
}

function calculateRowProductData(quantity, rowindex = false, flag = true) {

    var row_index = $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')');

    if(product_type[pos] == 'standard')
        unitConversion(rowindex);
    else
        row_product_price = product_price[rowindex];

    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.discount').text((product_discount[rowindex] * quantity).toFixed(2));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.discount-value').val((product_discount[rowindex] * quantity).toFixed(2));
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-rate').val(tax_rate[rowindex].toFixed(2));

    if (tax_method[rowindex] == 1) {
        var net_unit_price = row_product_price - product_discount[rowindex];
        var tax = net_unit_price * quantity * (tax_rate[rowindex] / 100);
        var sub_total = (net_unit_price * product_duration[rowindex] * quantity) + tax;

        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.net_unit_price').text(net_unit_price.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.net_unit_price').val(net_unit_price.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_price_change').attr("value", net_unit_price.toFixed(2));
        if(flag) {
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_price_change').val(net_unit_price.toFixed(2));
        }
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax').text(tax.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-value').val(tax.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sub-total').text(sub_total.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.subtotal-value').val(sub_total.toFixed(2));
    } else {
        var sub_total_unit = row_product_price - product_discount[rowindex];
        var net_unit_price = (100 / (100 + tax_rate[rowindex])) * sub_total_unit;
        var tax = (sub_total_unit - net_unit_price) * quantity;
        var sub_total = sub_total_unit * product_duration[rowindex] * quantity;

        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.net_unit_price').text(net_unit_price.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.net_unit_price').val(net_unit_price.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_price_change').attr("value", net_unit_price.toFixed(2));
        if(flag) {
            $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.product_price_change').val(net_unit_price.toFixed(2));
        }
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax').text(tax.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.tax-value').val(tax.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.sub-total').text(sub_total.toFixed(2));
        $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ')').find('.subtotal-value').val(sub_total.toFixed(2));
    }

    calculateTotal();
}

function unitConversion(rowindex) {
    var row_unit_operator = unit_operator[rowindex].slice(0, unit_operator[rowindex].indexOf(","));
    var row_unit_operation_value = unit_operation_value[rowindex].slice(0, unit_operation_value[rowindex].indexOf(","));

    if (row_unit_operator == '*') {
        row_product_price = product_price[rowindex] * row_unit_operation_value;
    } else {
        row_product_price = product_price[rowindex] / row_unit_operation_value;
    }
}

function calculateTotal() {
    //Sum of quantity
    var total_qty = 0;
    $(".qty").each(function() {
        if ($(this).val() == '') {
            total_qty += 0;
        } else {
            total_qty += parseFloat($(this).val());
        }
    });
    $("#total-qty").text(total_qty);
    $('input[name="total_qty"]').val(total_qty);

    //Sum of discount
    var total_discount = 0;
    $(".discount").each(function() {
        total_discount += parseFloat($(this).text());
    });
    $("#total-discount").text(total_discount.toFixed(2));
    $('input[name="total_discount"]').val(total_discount.toFixed(2));

    //Sum of tax
    var total_tax = 0;
    $(".tax").each(function() {
        total_tax += parseFloat($(this).text());
    });
    $("#total-tax").text(total_tax.toFixed(2));
    $('input[name="total_tax"]').val(total_tax.toFixed(2));

    //Sum of subtotal
    var total = 0;
    $(".sub-total").each(function() {
        total += parseFloat($(this).text());
    });
    $("#total").text(total.toFixed(2));
    $('input[name="total_price"]').val(total.toFixed(2));

    calculateGrandTotal();
}

function calculateGrandTotal() {

    var item = $('table.order-list tbody tr:last').index();

    var total_qty = parseFloat($('#total-qty').text());
    var subtotal = parseFloat($('#total').text());
    var order_tax = parseFloat($('select[name="order_tax_rate"]').val());
    var order_discount = parseFloat($('input[name="order_discount"]').val());
    var shipping_cost = parseFloat($('input[name="shipping_cost"]').val());

    if (!order_discount)
        order_discount = 0.00;
    if (!shipping_cost)
        shipping_cost = 0.00;

    item = ++item + '(' + total_qty + ')';
    order_tax = (subtotal - order_discount) * (order_tax / 100);
    var grand_total = (subtotal + order_tax + shipping_cost) - order_discount;

    $('#item').text(item);
    $('input[name="item"]').val($('table.order-list tbody tr:last').index() + 1);
    $('#subtotal').text(subtotal.toFixed(2));
    $('#order_tax').text(order_tax.toFixed(2));
    $('input[name="order_tax"]').val(order_tax.toFixed(2));
    $('#order_discount').text(order_discount.toFixed(2));
    $('#shipping_cost').text(shipping_cost.toFixed(2));
    $('#grand_total').text(grand_total.toFixed(2));
    if( $('select[name="payment_status"]').val() == 4 ){
        $('#paying-amount').val('');
        $('#paid-amount').val(grand_total.toFixed(2));
    }
    $('input[name="grand_total"]').val(grand_total.toFixed(2));
}

$('input[name="order_discount"]').on("input", function() {
    calculateGrandTotal();
});

$('input[name="shipping_cost"]').on("input", function() {
    calculateGrandTotal();
});

$('select[name="order_tax_rate"]').on("change", function() {
    calculateGrandTotal();
});

$('select[name="payment_status"]').on("change", function() {
    var payment_status = $(this).val();
    if (payment_status == 3 || payment_status == 4) {
        $("#paid-amount").prop('disabled',false);
        $("#payment").show();
        $("#paying-amount").prop('required',true);
        $("#paid-amount").prop('required',true);
        if(payment_status == 4){
            $("#paid-amount").prop('disabled',true);
            $('input[name="paying_amount"]').val($('input[name="grand_total"]').val());
            $('input[name="paid_amount"]').val($('input[name="grand_total"]').val());
        }
    }
    else{
        $("#paying-amount").prop('required',false);
        $("#paid-amount").prop('required',false);
        $('input[name="paying_amount"]').val('');
        $('input[name="paid_amount"]').val('');
        $("#payment").hide();
    }
});

$('select[name="paid_by_id"]').on("change", function() {
    var id = $(this).val();
    $(".payment-form").off("submit");
    $('input[name="cheque_no"]').attr('required', false);
    $('select[name="gift_card_id"]').attr('required', false);
    if(id == 2) {
        $("#gift-card").show();
        $.ajax({
            url: 'get_gift_card',
            type: "GET",
            dataType: "json",
            success:function(data) {
                $('select[name="gift_card_id"]').empty();
                $.each(data, function(index) {
                    gift_card_amount[data[index]['id']] = data[index]['amount'];
                    gift_card_expense[data[index]['id']] = data[index]['expense'];
                    $('select[name="gift_card_id"]').append('<option value="'+ data[index]['id'] +'">'+ data[index]['card_no'] +'</option>');
                });
                $('.selectpicker').selectpicker('refresh');
            }
        });
        $(".card-element").hide();
        $("#cheque").hide();
        $('select[name="gift_card_id"]').attr('required', true);
    }
    else if (id == 3) {
        $.getScript( "../public/vendor/stripe/checkout.js" );
        $(".card-element").show();
        $("#gift-card").hide();
        $("#cheque").hide();
    }
    else if (id == 4) {
        $("#cheque").show();
        $("#gift-card").hide();
        $(".card-element").hide();
        $('input[name="cheque_no"]').attr('required', true);
    }
    else {
        $("#gift-card").hide();
        $(".card-element").hide();
        $("#cheque").hide();
        if (id == 6) {
            if($('input[name="paid_amount"]').val() > deposit[$('#customer_id').val()]){
                alert('Amount exceeds customer deposit! Customer deposit : '+ deposit[$('#customer_id').val()]);
            }
        }
        else if (id == 7) {
            pointCalculation();
        }
    }
});

function pointCalculation() {
    paid_amount = $('input[name=paid_amount]').val();
    required_point = Math.ceil(paid_amount / reward_point_setting['per_point_amount']);
    if(required_point > points[$('#customer_id').val()]) {
      alert('Customer does not have sufficient points. Available points: '+points[$('#customer_id').val()]);
    }
    else {
      $("input[name=used_points]").val(required_point);
    }
}

$('select[name="gift_card_id"]').on("change", function() {
    var balance = gift_card_amount[$(this).val()] - gift_card_expense[$(this).val()];
    if($('input[name="paid_amount"]').val() > balance){
        alert('Amount exceeds card balance! Gift Card balance: '+ balance);
    }
});

$('input[name="paid_amount"]').on("input", function() {
    if( $(this).val() > parseFloat($('input[name="paying_amount"]').val()) ) {
        alert('Paying amount cannot be bigger than recieved amount');
        $(this).val('');
    }
    else if( $(this).val() > parseFloat($('#grand_total').text()) ){
        alert('Paying amount cannot be bigger than grand total');
        $(this).val('');
    }

    $("#change").text( parseFloat($("#paying-amount").val() - $(this).val()).toFixed(2) );
    var id = $('select[name="paid_by_id"]').val();
    if(id == 2){
        var balance = gift_card_amount[$("#gift_card_id").val()] - gift_card_expense[$("#gift_card_id").val()];
        if($(this).val() > balance)
            alert('Amount exceeds card balance! Gift Card balance: '+ balance);
    }
    else if(id == 6){
        if( $('input[name="paid_amount"]').val() > deposit[$('#customer_id').val()] )
            alert('Amount exceeds customer deposit! Customer deposit : '+ deposit[$('#customer_id').val()]);
    }
});

$('input[name="paying_amount"]').on("input", function() {
    $("#change").text( parseFloat( $(this).val() - $("#paid-amount").val()).toFixed(2));
});

$(window).keydown(function(e){
    if (e.which == 13) {
        var $targ = $(e.target);
        if (!$targ.is("textarea") && !$targ.is(":button,:submit")) {
            var focusNext = false;
            $(this).find(":input:visible:not([disabled],[readonly]), a").each(function(){
                if (this === e.target) {
                    focusNext = true;
                }
                else if (focusNext){
                    $(this).focus();
                    return false;
                }
            });
            return false;
        }
    }
});

$(document).on('submit', '.payment-form', function(e) {
    if (typeof tinymce !== 'undefined') {
        tinymce.triggerSave();
    }
    var rownumber = $('table.order-list tbody tr:last').index();
    if ( rownumber < 0 ) {
        alert("Please insert product to order table!")
        e.preventDefault();
    }
    else if( parseFloat($("#paying-amount").val()) < parseFloat($("#paid-amount").val()) ){
        alert('Paying amount cannot be bigger than recieved amount');
        e.preventDefault();
    }
    else if( $('select[name="payment_status"]').val() == 3 && parseFloat($("#paid-amount").val()) == parseFloat($('input[name="grand_total"]').val()) ) {
        alert('Paying amount equals to grand total! Please change payment status.');
        e.preventDefault();
    }
    else {
        $("#paid-amount").prop('disabled',false);
        $(".batch-no").prop('disabled', false);
    }
});

@if(!empty($cloneBooking))
(function loadClonedBookingLines() {
    var cloneLines = @json($cloneLines ?? []);
    if (!cloneLines.length) {
        return;
    }

    var warehouseId = $('#warehouse_id').val();
    var lineIndex = 0;

    function loadWarehouseProducts(done) {
        $.get('getproduct/' + warehouseId, function(data) {
            lims_product_array = [];
            product_code = data[0];
            product_name = data[1];
            product_qty = data[2];
            product_type = data[3];
            product_id = data[4];
            product_list = data[5];
            qty_list = data[6];
            product_warehouse_price = data[7];
            batch_no = data[8];
            product_batch_id = data[9];
            $.each(product_code, function(index) {
                lims_product_array.push(product_code[index] + ' (' + product_name[index] + ')');
            });
            done();
        });
    }

    function loadNextLine() {
        if (lineIndex >= cloneLines.length) {
            return;
        }
        var line = cloneLines[lineIndex++];
        productSearch(line.code, line, loadNextLine);
    }

    loadWarehouseProducts(loadNextLine);
})();
@endif

</script>
@endsection @section('scripts')
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>
<script type="text/javascript">
if (typeof tinymce !== 'undefined') {
    tinymce.init({
        selector: 'textarea.booking-note-editor',
        height: 180,
        menubar: false,
        plugins: 'lists link paste code',
        toolbar: 'undo redo | bold italic underline | bullist numlist | removeformat',
        branding: false,
        statusbar: false
    });
}
</script>
@endsection
