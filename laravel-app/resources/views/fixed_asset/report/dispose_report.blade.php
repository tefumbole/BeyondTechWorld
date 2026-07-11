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
                    <h3 class="text-center">Disposal Report</h3>
                </div>
                <form action="{{route('asset.report.dispose')}}" method="post">
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
                        <th>Barcode</th>
                        <th>{{trans('file.name')}}</th>
                        <th>Price</th>
                        <th>Method</th>
                        <th>Remarks</th>
                        <th>Date</th>
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

                    @endphp
                    @foreach($data as $key=>$item)
                        <tr data-id="{{$item->id}}">
                            <td>{{$item->serial_no}}</td>
                            <td>{{$item->name}}</td>
                            <td>{{$item->price}}</td>
                            <td>{{ $item->method == 'Others' ? $item->other : $item->method}}</td>
                            <td>{{ $item->remarks}}</td>
                            <td>{{ $item->date}}</td>
                        </tr>
                        @php
                            $total_expense += $item->price;
                        @endphp
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr style="padding-top:10px ">
                        <td>Prepared By:</td>
                        <td>_____________</td>
                        <td>{{ $total_expense }}</td>
                        <td>Checked By:</td>
                        <td>____________</td>
                    </tr>
                    </tfoot>
                </table>
            @endif
        </div>
    </section>
    @include('fixed_asset.report.footer')
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
