@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('asset.sale.list')}}" class="btn btn-info"><i class="dripicons-list"></i> Asset Sale List </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Sale Asset</h4>
                        </div>
                        <div class="card-body">
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            {!! Form::open(['route' => ['asset.sale.update'], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-12">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Asset Name</th>
                                                <th>Book Value</th>
                                                <th>Useful Life in Years</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($data->saleDetails as $key => $item)
                                            <tr>
                                                <td>{{ $item->asset_name }}</td>
                                                <input type="hidden" name="asset_id[]" value="{{ $item->id }}">
                                                <td><input type="number" name="price[]" class="form-control price-0 book-value" required step="any" value="{{$item->price}}"></td>
                                                <td><input type="number" name="life_span[]" class="form-control life_span-0" step="any" placeholder="Useful Life" value="{{$item->life_span}}"></td>
                                                <td><input type="text" name="remark[]" class="form-control" placeholder="Remark" value="{{$item->remark}}"></td>

                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-12 py-3">
                                    <h2>Sale Detail</h2><hr>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Sale Date</label>
                                        <input type="date" required name="date" class="form-control" value="{{ $data->date }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Total amount</label>
                                        <input type="number" required id="total-amount" name="buyer_total_amount" class="form-control" step="any" value="{{ $data->buyer_total_amount }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Remarks</label>
                                        <input type="text" name="buyer_remark" class="form-control" placeholder="Buyer Remarks" value="{{ $data->buyer_remark }}">
                                    </div>
                                </div>
                                <div class="col-md-12 py-3">
                                    <h2>Buyer Detail</h2><hr>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <select name="buyer_title" class="form-control">
                                            <option value="Mr" {{ $data->buyer_title == 'Mr' ? 'selected' : '' }}>Mr</option>
                                            <option value="Mrs" {{ $data->buyer_title == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                                            <option value="Ms" {{ $data->buyer_title == 'Ms' ? 'selected' : '' }}>Ms</option>
                                            <option value="Dr" {{ $data->buyer_title == 'Dr' ? 'selected' : '' }}>Dr</option>
                                            <option value="Prof" {{ $data->buyer_title == 'Prof' ? 'selected' : '' }}>Prof</option>
                                            <option value="Chief" {{ $data->buyer_title == 'Chief' ? 'selected' : '' }}>Chief</option>
                                            <option value="Engr" {{ $data->buyer_title == 'Engr' ? 'selected' : '' }}>Engr</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="hidden" name="id" value="{{ $data->id }}">
                                        <input type="text" name="buyer_name" class="form-control" placeholder="Buyer Name" value="{{ $data->buyer_name }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="number" name="buyer_number" class="form-control" placeholder="Buyer Phone Number" value="{{ $data->buyer_number }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Address</label>
                                        <input type="text" name="buyer_address" class="form-control" placeholder="Buyer Address" value="{{ $data->buyer_address }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="buyer_email" class="form-control" placeholder="Buyer Email Address" value="{{ $data->buyer_email }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>ID Card Number</label>
                                        <input type="number" name="buyer_id" class="form-control" placeholder="Buyer ID Card Number" value="{{ $data->buyer_id }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date of Issue of ID Card DD/MM/YYYY</label>
                                        <input type="date" name="buyer_id_date" class="form-control" value="{{ $data->buyer_id_date }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>On</label>
                                        <input type="date" name="buyer_to" class="form-control" value="{{ $data->buyer_to }}">
                                    </div>
                                </div>
                                <div class="col-md-12 py-3">
                                    <h2>Saller Detail</h2><hr>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <select name="saller_title" class="form-control">
                                            <option value="Mr" {{ $data->saller_title == 'Mr' ? 'selected' : '' }}>Mr</option>
                                            <option value="Mrs" {{ $data->saller_title == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                                            <option value="Ms" {{ $data->saller_title == 'Ms' ? 'selected' : '' }}>Ms</option>
                                            <option value="Dr" {{ $data->saller_title == 'Dr' ? 'selected' : '' }}>Dr</option>
                                            <option value="Prof" {{ $data->saller_title == 'Prof' ? 'selected' : '' }}>Prof</option>
                                            <option value="Chief" {{ $data->saller_title == 'Chief' ? 'selected' : '' }}>Chief</option>
                                            <option value="Engr" {{ $data->saller_title == 'Engr' ? 'selected' : '' }}>Engr</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" name="saller_name" class="form-control" placeholder="saller Name" value="{{ $data->saller_name }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="number" name="saller_number" class="form-control" placeholder="saller Phone Number" value="{{ $data->saller_number }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Address</label>
                                        <input type="text" name="saller_address" class="form-control" placeholder="saller Address" value="{{ $data->saller_address }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="saller_email" class="form-control" placeholder="saller Email Address" value="{{ $data->saller_email }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>ID Card Number</label>
                                        <input type="number" name="saller_id" class="form-control" placeholder="saller ID Card Number" value="{{ $data->saller_id }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Date of Issue of ID Card DD/MM/YYYY</label>
                                        <input type="date" name="saller_id_date" class="form-control" value="{{ $data->saller_id_date }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>On</label>
                                        <input type="date" name="saller_to" class="form-control" value="{{ $data->saller_to }}">
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="form-group mt-4">
                                        <input type="submit" value="Update Sale" class="btn btn-primary" >
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
