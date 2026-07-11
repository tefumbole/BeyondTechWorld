@extends('layout.main') @section('content')
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    <style>
        /*.table-responsive {*/
        /*    margin-left: 100px;*/
        /*}*/
    </style>
    <section class="forms">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header mt-2">
                    <h3 class="text-center">{{trans('file.Average Report')}}</h3>
                </div>
                <form action="{{route('report.average.sale')}}" method="post">
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
                        <div class="col-md-10 offset-md-1 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Products</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="products_id[]" class="selectpicker form-control" data-live-search="true" multiple>
                                        <option value="0">All Products</option>
                                        @foreach($lims_products_list as $item)
                                            <option value="{{$item->id}}">{{$item->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>
            </form>
        </div>
        </div>
        <div class="table-responsive">
            @if(isset($data))
                <table id="product-report-table" class="table table-hover" style="width: 100%">
                    <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Product Name</th>
                        <th>AMC</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data as $item)
                        @php
                            $out_of_stock = App\StockDuration::where('product_id', $item->product_id)->whereBetween('out_of_stock', [$start_date, $end_date])->get();
                            $product_data = App\Product::where('id', $item->product_id)->first();
                            $out = 0;
                            if(@$product_data->type != 'digital') {
                                foreach ($out_of_stock as $product) {
                                    $interval = strtotime($product->restock) - strtotime($product->out_of_stock);
                                    $out += ($interval/3600/24) - 1;
                                }
                                $total_days = $diff_date - $out;
                            } else {
                                $total_days = $diff_date;
                            }
                            $average = round(($item->qty / $total_days) * 30, 2);
                        @endphp
                        <tr>
                            <td>{{$item->product_id}}</td>
                            <td>{{@$product_data->name}}</td>
                            <td class="{{$item->qty}} , {{$total_days}}">{{$average}}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
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
        $('#product-report-table').DataTable( {
            "processing": false,
            "serverSide": false,

            dom: '<"row"lfB>rtip',
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
            buttons: [
                {
                    extend: 'pdf',
                    text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                    },
                    footer:true
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                    },
                    footer:true
                },
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:not(.not-exported)',
                        rows: ':visible'
                    },
                    action: function(e, dt, button, config) {
                        $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                    },
                    footer:true
                },
                {
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
            drawCallback: function () {
                var api = this.api();
            }
        } );

    </script>
    <script type="text/javascript">

        $("ul#report").siblings('a').attr('aria-expanded','true');
        $("ul#report").addClass("show");
        $("ul#report #average_sale_active-menu").addClass("active");

        $('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
        $('.selectpicker').selectpicker('refresh');

        $('#warehouse_id').on("change", function(){
            $('#report-form').submit();
        });
    </script>
@endsection
