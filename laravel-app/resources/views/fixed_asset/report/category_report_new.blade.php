@extends('layout.main') @section('content')
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    <section class="forms">
        <div class="container-fluid">
{{--            <a href="{{route('asset.report.category')}}" class="btn btn-sm btn-info"><i class="dripicons-list"></i> {{trans('file.Asset Category Report')}} </a>--}}
{{--            <a href="{{route('asset.report.department')}}" class="btn btn-sm btn-danger"><i class="dripicons-list"></i> {{trans('file.Asset Department Report')}} </a>--}}
{{--            <a href="{{route('asset.report.donor')}}" class="btn btn-sm btn-success"><i class="dripicons-list"></i> {{trans('file.Asset Donor Report')}} </a>--}}
{{--            <a href="{{route('asset.report.region')}}" class="btn btn-sm btn-warning"><i class="dripicons-list"></i> {{trans('file.Asset Region Report')}} </a>--}}
{{--            <a href="{{route('asset.report.station')}}" class="btn btn-sm btn-primary"><i class="dripicons-list"></i> {{trans('file.Asset Station Report')}} </a>--}}
{{--            <a href="{{route('asset.report.repair')}}" class="btn btn-sm btn-success"><i class="dripicons-list"></i> Repair Reort </a>--}}
{{--            <a href="{{route('asset.report.expense')}}" class="btn btn-sm btn-danger"><i class="dripicons-list"></i> Automobile Reort </a>--}}
{{--            <a href="{{route('asset.report.photocopy')}}" class="btn btn-sm btn-info"><i class="dripicons-list"></i> PhotoCopy Report </a>--}}
{{--            <a href="{{route('asset.report.general')}}" class="btn btn-sm btn-warning"><i class="dripicons-list"></i> General Report </a>--}}
{{--            <a href="{{route('asset.report.dispose')}}" class="btn btn-sm btn-primary"><i class="dripicons-list"></i> Disposal Report </a>--}}
{{--            <a href="{{route('asset.report.transfer')}}" class="btn btn-sm btn-success"><i class="dripicons-list"></i> Transfer Report </a>--}}
            <div class="card">
                <div class="card-header mt-2">
                    <h3 class="text-center">Asset Refined Category Report</h3>
                </div>
                <form action="{{route('asset.report.category.new')}}" method="post">
                    @csrf
                    <div class="row ">
                        @include('fixed_asset.report.options')
                        <div class="col-md-3 mt-3">
                            <div class="form-group row">
                                <label class="d-tc mt-2"><strong>Category</strong> &nbsp;</label>
                                <div class="d-tc">
                                    <select name="category_id" required class="selectpicker form-control" data-live-search="true" >
                                        <option value="0">All Category</option>
                                        @foreach($dataa as $item)
                                            @if(@$category_id == $item->id)
                                                @php $catValue = $item->name; @endphp
                                            @endif
                                            <option {{ @$category_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="cat-value" value="{{ @$catValue }}" name="category_value">
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
                    <tr>
                        <th>Asset Code</th>
                        <th>Asset Name</th>
                        <th>Manufacturer</th>
                        <th>Location</th>
                        <th>Person Responsible</th>
                        <th>Purchase Date</th>
                        <th>Service Date</th>
                        <th>Cost</th>
                        <th>Book Value</th>
                        <th>Depreciation Value</th>

                        <th>Reference (CDV/JE/ Special Cheque)</th>
                        <th>GL Code</th>
                        <th>Account Number</th>
                        <th>Asset Type</th>
                        <th>General Note</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php
                        $setting = \App\GeneralSetting::select('currency')->latest()->first();
                        $curency = '';
                        if($setting) {
                            $curency = \App\Currency::where('id', $setting->currency)->select('code')->first()->code;
                        }
                        $initial_value = 0;
                        $current_value = 0;
                        $depreciation_value = 0;
                        $count = 0;

                        $book_value_sum = 0;
                        $depreciation_value_sum = 0;
                        $price_sum = 0;
                    @endphp
                    @foreach($data as $key=>$item)
                        <tr data-id="{{$item->id}}">
                            <td>{{$item->serial_no}}</td>
                            <td>{{ $item->name }}</td>
                            <td>{{ $item->manufacturer }}</td>
                            <td>{{ $item->physical_location }}</td>
                            <td>{{ $item->manager }}</td>
                            <td>{{ $item->purchase_date }}</td>
                            <td>{{ $item->service_date }}</td>
                            <td>{{ number_format($item->price, 2) }}</td>
                            @php
                                $asset_calcultion = \App\Asset::depricationCaluculate($item);
                                    $depreciation = $asset_calcultion['depreciation'];
                                    $book_value = $asset_calcultion['book_value'];
                                    $available = $asset_calcultion['available'];
                                    $consume = $asset_calcultion['consume'];

                                    $count++;
                                    $book_value_sum += $book_value;
                                    $depreciation_value_sum += $depreciation;
                                    $price_sum += $item->price;
                            @endphp
                            <td>{{ number_format($book_value, 2) }}</td>
                            <td>{{ number_format($depreciation, 2) }}</td>
                            <td>{{ $item->serial_no }}</td>
                            <td>{{ $item->serial_no }}</td>
                            <td>{{ $item->serial_no }}</td>
                            <td>{{ $item->asset_type }}</td>
                            <td>{{ $item->remark }}</td>

                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr style="padding-top:10px ">
                        <td>Prepared By:</td>
                        <td>__________________</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>Checked By:</td>
                        <td>____________</td>
                    </tr>
                    </tfoot>
                </table>
            @endif
        </div>

    </section>
    @include('fixed_asset.report.footer_new')
@endsection
