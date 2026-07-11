@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('asset.sale.list')}}" class="btn btn-info"><i class="dripicons-list"></i> Asset Sales List </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Sale Asset</h4>
                        </div>
                        <div class="card-body">
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            {!! Form::open(['route' => ['asset.sale'], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-12">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Remove</th>
                                                <th>Asset Name</th>
                                                <th>Book Value</th>
                                                <th>Useful Life in Years</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><input type="checkbox" name="record"></td>
                                                <td>
                                                    <select name="asset_id[]" class="form-control select-asset selectpicker" data-live-search="true" required onchange="selectAsset(this, 0)">
                                                        <option value="">  -- choose -- </option>
                                                        @foreach($assets as $item)
                                                            <option value="{{ $item->id }}" {{ @$data['asset_id'] == $item->id ? 'selected' : '' }}> {{ $item->name }} - {{ $item->serial_no }} - {{$item->department->name }} </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="number" name="price[]" class="form-control price-0 book-value" required step="any" value="{{@$data['book_value'] ?? 0}}"></td>
                                                <td><input type="number" name="life_span[]" class="form-control life_span-0" step="any" placeholder="Useful Life" value="{{@$data['available_in_year']}}"></td>
                                                <td><input type="text" name="remark[]" class="form-control" placeholder="Remark"></td>

                                            </tr>
                                        </tbody>
                                        <tfoot>
                                        <tr>
                                            <td colspan="2"></td>
                                            <td><a class="btn btn-block btn-success add-row text-white"> + Add More</a></td>
                                            <td><span class="btn btn-danger fa fa-trash remove">  Remove Rows</span></td>
                                            <td colspan="2"></td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div class="col-md-12 py-3">
                                    <h2>Sale Detail</h2><hr>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Sale Date</label>
                                        <input type="date" required name="date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Total amount</label>
                                        <input type="number" required id="total-amount" name="buyer_total_amount" class="form-control" value="0" step="any">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Remarks</label>
                                        <input type="text" name="buyer_remark" class="form-control" placeholder="Buyer Remarks">
                                    </div>
                                </div>
                                <div class="col-md-12 py-3">
                                    <h2>Buyer Detail</h2><hr>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <select name="buyer_title" class="form-control">
                                            <option value="Mr">Mr</option>
                                            <option value="Mrs">Mrs</option>
                                            <option value="Ms">Ms</option>
                                            <option value="Dr">Dr</option>
                                            <option value="Prof">Prof</option>
                                            <option value="Chief">Chief</option>
                                            <option value="Engr ">Engr</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" name="buyer_name" class="form-control" placeholder="Buyer Name">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="number" name="buyer_number" class="form-control" placeholder="Buyer Phone Number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Address</label>
                                        <input type="text" name="buyer_address" class="form-control" placeholder="Buyer Address">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="buyer_email" class="form-control" placeholder="Buyer Email Address">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>ID Card Number</label>
                                        <input type="number" name="buyer_id" class="form-control" placeholder="Buyer ID Card Number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date of Issue of ID Card DD/MM/YYYY</label>
                                        <input type="date" name="buyer_id_date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>On</label>
                                        <input type="date" name="buyer_to" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-12 py-3">
                                    <h2>Saller Detail</h2><hr>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <select name="saller_title" class="form-control">
                                            <option value="Mr">Mr</option>
                                            <option value="Mrs">Mrs</option>
                                            <option value="Ms">Ms</option>
                                            <option value="Dr">Dr</option>
                                            <option value="Prof">Prof</option>
                                            <option value="Chief">Chief</option>
                                            <option value="Engr ">Engr</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" name="saller_name" class="form-control" placeholder="saller Name">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="number" name="saller_number" class="form-control" placeholder="saller Phone Number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Address</label>
                                        <input type="text" name="saller_address" class="form-control" placeholder="saller Address">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="saller_email" class="form-control" placeholder="saller Email Address">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>ID Card Number</label>
                                        <input type="number" name="saller_id" class="form-control" placeholder="saller ID Card Number">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date of Issue of ID Card DD/MM/YYYY</label>
                                        <input type="date" name="saller_id_date" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>On</label>
                                        <input type="date" name="saller_to" class="form-control">
                                    </div>
                                </div>
{{--                                end saller detail--}}
                                <div class="col-md-12">
                                    <div class="form-group mt-4">
                                        <input type="submit" value="Sale" class="btn btn-primary" >
                                    </div>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">

        // new row
        let lineNo = 1;
        $(document).ready(function () {
            $(".add-row").click(function () {
                var newRow = $("<tr>");
                var cols = '';

                cols += '<td><input type="checkbox" name="record"></td>';
                cols += '<td><select name="asset_id[]" class="form-control select-asset selectpicker" data-live-search="true" required onchange="selectAsset(this, '+lineNo+')"> <option value="">  -- choose -- </option> @foreach($assets as $item) <option value="{{ $item->id }}"> {{ $item->name }} - {{ $item->serial_no }} - {{$item->department->name }} </option> @endforeach </select></td>';
                cols += '<td><input type="number" name="price[]" class="form-control book-value price-'+lineNo+'" step="any" value="0"></td>';
                cols += '<td><input type="number" name="life_span[]" class="form-control life_span-'+lineNo+'" step="any" placeholder="Useful Life"></td>';
                cols += '<td><input type="text" name="remark[]" class="form-control" placeholder="Remark"></td>';

                newRow.append(cols);
                $("table tbody").prepend(newRow);
                lineNo++;

                $('.selectpicker').selectpicker({
                    style: 'btn-link',
                });
            });
        });

        // Find and remove selected table rows
        $(".remove").click(function(){
            $("table tbody").find('input[name="record"]').each(function(){
                if($(this).is(":checked")){
                    $(this).parents("tr").remove();
                }
                countTotal();
            });
        });

        // ajax
        function selectAsset(selectObject, id) {
            var value = selectObject.value;
            var url = "{{ route('asset.sale.search', ":id") }}";
            url = url.replace(':id', value);
            $.ajax({
                type:'get',
                url: url,
                success: function(data) {
                    $('.price-'+id).val(data['book_value']);
                    $('.life_span-'+id).val(data['available_in_year']);
                    countTotal();
                }
            });
        }

        $('.book-value').on('change keyup paste', function() {
            countTotal();
        });

        function countTotal(){
            var total_sale = 0;
            $(".book-value").each(function() {

                if ($(this).val() == '') {
                    total_sale += 0;
                } else {
                    total_sale += parseFloat($(this).val());
                }
            });
            $("#total-amount").val(total_sale.toFixed(2));
        }

        // menu open
        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-sale-menu").addClass("active");
    </script>
@endsection
