@extends('layout.main') @section('content')

@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.General Setting')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'setting.generalStore', 'files' => true, 'method' => 'post']) !!}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.System Title')}} *</label>
                                        <input type="text" name="site_title" class="form-control" value="@if($lims_general_setting_data){{$lims_general_setting_data->site_title}}@endif" required />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.System Logo')}}</label>
                                        @if($lims_general_setting_data && $lims_general_setting_data->site_logo)
                                            <div class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-center" style="width:200px;height:80px;overflow:hidden;">
                                                <img src="{{url('public/logo', $lims_general_setting_data->site_logo)}}" alt="Current logo" style="max-height:100%;max-width:100%;width:auto;height:auto;object-fit:contain;">
                                            </div>
                                        @endif
                                        <input type="file" name="site_logo" class="form-control" accept="image/png,image/jpeg,image/gif"/>
                                        <small class="text-muted">PNG/JPG recommended. Auto-resized to fit (max 400×400). Transparent PNG works best.</small>
                                    </div>
                                    @if($errors->has('site_logo'))
                                   <span>
                                       <strong>{{ $errors->first('site_logo') }}</strong>
                                    </span>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Application Version</label>
                                        <input type="text" name="app_version" class="form-control" readonly
                                               value="{{ \App\Support\AppVersion::erp() }}"
                                               placeholder="e.g. BCL_ERP_V2.3.0">
                                        <small class="text-muted">Auto-updates as <code>BCL_ERP_V…</code> from <code>VERSION</code> on every commit/push and deploy (patch 0–9 → next minor; after 2.9 → 3.0.0). Not editable.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email / Invoice Header</label>
                                        @if($lims_general_setting_data && $lims_general_setting_data->email_header)
                                            <div class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-center" style="width:100%;max-width:420px;height:72px;overflow:hidden;">
                                                <img src="{{url('public/logo', $lims_general_setting_data->email_header)}}" alt="Current email header" style="max-height:100%;max-width:100%;width:auto;height:auto;object-fit:contain;">
                                            </div>
                                        @endif
                                        <input type="file" name="email_header" class="form-control" accept="image/png,image/jpeg,image/gif"/>
                                        <small class="text-muted">Wide letterhead band. Auto-resized to max 1400×240 so it fits on A4 invoices.</small>
                                    </div>
                                    @if($errors->has('email_header'))
                                        <span>
                                       <strong>{{ $errors->first('email_header') }}</strong>
                                    </span>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email / Invoice Footer</label>
                                        @if($lims_general_setting_data && $lims_general_setting_data->email_footer)
                                            <div class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-center" style="width:100%;max-width:420px;height:72px;overflow:hidden;">
                                                <img src="{{url('public/logo', $lims_general_setting_data->email_footer)}}" alt="Current email footer" style="max-height:100%;max-width:100%;width:auto;height:auto;object-fit:contain;">
                                            </div>
                                        @endif
                                        <input type="file" name="email_footer" class="form-control" accept="image/png,image/jpeg,image/gif"/>
                                        <small class="text-muted">Wide footer band. Auto-resized to max 1400×200.</small>
                                    </div>
                                    @if($errors->has('email_footer'))
                                        <span>
                                       <strong>{{ $errors->first('email_footer') }}</strong>
                                    </span>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email / Invoice Watermark</label>
                                        @if($lims_general_setting_data && $lims_general_setting_data->email_water_mark)
                                            <div class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-center" style="width:120px;height:120px;overflow:hidden;">
                                                <img src="{{url('public/logo', $lims_general_setting_data->email_water_mark)}}" alt="Current email water mark" style="max-height:100%;max-width:100%;width:auto;height:auto;object-fit:contain;">
                                            </div>
                                        @endif
                                        <input type="file" name="email_water_mark" class="form-control" accept="image/png,image/jpeg,image/gif"/>
                                        <small class="text-muted">Square logo works best. Auto-resized to max 800×800 (keeps PDFs small on WhatsApp).</small>
                                    </div>
                                    @if($errors->has('email_water_mark'))
                                        <span>
                                       <strong>{{ $errors->first('email_water_mark') }}</strong>
                                    </span>
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Currency')}} *</label>
                                        <select name="currency" class="form-control" required>
                                            @foreach($lims_currency_list as $key => $currency)
                                                @if($lims_general_setting_data->currency == $currency->id)
                                                    <option value="{{$currency->id}}" selected>{{$currency->name}}</option>
                                                @else
                                                    <option value="{{$currency->id}}">{{$currency->name}}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.default unit')}} *</label>
                                        <select name="unit" class="form-control" required>
                                            @foreach($lims_unit_list as $key => $unit)
                                                @if($lims_general_setting_data->unit == $unit->id)
                                                    <option value="{{$unit->id}}" selected>{{$unit->unit_name}}</option>
                                                @else
                                                    <option value="{{$unit->id}}">{{$unit->unit_name}}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.default category')}} *</label>
                                        <select name="category" class="form-control" required>
                                            @foreach($lims_category_list as $key => $category)
                                                @if($lims_general_setting_data->category == $category->id)
                                                    <option value="{{$category->id}}" selected>{{$category->name}}</option>
                                                @else
                                                    <option value="{{$category->id}}">{{$category->name}}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Default Warehouse')}}</label>
                                        <select name="default_warehouse_id" class="form-control">
                                            <option value="">No default (auto: warehouse with most items)</option>
                                            @foreach($lims_warehouse_list as $warehouse)
                                                <option value="{{$warehouse->id}}" @if($lims_general_setting_data && (int) $lims_general_setting_data->default_warehouse_id === (int) $warehouse->id) selected @endif>{{$warehouse->name}}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Used by POS and sales when no POS warehouse is configured.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Default Biller')}}</label>
                                        <select name="default_biller_id" class="form-control">
                                            <option value="">No default (auto: first active biller)</option>
                                            @foreach($lims_biller_list as $biller)
                                                <option value="{{$biller->id}}" @if($lims_general_setting_data && (int) $lims_general_setting_data->default_biller_id === (int) $biller->id) selected @endif>{{$biller->name}}</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Used by POS and sales when no POS biller is configured.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Currency Position')}} *</label><br>
                                        @if($lims_general_setting_data->currency_position == 'prefix')
                                        <label class="radio-inline">
                                            <input type="radio" name="currency_position" value="prefix" checked> {{trans('file.Prefix')}}
                                        </label>
                                        <label class="radio-inline">
                                          <input type="radio" name="currency_position" value="suffix"> {{trans('file.Suffix')}}
                                        </label>
                                        @else
                                        <label class="radio-inline">
                                            <input type="radio" name="currency_position" value="prefix"> {{trans('file.Prefix')}}
                                        </label>
                                        <label class="radio-inline">
                                          <input type="radio" name="currency_position" value="suffix" checked> {{trans('file.Suffix')}}
                                        </label>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Time Zone')}}</label>
                                        @if($lims_general_setting_data)
                                        <input type="hidden" name="timezone_hidden" value="{{env('APP_TIMEZONE')}}">
                                        @endif
                                        <select name="timezone" class="selectpicker form-control" data-live-search="true" title="Select TimeZone...">
                                            @foreach($zones_array as $zone)
                                            <option value="{{$zone['zone']}}">{{$zone['diff_from_GMT'] . ' - ' . $zone['zone']}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 d-none">
                                    <div class="form-group">
                                        <label>{{trans('file.Theme')}} *</label>
                                        <div class="row ml-1">
                                            <div class="col-md-3 theme-option" data-color="default.css" style="background: #7c5cc4; min-height: 40px; max-width: 50px;" title="Purple"></div>&nbsp;&nbsp;
                                            <div class="col-md-3 theme-option" data-color="green.css" style="background: #1abc9c; min-height: 40px;max-width: 50px;" title="Green"></div>&nbsp;&nbsp;
                                            <div class="col-md-3 theme-option" data-color="blue.css" style="background: #3498db; min-height: 40px;max-width: 50px;" title="Blue"></div>&nbsp;&nbsp;
                                            <div class="col-md-3 theme-option" data-color="dark.css" style="background: #34495e; min-height: 40px;max-width: 50px;" title="Dark"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Staff Access')}} *</label>
                                        @if($lims_general_setting_data)
                                        <input type="hidden" name="staff_access_hidden" value="{{$lims_general_setting_data->staff_access}}">
                                        @endif
                                        <select name="staff_access" class="selectpicker form-control">
                                            <option value="all"> {{trans('file.All Records')}}</option>
                                            <option value="own"> {{trans('file.Own Records')}}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Invoice Format')}} *</label>
                                        @if($lims_general_setting_data)
                                        <input type="hidden" name="invoice_format_hidden" value="{{$lims_general_setting_data->invoice_format}}">
                                        @endif
                                        <select name="invoice_format" class="selectpicker form-control" required>
                                            <option value="standard">Standard</option>
                                            <option value="gst">GST</option>
                                            <option value="beyond_a4">Beyond A4</option>
                                            <option value="mini">Beyond Mini Receipt</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="state" class="col-md-6 d-none">
                                    <div class="form-group">
                                        <label>{{trans('file.State')}} *</label>
                                        @if($lims_general_setting_data)
                                        <input type="hidden" name="state_hidden" value="{{$lims_general_setting_data->state}}">
                                        @endif
                                        <select name="state" class="selectpicker form-control">
                                            <option value="1">Home State</option>
                                            <option value="2">Buyer State</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Date Format')}} *</label>
                                        @if($lims_general_setting_data)
                                        <input type="hidden" name="date_format_hidden" value="{{$lims_general_setting_data->date_format}}">
                                        @endif
                                        <select name="date_format" class="selectpicker form-control">
                                            <option value="d-m-Y"> dd-mm-yyy</option>
                                            <option value="d/m/Y"> dd/mm/yyy</option>
                                            <option value="d.m.Y"> dd.mm.yyy</option>
                                            <option value="m-d-Y"> mm-dd-yyy</option>
                                            <option value="m/d/Y"> mm/dd/yyy</option>
                                            <option value="m.d.Y"> mm.dd.yyy</option>
                                            <option value="Y-m-d"> yyy-mm-dd</option>
                                            <option value="Y/m/d"> yyy/mm/dd</option>
                                            <option value="Y.m.d"> yyy.mm.dd</option>
                                        </select>
                                    </div>
                                </div>
                                @if(in_array("developed_by", $all_permission))
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Developed By')}}</label>
                                            <input type="text" name="developed_by" class="form-control" value="{{$lims_general_setting_data->developed_by}}">
                                        </div>
                                    </div>
                                @else
                                    <input type="hidden" name="developed_by" class="form-control" value="{{$lims_general_setting_data->developed_by}}">
                                @endif
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Letter Serial No.')}}</label>
                                        <input type="text" name="letter_serial_no" class="form-control" value="{{$lims_general_setting_data->letter_serial_no}}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.profit percentage')}}</label>
                                        <input type="text" name="profit_percentage" class="form-control" value="{{$lims_general_setting_data->profit_percentage}}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Admin Commission Percentage</label>
                                        <input type="text" name="commission" class="form-control" value="{{$lims_general_setting_data->commission}}">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                            </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #general-setting-menu").addClass("active");

    $("select[name=invoice_format]").on("change", function (argument) {
        if($(this).val() == 'standard') {
            $("#state").addClass('d-none');
            $("input[name=state]").prop("required", false);
        }
        else if($(this).val() == 'gst') {
            $("#state").removeClass('d-none');
            $("input[name=state]").prop("required", true);
        }
    })
    if($("input[name='timezone_hidden']").val()){
        $('select[name=timezone]').val($("input[name='timezone_hidden']").val());
        $('select[name=staff_access]').val($("input[name='staff_access_hidden']").val());
        $('select[name=date_format]').val($("input[name='date_format_hidden']").val());
        $('select[name=invoice_format]').val($("input[name='invoice_format_hidden']").val());
        if($("input[name='invoice_format_hidden']").val() == 'gst') {
            $('select[name=state]').val($("input[name='state_hidden']").val());
            $("#state").removeClass('d-none');
        }
        $('.selectpicker').selectpicker('refresh');
    }

    $('.theme-option').on('click', function() {
        $.get('general_setting/change-theme/' + $(this).data('color'), function(data) {
        });
        var style_link= $('#custom-style').attr('href').replace(/([^-]*)$/, $(this).data('color') );
        $('#custom-style').attr('href', style_link);
    });


</script>
@endsection
