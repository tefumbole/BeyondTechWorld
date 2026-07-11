@extends('layout.main') @section('content')

    @if($errors->has('name'))
        <div class="alert alert-danger alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif

    <section>

        <div class="container-fluid">
            <div class="card">
                <div class="card-header mt-2">
                    <h3 class="text-center">Booked Products</h3>
                </div>
                <form action="{{route('booking.product.report')}}" method="post">
                    @csrf
                    <div class="row ">
                        <div class="col-md-4 offset-md-1 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>{{trans('file.Choose Your Date')}}</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <div class="input-group">
                                        <input type="text" class="daterangepicker-field form-control" value="{{$start_date}} To {{$end_date}}" required />
                                        <input type="hidden" name="start_date" value="{{$start_date}}" />
                                        <input type="hidden" name="end_date" value="{{$end_date}}" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Warehouse</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="warehouse_id" required class="selectpicker form-control" data-live-search="true" >
                                        <option value="0">All Warehouse</option>
                                        @foreach($lims_warehouse_list as $item)
                                            <option {{ @$warehouse_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-1 mt-3">
                            <div class="form-group">
                                <button class="btn btn-primary" type="submit">{{trans('file.submit')}}</button>
                            </div>
                        </div>
                        <div class="col-md-3 offset-md-1 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Status</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="status" class="form-control">
                                        <option value="">All</option>
                                        <option {{ @$status == '0' ? "selected" : "" }} value="0">Booked</option>
                                        <option {{ @$status == '1' ? "selected" : "" }} value="1">Return</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Products</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="products_id[]" class="selectpicker form-control" data-live-search="true" multiple>
                                        <option value="0">All Products</option>
                                        @foreach($lims_products_list as $item)
                                            @if(isset($products_id))
                                                <option {{ in_array($item->id, $products_id) ? 'selected' : '' }} value="{{$item->id}}">{{$item->name}}</option>
                                            @else
                                                <option value="{{$item->id}}">{{$item->name}} - {{$item->serial_no}}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
            </form>
            {{--            @if(in_array("booking_create", $all_permission))--}}
            {{--                <a href="{{route('booking.create')}}" class="btn btn-info"><i class="dripicons-plus"></i> Add Booking </a>--}}
            {{--            @endif--}}
        </div>
        <div class="table-responsive">
            <table id="role-table" class="table">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>Customer Name</th>
                    <th>{{trans('file.Image')}}</th>
                    <th>{{trans('file.name')}}</th>
                    <th>Quantity</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Book Time</th>
                    <th>Return Time</th>

                    @if(in_array("booking_return", $all_permission))
                        <th class="not-exported"><a href="#"></a></th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach($products as $key=>$item)
                    <tr data-id="{{$item->id}}">
                        <td>{{$key}}</td>
                        <td>{{@$item->booking->customer->name}}</td>
                        @if($item->product && $item->product->image)
                            <td> <img src="{{url('public/images/product',$item->product->image)}}" height="80" width="80"></td>
                        @else
                            <td>No Image</td>
                        @endif
                        <td>{{ $item->product ? $item->product->name : 'NAN' }}</td>
                        <td>{{ $item->qty }}</td>
                        <td>{{ number_format((float)$item->total, 2) }}</td>

                        @if($item->is_return == 1)
                            <td><span class="badge badge-danger">Return</span></td>
                        @else
                            @if(explode(' ', $item->end)[0] == date('Y-m-d'))
                                <td><span class="badge badge-info">Due For Return</span></td>
                            @else
                                <td><span class="badge badge-warning">Booked</span></td>
                            @endif
                        @endif
                        <td>{{ $item->start }}</td>
                        <td>{{ $item->end }}</td>
                        <td>
                            @if($item->is_return == 0)
                                @if(in_array("booking_return", $all_permission))
                                    <a href="{{ route('booking.return', $item->booking->id) }}" class="btn btn-warning"><i class="dripicons-return"></i> Return </a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>


    <script type="text/javascript">


        var warehouse_id = 1;
        $('.product-report-filter select[name="warehouse_id"]').val(warehouse_id);
        $('.selectpicker').selectpicker('refresh');

        $(".daterangepicker-field").daterangepicker({
            callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $(".product-report-filter input[name=start_date]").val(start_date);
                $(".product-report-filter input[name=end_date]").val(end_date);
            }
        });

        var start_date = $(".product-report-filter input[name=start_date]").val();
        var end_date = $(".product-report-filter input[name=end_date]").val();
        var warehouse_id = $(".product-report-filter select[name=warehouse_id]").val();


        $("ul#booking").siblings('a').attr('aria-expanded','true');
        $("ul#booking").addClass("show");
        $("ul#booking #booking-product-menu").addClass("active");

        $(document).ready(function() {
            $(document).on('click', '.open-EditroleDialog', function() {
                var url = "role/"
                var id = $(this).data('id').toString();
                url = url.concat(id).concat("/edit");

                $.get(url, function(data) {
                    $("input[name='name']").val(data['name']);
                    $("textarea[name='description']").val(data['description']);
                    $("input[name='role_id']").val(data['id']);
                });
            });

            $('#role-table').DataTable( {
                "order": [],
                'language': {
                    'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
                    "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                    "search":  '{{trans("file.Search")}}',
                    'paginate': {
                        'previous': '<i class="dripicons-chevron-left"></i>',
                        'next': '<i class="dripicons-chevron-right"></i>'
                    }
                },
                'columnDefs': [
                    {
                        "orderable": false,
                        'targets': [0, 3]
                    },
                    {
                        'render': function(data, type, row, meta){
                            if(type === 'display'){
                                data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                            }

                            return data;
                        },
                        'checkboxes': {
                            'selectRow': true,
                            'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                        },
                        'targets': [0]
                    }
                ],
                'select': { style: 'multi',  selector: 'td:first-child'},
                'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
                dom: '<"row"lfB>rtip',
                buttons: [
                    {
                        extend: 'pdf',
                        text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                        exportOptions: {
                            columns: ':visible:Not(.not-exported)',
                            rows: ':visible'
                        },
                    },
                    {
                        extend: 'csv',
                        text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                        exportOptions: {
                            columns: ':visible:Not(.not-exported)',
                            rows: ':visible'
                        },
                    },
                    {
                        extend: 'print',
                        text: '<i title="print" class="fa fa-print"></i>',
                        exportOptions: {
                            columns: ':visible:Not(.not-exported)',
                            rows: ':visible'
                        },
                    },
                    {
                        extend: 'colvis',
                        text: '<i title="column visibility" class="fa fa-eye"></i>',
                        columns: ':gt(0)'
                    },
                ],
            } );
        });
    </script>

@endsection
