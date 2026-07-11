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
                    <h3 class="text-center">{{trans('file.JE Report')}}</h3>
                </div>
                <form action="{{route('report.JEData')}}" method="post">
                    @csrf
                    <div class="row ">
                        <div class="col-md-3 offset-md-1 text-center product-report-filter mt-3">
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
                        <div class="col-md-3 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>{{trans('file.Prepared By')}}</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <div class="input-group">
                                        <input type="text" name="prepared" value="{{@$prepared}}" class="form-control" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>{{trans('file.Station Code')}}</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <div class="input-group">
                                        <input type="text" name="code" value="{{@$code}}" class="form-control" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 offset-md-1 text-center product-report-filter mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>{{trans('file.Initial page')}}</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <div class="input-group">
                                        <input type="text" name="page" value="{{@$page}}" class="form-control" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Payment method</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="payment" required class="selectpicker form-control" data-live-search="true" >
                                        <option value="0" {{ @$payment == 0 ? 'selected' : '' }}>Cash & JE</option>
                                        <option value="1" {{ @$payment == 1 ? 'selected' : '' }}>Cash</option>
                                        <option value="2" {{ @$payment == 2 ? 'selected' : '' }}>JE</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-1 mt-3">
                            <div class="form-group">
                                <button class="btn btn-primary" type="submit">{{trans('file.submit')}}</button>
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
{{--                    <tr>--}}
{{--                        <th>code:</th>--}}
{{--                        <th>H</th>--}}
{{--                        <th></th>--}}
{{--                        <th></th>--}}
{{--                        <th></th>--}}
{{--                        <th>{{$code}}</th>--}}
{{--                        <th>a</th>--}}
{{--                        <th><i>preparer's</i></th>--}}
{{--                    </tr>--}}
                    <tr>
                        <th>line</th>
                        <th>date</th>
                        <th>reference</th>
                        <th>account - dpt</th>
                        <th>description</th>
                        <th>debit</th>
                        <th>credit</th>
                        <th>initials</th>
                    </tr>
                    </thead>
                    @php
                    $line = 1;
                    $page = $page ?? 0;
                    $credit = 0;
                    $debitAmount = 0;
                    @endphp
                    <tbody>
                    @foreach($data as $item)
                        @php
                        if($item->accounts->id) {
                            $department = \App\Department::where('id', $item->accounts->department_id)->first();
                        }
                        $nameArray = explode(" ", $prepared);
                        $name = '';
                        foreach ($nameArray as $nameItem) {
                            $name .= substr("$nameItem", 0, 1);
                        }
                        @endphp
                        <tr>
                            <td>{{$line}}</td>
                            <td>{{$item->created_at->format('d-M-Y')}}</td>
                            <td>{{$code.$page.'a'.$line}}</td>
                            <td>{{@$item->accounts->account_no }} - {{$department->code}}</td>
                            <td>{{$item->payment_note}}</td>
                            @if($item->purchase_id != null OR $item->debit_sale_id != null)
                                <td>{{ $item->amount }}</td>
                                @php $debitAmount += $item->amount; @endphp
                            @else
                                <td></td>
                            @endif
                            @if($item->sale_id != null)
                                <td>{{$item->amount}}</td>
                                @php $credit += $item->amount; @endphp
                            @else
                                <td></td>
                            @endif
                            <td>{{ $name }}</td>
                        </tr>
                        @php
                            $line++;
                        @endphp
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>Prepared By</td>
                            <td>___________</td>
                            <td style="text-align:right">Check / </td>
                            <td>Approved By</td>
                            <td>___________</td>
                            <td>{{ $debitAmount }}</td>
                            <td>{{ $credit }}</td>
                            <td></td>
                        </tr>
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
        var titlePrint = "BEYOND COMPANY LTD \n Initial page: {{ @$page }}      Prepared by: {{ @$prepared }}      Station Code: {{ @$code }}"
        $('#product-report-table').DataTable( {
            "processing": false,
            "serverSide": false,

            dom: '<"row"lfB>rtip',
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
            buttons: [
                {
                    extend: 'pdf',
                    title: titlePrint,
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
                    title: titlePrint,
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
                    title: titlePrint,
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
        $("ul#report #JE-menu").addClass("active");

        $('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
        $('.selectpicker').selectpicker('refresh');

        $('#warehouse_id').on("change", function(){
            $('#report-form').submit();
        });
    </script>
@endsection
