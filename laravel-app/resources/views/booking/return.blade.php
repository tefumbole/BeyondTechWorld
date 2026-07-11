@extends('layout.main') @section('content')
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>Return Booking</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => ['booking.return.data', $lims_sale_data->id], 'method' => 'post', 'files' => true, 'id' => 'payment-form']) !!}
                        <div class="row">
                            <div class="col-md-12">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.reference')}}</label>
                                            <p><strong>{{ $lims_sale_data->reference_no }}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.customer')}} *</label>
                                            <input type="hidden" name="customer_id" value="{{$lims_sale_data->customer_id}}">
                                            <p><strong>{{ @$lims_sale_data->customer->name }}</strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Warehouse')}} *</label>
                                            <p><strong>{{ @$lims_sale_data->warehouse->name }}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Biller')}} *</label>
                                            <p><strong>{{ @$lims_sale_data->biller->name }}</strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-5">
                                    <div class="col-md-12">
                                        <h5>{{trans('file.Order Table')}} *</h5>
                                        <div class="table-responsive mt-3">
                                            <table id="myTable" class="table table-hover order-list">
                                                <thead>
                                                <tr>
                                                    <th>{{trans('file.name')}}</th>
                                                    <th>{{trans('file.Code')}}</th>
                                                    <th>{{trans('file.Quantity')}}</th>
                                                    <th>{{trans('file.Batch No')}}</th>
                                                    <th>{{trans('file.Net Unit Price')}}</th>
                                                    <th>Duration</th>
                                                    <th>{{trans('file.Discount')}}</th>
                                                    <th>{{trans('file.Tax')}}</th>
                                                    <th>{{trans('file.Subtotal')}}</th>
                                                    <th><i class="dripicons-return"></i></th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <?php
                                                $temp_unit_name = [];
                                                $temp_unit_operator = [];
                                                $temp_unit_operation_value = [];
                                                ?>
                                                @foreach($lims_product_sale_data as $product_sale)
                                                    <tr>
                                                            <?php
                                                            $product_data = DB::table('products')->find($product_sale->product_id);
                                                            if($product_sale->variant_id){
                                                                $product_variant_data = \App\ProductVariant::select('id', 'item_code')->FindExactProduct($product_data->id, $product_sale->variant_id)->first();
                                                                $product_variant_id = $product_variant_data->id;
                                                                $product_data->code = $product_variant_data->item_code;
                                                            }
                                                            else
                                                                $product_variant_id = null;
                                                            if($product_data->tax_method == 1){
                                                                $product_price = $product_sale->net_unit_price + ($product_sale->discount / $product_sale->qty);
                                                            }
                                                            elseif ($product_data->tax_method == 2) {
                                                                $product_price =($product_sale->total / $product_sale->qty) + ($product_sale->discount / $product_sale->qty);
                                                            }

                                                            $tax = DB::table('taxes')->where('rate',$product_sale->tax_rate)->first();
                                                            $unit_name = array();
                                                            $unit_operator = array();
                                                            $unit_operation_value = array();
                                                            if($product_data->type == 'standard'){
                                                                $units = DB::table('units')->where('base_unit', $product_data->unit_id)->orWhere('id', $product_data->unit_id)->get();

                                                                foreach($units as $unit) {
                                                                    if($product_sale->sale_unit_id == $unit->id) {
                                                                        array_unshift($unit_name, $unit->unit_name);
                                                                        array_unshift($unit_operator, $unit->operator);
                                                                        array_unshift($unit_operation_value, $unit->operation_value);
                                                                    }
                                                                    else {
                                                                        $unit_name[]  = $unit->unit_name;
                                                                        $unit_operator[] = $unit->operator;
                                                                        $unit_operation_value[] = $unit->operation_value;
                                                                    }
                                                                }
                                                                if($unit_operator[0] == '*'){
                                                                    $product_price = $product_price / $unit_operation_value[0];
                                                                }
                                                                elseif($unit_operator[0] == '/'){
                                                                    $product_price = $product_price * $unit_operation_value[0];
                                                                }
                                                            }
                                                            else {
                                                                $unit_name[] = 'n/a';
                                                                $unit_operator[] = 'n/a'. ',';
                                                                $unit_operation_value[] = 'n/a'. ',';
                                                            }
                                                            $temp_unit_name = $unit_name = implode(",",$unit_name);

                                                            $temp_unit_operator = $unit_operator = implode(",",$unit_operator) .',';

                                                            $temp_unit_operation_value = $unit_operation_value =  implode(",",$unit_operation_value) . ',';

                                                            $product_batch_data = \App\ProductBatch::select('batch_no', 'expired_date')->find($product_sale->product_batch_id);
                                                            ?>
                                                        <td>{{$product_data->name}} <button type="button" class="edit-product btn btn-link" data-toggle="modal" data-target="#editModal"> <i class="dripicons-document-edit"></i></button> <input type="hidden" class="product-type" value="{{$product_data->type}}" /></td>
                                                        <td>{{$product_data->code}}</td>
                                                        <td>{{$product_sale->qty}}</td>
                                                        @if($product_batch_data)
                                                            <td>
                                                                <input type="hidden" class="product-batch-id" name="product_batch_id[]" value="{{$product_sale->product_batch_id}}">
                                                                <input type="text" class="form-control batch-no" name="batch_no[]" value="{{$product_batch_data->batch_no}}" required/>
                                                            </td>
                                                        @else
                                                            <td>
                                                                <input type="hidden" class="product-batch-id" name="product_batch_id[]" value="">
                                                                <input type="text" class="form-control batch-no" name="batch_no[]" value="" disabled />
                                                            </td>
                                                        @endif
                                                        <td class="net_unit_price">{{ number_format((float)$product_sale->net_unit_price, 2, '.', '') }}</td>
                                                        <td class="duration">{{ $product_sale->start }} | {{ $product_sale->end }}</td>
                                                        <td class="discount">{{ number_format((float)$product_sale->discount, 2, '.', '') }}</td>
                                                        <td class="tax">{{ number_format((float)$product_sale->tax, 2, '.', '') }}</td>
                                                        <td class="sub-total">{{ number_format((float)$product_sale->total, 2, '.', '') }}</td>
                                                        <td>@if($product_sale->is_return == 0)<button type="button" class="ibtnDel btn btn-md btn-warning">{{trans("file.Return")}}@endif</button></td>
                                                        <input type="hidden" class="product-code" name="product_code[]" value="{{$product_data->code}}"/>
                                                        @if($product_sale->is_return == 0)
                                                            <input type="hidden" class="product-id" name="product_id[]" value="{{$product_data->id}}"/>
                                                            <input type="hidden" class="is_return" name="is_return[]" value="0"/>
                                                        @else
                                                            <input type="hidden" class="is_return" name="is_return[]" value="1"/>
                                                        @endif
                                                        <input type="hidden" name="product_variant_id[]" value="{{$product_variant_id}}"/>
                                                        <input type="hidden" class="product-price" name="product_price[]" value="{{$product_price}}"/>
                                                        <input type="hidden" class="sale-unit" name="sale_unit[]" value="{{$unit_name}}"/>
                                                        <input type="hidden" class="sale-unit-operator" value="{{$unit_operator}}"/>
                                                        <input type="hidden" class="sale-unit-operation-value" value="{{$unit_operation_value}}"/>
                                                        <input type="hidden" class="net_unit_price" name="net_unit_price[]" value="{{$product_sale->net_unit_price}}" />
                                                        <input type="hidden" class="discount-value" name="discount[]" value="{{$product_sale->discount}}" />
                                                        <input type="hidden" class="tax-rate" name="tax_rate[]" value="{{$product_sale->tax_rate}}"/>

                                                        <input type="hidden" class="qty" name="qty[]" value="{{$product_sale->qty}}"/>
                                                        <input type="hidden" class="warehouse_id" name="warehouse_id[]" value="{{$lims_sale_data->warehouse_id}}"/>
                                                        @if($tax)
                                                            <input type="hidden" class="tax-name" value="{{$tax->name}}" />
                                                        @else
                                                            <input type="hidden" class="tax-name" value="No Tax" />
                                                        @endif
                                                        <input type="hidden" class="tax-method" value="{{$product_data->tax_method}}"/>
                                                        <input type="hidden" class="tax-value" name="tax[]" value="{{$product_sale->tax}}" />
                                                        <input type="hidden" class="subtotal-value" name="subtotal[]" value="{{$product_sale->total}}" />
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                                <tfoot class="tfoot active">
                                                <th colspan="2">{{trans('file.Total')}}</th>
                                                <th id="total-qty">{{$lims_sale_data->total_qty}}</th>
                                                <th></th>
                                                <th></th>
                                                <th></th>
                                                <th id="total-discount">{{ number_format((float)$lims_sale_data->total_discount, 2, '.', '') }}</th>
                                                <th id="total-tax">{{ number_format((float)$lims_sale_data->total_tax, 2, '.', '')}}</th>
                                                <th id="total">{{ number_format((float)$lims_sale_data->total_price, 2, '.', '') }}</th>
                                                <th><i class="dripicons-return"></i></th>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Order Tax')}}</label>
                                            <p><strong>{{$lims_sale_data->order_tax_rate}}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                <strong>{{trans('file.Order Discount')}}</strong>
                                            </label>
                                            <p><strong>{{$lims_sale_data->order_discount}}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                <strong>{{trans('file.Shipping Cost')}}</strong>
                                            </label>
                                            <p><strong>{{$lims_sale_data->shipping_cost}}</strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Booking Status *</label>
                                                <select name="booking_status" class="form-control">

                                                    @if($lims_sale_data->booking_status == 2)
                                                        <option value="2" {{ $lims_sale_data->booking_status == 2 ? 'selected' : '' }}>{{trans('file.Pending')}}</option>
                                                    @endif
                                                    @if($lims_sale_data->booking_status == 1 || $lims_sale_data->booking_status == 3 || $lims_sale_data->booking_status == 4)
                                                            <option value="4">Partial Return</option>
                                                            <option value="1">{{trans('file.Completed')}}</option>
                                                            <option value="3">{{trans('file.Return')}}</option>
{{--                                                            <option value="4" {{ $lims_sale_data->booking_status == 4 ? 'selected' : '' }}>Partial Return</option>--}}
{{--                                                            <option value="1" {{ $lims_sale_data->booking_status == 1 ? 'selected' : '' }}>{{trans('file.Completed')}}</option>--}}
{{--                                                            <option value="3" {{ $lims_sale_data->booking_status == 3 ? 'selected' : '' }}>{{trans('file.Return')}}</option>--}}
                                                    @endif

                                                </select>
                                        </div>
                                    </div>
                                    @if($lims_sale_data->coupon_id)
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>
                                                <strong>{{trans('file.Coupon Discount')}}</strong>
                                            </label>
                                            <p><strong>{{number_format((float)$lims_sale_data->coupon_discount, 2, '.', '')}}</strong></p>
                                        </div>
                                    </div>
                                    @endif
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Booking Note</label>
                                            <p><strong>{{$lims_sale_data->booking_note}}</strong></p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Staff Note')}}</label>
                                            <p><strong>{{$lims_sale_data->staff_note}}</strong></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-button">
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
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
</section>
<script type="text/javascript">
    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
    $("ul#booking #booking-index-menu").addClass("active");

//Delete product
$("table.order-list tbody").on("click", ".ibtnDel", function(event) {
    rowindex = $(this).closest('tr').index();
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .is_return').val(1);
    $('table.order-list tbody tr:nth-child(' + (rowindex + 1) + ') .ibtnDel').hide();
});

</script>
@endsection @section('scripts')
<script type="text/javascript" src="https://js.stripe.com/v3/"></script>

@endsection
