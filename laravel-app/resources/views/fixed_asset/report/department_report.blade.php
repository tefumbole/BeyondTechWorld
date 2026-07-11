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
                    <h3 class="text-center">{{trans('file.Asset Department Report')}}</h3>
                </div>
                <form action="{{route('asset.report.department')}}" method="post">
                    @csrf
                    <div class="row ">
                        @include('fixed_asset.report.options')
                        <div class="col-md-3 mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Department</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="department_id" required class="selectpicker form-control" data-live-search="true" >
                                        <option value="0">All Department</option>
                                        @foreach($dataa as $item)
                                            <option {{ @$department_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}} / {{$item->code}}</option>
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

                    </div>
                </form>
            </div>
        </div>
        @include('fixed_asset.report.result')
    </section>
    @include('fixed_asset.report.footer')
@endsection
