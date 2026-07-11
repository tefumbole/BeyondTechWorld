@extends('layout.main') @section('content')
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    <section class="forms">
        <div class="container-fluid">
            <a href="{{route('asset.report.category')}}" class="btn btn-sm btn-info"><i class="dripicons-list"></i> {{trans('file.Asset Category Report')}} </a>
            <a href="{{route('asset.report.department')}}" class="btn btn-sm btn-danger"><i class="dripicons-list"></i> {{trans('file.Asset Department Report')}} </a>
            <a href="{{route('asset.report.donor')}}" class="btn btn-sm btn-success"><i class="dripicons-list"></i> {{trans('file.Asset Donor Report')}} </a>
            <a href="{{route('asset.report.region')}}" class="btn btn-sm btn-warning"><i class="dripicons-list"></i> {{trans('file.Asset Region Report')}} </a>
            <a href="{{route('asset.report.station')}}" class="btn btn-sm btn-primary"><i class="dripicons-list"></i> {{trans('file.Asset Station Report')}} </a>
            <a href="{{route('asset.report.repair')}}" class="btn btn-sm btn-success"><i class="dripicons-list"></i> Repair Reort </a>
            <a href="{{route('asset.report.expense')}}" class="btn btn-sm btn-danger"><i class="dripicons-list"></i> Automobile Reort </a>
            <a href="{{route('asset.report.photocopy')}}" class="btn btn-sm btn-info"><i class="dripicons-list"></i> PhotoCopy Report </a>
            <a href="{{route('asset.report.general')}}" class="btn btn-sm btn-warning"><i class="dripicons-list"></i> General Report </a>
            <a href="{{route('asset.report.dispose')}}" class="btn btn-sm btn-primary"><i class="dripicons-list"></i> Disposal Report </a>
            <a href="{{route('asset.report.transfer')}}" class="btn btn-sm btn-success"><i class="dripicons-list"></i> Transfer Report </a>
            <div class="card">
                <div class="card-header mt-2">
                    <h3 class="text-center">Repair Report</h3>
                </div>
                <form action="{{route('asset.report.repair')}}" method="post">
                    @csrf
                    @include('fixed_asset.report.form_options')
                </form>
            </div>
        </div>
        <div class="table-responsive">
            @if(isset($data))
                <table id="product-report-table" class="table table-hover" style="width: 100%">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Barcode</th>
                        <th>{{trans('file.name')}}</th>
                        <th>Expense</th>
                        <th>Income</th>
                        <th>profit</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $setting = \App\GeneralSetting::select('currency')->latest()->first();
                        $curency = '';
                        if($setting) {
                            $curency = \App\Currency::where('id', $setting->currency)->select('code')->first()->code;
                        }
                        $total_expense = 0;
                        $total_income = 0;
                        $total_profit = 0;

                    @endphp
                    @foreach($data as $key=>$item)
                        @php
                            $expense = \App\AssetExpense::where('asset_id', $item->asset_id)->whereBetween('asset_expenses.date', [$yesterday, $tomorrow])->where('type', 'expense')->sum('amount');
                            $income = \App\AssetExpense::where('asset_id', $item->asset_id)->whereBetween('asset_expenses.date', [$yesterday, $tomorrow])->where('activity_type', 'repair')->where('type', 'activity')->sum('amount');
                            $f_asset = \App\Asset::where('id', $item->asset_id)->select('name', 'serial_no')->first();
                            @endphp
                        <tr>
                            <td></td>
                            <td>{{$f_asset->serial_no}}</td>
                            <td>{{$f_asset->name}}</td>
                            <td>{{$expense}}</td>
                            <td>{{$income}}</td>
                            <td>{{$income - $expense}}</td>
                            <td></td>

                        </tr>
                        @php
                            $total_expense += $expense;
                            $total_income += $income;
                            $total_profit += $income - $expense;
                        @endphp
                    @endforeach

                    </tbody>
                    <tfoot>
                    <tr style="padding-top:10px ">
                        <td></td>
                        <td></td>
                        <td>Total</td>
                        <td>{{ $total_expense }}</td>
                        <td>{{ $total_income }}</td>
                        <td>{{ $total_profit }}</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </section>
    @include('fixed_asset.report.footer3')
    <script>

        $(".category").hide();
        $(".department").hide();
        $(".donor").hide();
        $(".station").hide();
        $(".region").hide();

        @if(isset($filter_id))
            @if($filter_id == 'Region')
            $(".region").show();
            @elseif($filter_id == 'Category')
            $(".category").show();
            @elseif($filter_id == 'Station')
            $(".station").show();
            @elseif($filter_id == 'Donor')
            $(".donor").show();
            @elseif($filter_id =='Department')
            $(".department").show();
            @endif
        @endif

        $('#filter').on('change', function() {
            if ($(this).val() == 'Category') {
                $(".category").show(300);
                $(".department").hide();
                $(".donor").hide();
                $(".station").hide();
                $(".region").hide();
            } else if ($(this).val() == 'Department') {
                $(".category").hide();
                $(".department").show(300);
                $(".donor").hide();
                $(".station").hide();
                $(".region").hide();
            } else if ($(this).val() == 'Donor') {
                $(".category").hide();
                $(".department").hide();
                $(".donor").show(300);
                $(".station").hide();
                $(".region").hide();
            } else if ($(this).val() == 'Station') {
                $(".category").hide();
                $(".department").hide();
                $(".donor").hide();
                $(".station").show(300);
                $(".region").hide();
            } else if ($(this).val() == 'Region') {
                $(".category").hide();
                $(".department").hide();
                $(".donor").hide();
                $(".station").hide();
                $(".region").show(300);
            } else {
                $(".category").hide();
                $(".department").hide();
                $(".donor").hide();
                $(".station").hide();
                $(".region").hide();
            }
        });
    </script>
@endsection
