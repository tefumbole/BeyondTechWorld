<div class="row ">
    <div class="col-md-6 text-center product-report-filter mt-3 pl-5">
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
    {{--                        assets--}}
    <div class="col-md-6 mt-3">
        <div class="form-group row">
            <label class="d-tc mt-2"><strong>Assets</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="asset_id[]" class="selectpicker form-control" data-live-search="true" multiple>
                    <option value="0">No Assets</option>
                    @foreach($assets as $item)
                        @if(isset($asset_id))
                            <option {{ in_array($item->id, $asset_id) ? 'selected' : '' }} value="{{$item->id}}">{{$item->name}}</option>
                        @else
                            <option value="{{$item->id}}">{{$item->name}} - {{$item->serial_no}}</option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    <div class="col-md-5 pl-5 mt-3">
        <div class="form-group row">
            <label class="d-tc mt-2"><strong>Filter</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="filter_id" id="filter" class="selectpicker form-control" data-live-search="true" >
                    <option value="Date" {{ @$filter_id == "Date" ? "selected" : "" }}>Date Base</option>
                    <option value="Donor" {{ @$filter_id == "Donor" ? "selected" : "" }}>Donor Base</option>
                    <option value="Station" {{ @$filter_id == "Station" ? "selected" : "" }}>Station Base</option>
                    <option value="Region" {{ @$filter_id == "Region" ? "selected" : "" }}>Region Base</option>
                    <option value="Department" {{ @$filter_id == "Department" ? "selected" : "" }}>Department Base</option>
                    <option value="Category" {{ @$filter_id == "Category" ? "selected" : "" }}>Category Base</option>
                </select>
            </div>
        </div>
    </div>
    {{--                        station--}}
    <div class="col-md-5 mt-3 station">
        <div class="form-group row">
            <label class="d-tc mt-2"><strong>Station</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="station_id" required class="selectpicker form-control" data-live-search="true" >
                    <option value="0">No Station</option>
                    @foreach($station as $item)
                        <option {{ @$station_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    {{--                        category--}}
    <div class="col-md-5 mt-3 category">
        <div class="form-group row">
            <label class="d-tc mt-2"><strong>Category</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="category_id" required class="selectpicker form-control" data-live-search="true" >
                    <option value="0">No Category</option>
                    @foreach($assetCategory as $item)
                        <option {{ @$category_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    {{--                        region--}}
    <div class="col-md-5 mt-3 region">
        <div class="form-group row ">
            <label class="d-tc mt-2"><strong>Region</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="region_id" required class="selectpicker form-control" data-live-search="true" >
                    <option value="0">No Region</option>
                    @foreach($region as $item)
                        <option {{ @$region_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    {{--                        department--}}
    <div class="col-md-5 mt-3 department">
        <div class="form-group row">
            <label class="d-tc mt-2"><strong>Department</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="department_id" required class="selectpicker form-control" data-live-search="true" >
                    <option value="0">No Department</option>
                    @foreach($department as $item)
                        <option {{ @$department_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}} / {{$item->code}}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    {{--                        donor--}}
    <div class="col-md-5 mt-3 donor">
        <div class="form-group row">
            <label class="d-tc mt-2"><strong>Donor</strong> &nbsp;</label>
            <div class="d-tc">
                <select name="donor_id" required class="selectpicker form-control" data-live-search="true" >
                    <option value="0">No Donor</option>
                    @foreach($donor as $item)
                        <option {{ @$donor_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name}}</option>
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
