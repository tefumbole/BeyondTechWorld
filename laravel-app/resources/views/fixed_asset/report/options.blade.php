<div class="col-md-5 text-center product-report-filter mt-3 pl-5">
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
        <label class="d-tc mt-2"><strong>Filter</strong> &nbsp;</label>
        <div class="d-tc">
            <select name="filter_id" class="selectpicker form-control" data-live-search="true" >
                <option value="0" {{ @$filter_id == 0 ? "selected" : "" }}>Registery Base</option>
                <option value="1" {{ @$filter_id == 1 ? "selected" : "" }}>Purchase Base</option>
                <option value="2" {{ @$filter_id == 2 ? "selected" : "" }}>Deprication Base</option>
                <option value="3" {{ @$filter_id == 3 ? "selected" : "" }}>First Use Base</option>
            </select>
        </div>
    </div>
</div>
