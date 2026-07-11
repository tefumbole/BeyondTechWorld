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
                    <h3 class="text-center">Category Report</h3>
                </div>
                <form action="{{route('report.category.data')}}" method="post">
                    @csrf
                    <div class="row ">
                        <div class="col-md-4 offset-md-1 product-report-filter mt-3">
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
                                <label class="d-tc mt-2"><strong>Category</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="category_id" class="selectpicker form-control" data-live-search="true"  >
                                        <option value="0">All Categories</option>
                                        @foreach($all_categories as $item)
                                            <option {{ @$category_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mt-3">
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
                        <div class="col-md-2 mt-3">
                            <div class="form-group">
                                <button class="btn btn-primary" type="submit">{{trans('file.submit')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        @php
            $total = 0;
            $tax = 0;
            $qty = 0;
            $discount = 0;
            $totalExpense = 0;
            $totalProfit = 0;
        @endphp
        <div class="table-responsive">
            @if(isset($data))
            <table id="product-report-table" class="table table-hover" style="width: 100%">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Quantity</th>
                    <th>Expense</th>
                    <th>Tax</th>
                    <th>Discount</th>
                    <th>Total Sale</th>
                    <th>Profit</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lims_category_list as $item)
                    @php
                    if ($warehouse_id != 0) {
                        $productSale = \App\Product_Sale::selectRaw('sum(total) as total, sum(qty) as qty, sum(tax) as tax, sum(discount) as discount')->where('category_id', $item->id)->where('warehouse_id', $warehouse_id)->whereBetween('created_at', [$yesterday, $tomorrow])->first();
                        $expense = \App\Expense::where('category_id', $item->id)->where('warehouse_id', $warehouse_id)->whereBetween('created_at', [$yesterday, $tomorrow])->sum('amount');
                    } else {
                        $productSale = \App\Product_Sale::selectRaw('sum(total) as total, sum(qty) as qty, sum(tax) as tax, sum(discount) as discount')->where('category_id', $item->id)->whereBetween('created_at', [$yesterday, $tomorrow])->first();
                        $expense = \App\Expense::where('category_id', $item->id)->whereBetween('created_at', [$yesterday, $tomorrow])->sum('amount');
                    }

                    @endphp
                    <tr>
                        <td>{{$item->id}}</td>
                        <td>{{$item->name}}</td>
                        <td>{{$productSale->qty ?? 0}}</td>
                        <td>{{@$expense ?? 0}}</td>
                        <td>{{$productSale->tax ?? 0}}</td>
                        <td>{{$productSale->discount ?? 0}}</td>
                        <td>{{$productSale->total ?? 0}}</td>
                        <td>{{$profit = $productSale->total - @$expense}}</td>
                    </tr>
                    @php
                        $total += $item->total;
                        $totalExpense += $expense;
                        $totalProfit += $profit;
                        $qty += $item->qty;
                        $tax += $item->tax;
                        $discount += $item->discount;
                    @endphp
                @endforeach
                </tbody>

                <tfoot class="tfoot active">
                <th></th>
                <th>{{trans('file.Total')}}</th>
                <th>{{$qty}}</th>
                <th>{{$totalExpense}}</th>
                <th>{{$tax}}</th>
                <th>{{$discount}}</th>
                <th>{{$total}}</th>
                <th>{{$totalProfit}}</th>

                </tfoot>
            </table>
            @endif
            @if(isset($category_data))
                <table id="product-report-table" class="table table-hover" style="width: 100%">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Warehouse Name</th>
                        <th>Quantity</th>
                        <th>Tax</th>
                        <th>Discount</th>
                        <th>Total Sale</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($category_data as $item)
                        <tr>
                            <td>{{$item->id}}</td>
                            <td>{{$item->name}}</td>
                            @if(isset($item->w_name))
                            <td>{{$item->w_name}}</td>
                            @else
                            <td>unknown</td>
                            @endif
                            <td>{{$item->qty}}</td>
                            <td>{{$item->tax}}</td>
                            <td>{{$item->discount}}</td>
                            <td>{{$item->total}}</td>
                        </tr>
                        @php
                            $total += $item->total;
                            $tax += $item->tax;
                            $discount += $item->discount;
                            $qty += $item->qty;
                        @endphp
                    @endforeach
                    </tbody>

                    <tfoot class="tfoot active">
                    <th></th>
                    <th></th>
                    <th>{{trans('file.Total')}}</th>
                    <th>{{$qty}}</th>
                    <th>{{$tax}}</th>
                    <th>{{$discount}}</th>
                    <th>{{$total}}</th>
                    </tfoot>
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
        $("ul#report #category_active").addClass("active");

        $('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
        $('.selectpicker').selectpicker('refresh');

        $('#warehouse_id').on("change", function(){
            $('#report-form').submit();
        });
    </script>
@endsection
