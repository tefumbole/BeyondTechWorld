@extends('layout.main') @section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if(in_array("asset-index", $all_permission))
                    <a href="{{route('asset.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Assets List')}} </a>
                @endif
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Add Assets')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => ['asset.update', $data->id], 'method' => 'put', 'files' => true]) !!}
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.name')}} *</strong> </label>
                                    <input type="text" name="name" value="{{ $data->name }}" required class="form-control">
                                </div>
                            </div>
{{--                            <div class="col-md-3">--}}
{{--                                <div class="form-group">--}}
{{--                                    <label>Person Incharge</label>--}}
{{--                                    <input type="text" name="manager" value="{{ $data->manager }}" class="form-control" placeholder="Person Incharge">--}}
{{--                                </div>--}}
{{--                            </div>--}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Assign To</label>
                                    <input type="text" name="Assign_to" value="{{ $data->Assign_to }}" class="form-control" placeholder="Assign To">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Assets Form</label>
                                    <select name="asset_type" class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        <option value="software" {{ $data->asset_type == 'software' ? 'selected' : '' }}>SOFTWARE</option>
                                        <option value="land" {{ $data->asset_type == 'land' ? 'selected' : '' }}>LAND</option>
                                        <option value="house" {{ $data->asset_type == 'house' ? 'selected' : '' }}>BUILDINGS, FIXTURE & FITTINGS</option>
                                        <option value="mediacal-equipment" {{ $data->asset_type == 'mediacal-equipment' ? 'selected' : '' }}>EQUIPMENT</option>
                                        <option value="furniture" {{ $data->asset_type == 'furniture' ? 'selected' : '' }}>FURNITURE</option>
                                        <option value="vehicle" {{ $data->asset_type == 'vehicle' ? 'selected' : '' }}>TRANSPORT EQUIPMENT</option>
                                        <option value="tvs" {{ $data->asset_type == 'tvs' ? 'selected' : '' }}>TVs</option>
                                        <option value="computer" {{ $data->asset_type == 'computer' ? 'selected' : '' }}>COMPUTERS</option>
                                        <option value="general-electronics" {{ $data->asset_type == 'general-electronics' ? 'selected' : '' }}>GENERAL</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Type')}}</label>
                                    <select name="category_id" class="form-control">
                                        <option value="">  -- choose -- </option>
                                        @foreach($category as $item)
                                            <option value="{{ $item->id }}" {{ $data->category_id == $item->id ? 'selected' : '' }}>  {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Assets Department *</label>
                                    <select name="department_id" id="department_id" required class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        @foreach($department as $item)
                                            <option value="{{ $item->id }}"{{ $data->department_id == $item->id ? 'selected' : '' }}> {{ $item->name }} / {{ $item->code }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Donor')}}</label>
                                    <select name="donor_id" class="form-control">
                                        <option value="">  -- choose -- </option>
                                        @foreach($donor as $item)
                                            <option value="{{ $item->id }}" {{ $data->donor_id == $item->id ? 'selected' : '' }}>  {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Region')}}</label>
                                    <select name="region_id" class="form-control">
                                        <option value="">  -- choose -- </option>
                                        @foreach($region as $item)
                                            <option value="{{ $item->id }}" {{ $data->region_id == $item->id ? 'selected' : '' }}>  {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Station')}}</label>
                                    <select name="station_id" class="form-control">
                                        <option value="">  -- choose -- </option>
                                        @foreach($station as $item)
                                            <option value="{{ $item->id }}" {{ $data->station_id == $item->id ? 'selected' : '' }}> {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Bare Code</label>
                                    <input type="text" name="serial_no" id="serial" value="{{ $data->serial_no }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Scrap Value</label>
                                    <input type="number" name="scrap" value="{{ $data->scrap }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3 land">
                                <div class="form-group">
                                    <label>Appreciation Value</label>
                                    <input type="number" name="appreciation" value="{{ $data->appreciation }}" placeholder="Appreciation Value" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Product Image')}}</strong> </label> <i class="dripicons-question" data-toggle="tooltip" title="{{trans('file.You can upload multiple image. Only .jpeg, .jpg, .png, .gif file can be uploaded. First image will be base image.')}}"></i>
                                    <br>
                                    <input type="file" name="image[]" class="form-control" multiple id="imageUpload"></input>
                                    <span class="validation-msg" id="image-error"></span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <table class="table table-hover">
                                        <thead>
                                        <tr>
                                            <th><button type="button" class="btn btn-sm"><i class="fa fa-trash"></i></button></th>
                                            <th>Image</th>
                                            <th>Remove</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <tr>
                                            <td><button type="button" class="btn btn-sm"><i class="fa fa-trash"></i></button></i></td>
                                            <td>
                                                <img src="{{url('public/images/assets', $data->image)}}" height="60" width="60">
                                            </td>
                                            <td>Default image</td>
                                        </tr>
                                        <?php $images = \App\ImageLibrary::where('asset_id', $data->id)->get(); ?>
                                        @foreach($images as $key => $image)
                                            <tr>
                                                <td><button type="button" class="btn btn-sm"><i class="fa fa-trash"></i></button></i></td>
                                                <td>
                                                    <img src="{{url('public/images/assets', $image->image)}}" height="60" width="60">
                                                </td>
                                                <td><a href="{{route('asset.image.delete', ['id' => $image->id])}}" class="btn btn-sm btn-danger remove-img">X</a></td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 computer-tv">
                                <div class="form-group">
                                    <label>manufacturer</label>
                                    <input type="text" name="manufacturer" value="{{ $data->manufacturer }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Set Type</label>
                                    <input type="text" name="set_type" value="{{ $data->set_type }}" class="form-control" placeholder="Set Type">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Depreciation Method</label>
                                    <select name="depreciation_type" class="form-control">
                                        <option value="fixed" {{ $data->depreciation_type == 'fixed' ? 'selected' : '' }}>Fixed</option>
                                        <option value="starigh_line" {{ $data->depreciation_type == 'starigh_line' ? 'selected' : '' }}>Starigh Line</option>
                                        <option value="declining_balance" {{ $data->depreciation_type == 'declining_balance' ? 'selected' : '' }}>Declining Balance</option>
                                        <option value="double_declining_balance" {{ $data->depreciation_type == 'double_declining_balance' ? 'selected' : '' }}>Double Declining Balance</option>
                                        <option value="sum_of_the_years_digits" {{ $data->depreciation_type == 'sum_of_the_years_digits' ? 'selected' : '' }}>Sum of the Years Digits</option>
                                        <option value="units_of_production" {{ $data->depreciation_type == 'units_of_production' ? 'selected' : '' }}>Units of Production</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Remark</label>
                                    <input type="text" name="remark" value="{{ $data->remark }}" class="form-control" placeholder="Remark">
                                </div>
                            </div>
                            <div class="col-md-3 computer-tv">
                                <div class="form-group">
                                    <label>Model</label>
                                    <input type="text" name="model" value="{{ $data->model }}" class="form-control">
                                </div>
                            </div>
{{--                            <div class="col-md-3">--}}
{{--                                <div class="form-group">--}}
{{--                                    <label>Serial</label>--}}
{{--                                    <input type="text" name="serial" value="{{ $data->serial }}" class="form-control">--}}
{{--                                </div>--}}
{{--                            </div>--}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Price</label>
                                    <input type="number" name="price" value="{{ $data->price }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Physical Location</label>
                                    <input type="text" name="physical_location" value="{{ $data->physical_location }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.City')}}</label>
                                    <input type="text" name="city" value="{{ $data->city }}" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Purchase Date</label>
                                    <input type="date" id="purchase_date" name="purchase_date" value="{{ $data->purchase_date }}" class="form-control date">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Service Date</label>
                                    <input type="date" id="service_date" name="service_date" value="{{ $data->service_date }}" class="form-control date">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Useful Life in Years</label>
                                    <input type="number" name="life_span" value="{{ $data->life_span }}" class="form-control">
                                </div>
                            </div>


                            {{--vehicles--}}
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Manager</label>
                                    <input type="text" name="manager" class="form-control" placeholder="Manager">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Driver</label>
                                    <input type="text" name="driver" value="{{ $data->driver }}" class="form-control" placeholder="Driver">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Engine Type</label>
                                    <select name="engine_type" class="form-control">
                                        <option value="">-- choose --</option>
                                        <option value="Super" {{ $data->engine_type == 'Super' ? 'selected' : '' }}>Super</option>
                                        <option value="Gas"> {{ $data->engine_type == 'Gas' ? 'selected' : '' }}Gas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Number of Seats</label>
                                    <input type="number" name="number_of_Seats" value="{{ $data->number_of_Seats }}" class="form-control" placeholder="Number of Seats">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Horse Power</label>
                                    <input type="text" name="horse_power" value="{{ $data->horse_power }}" class="form-control" placeholder="Horse Power">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Milage at Purchase</label>
                                    <input type="text" name="milage_at_purchase" value="{{ $data->milage_at_purchase }}" class="form-control" placeholder="Milage at Purchase">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Chassi Number</label>
                                    <input type="text" name="chassi_number" value="{{ $data->chassi_number }}" class="form-control" placeholder="Chassi Number">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Matricule</label>
                                    <input type="text" name="matricule" value="{{ $data->matricule }}" class="form-control" placeholder="Matricule">
                                </div>
                            </div>

                            {{--computer--}}

                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>RAM (GB)</label>
                                    <input type="text" name="ram" value="{{ $data->ram }}" class="form-control" placeholder="RAM">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Hard Drive (GB)</label>
                                    <input type="text" name="hard_drive" value="{{ $data->hard_drive }}" class="form-control" placeholder="Hard Drive">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Operating System</label>
                                    <input type="text" name="operating_system" value="{{ $data->operating_system }}" class="form-control" placeholder="Operating System">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Processor</label>
                                    <input type="text" name="processor" value="{{ $data->processor }}" class="form-control" placeholder="Processor">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Processor Speed (GHZ)</label>
                                    <input type="text" name="processor_speed" value="{{ $data->processor_speed }}" class="form-control" placeholder="Processor Speed">
                                </div>
                            </div>

                            {{--tvs--}}
                            <div class="col-md-3 tvs">
                                <div class="form-group">
                                    <label>TV Size in Inches</label>
                                    <input type="text" name="tv_size" value="{{ $data->tv_size }}" class="form-control" placeholder="TV Size in Inches">
                                </div>
                            </div>

                            {{--land--}}
                            <div class="col-md-3 land">
                                <div class="form-group">
                                    <label>Is there are house in the land (yes, no, unknown)</label>
                                    <select name="house_in_land" class="form-control">
                                        <option value="">--choose--</option>
                                        <option value="Yes" {{ $data->house_in_land == 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ $data->house_in_land == 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Unknown" {{ $data->house_in_land == 'Unknown' ? 'selected' : '' }}>Unknown</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 land house_in_land">
                                <div class="form-group">
                                    <label>Furnished</label>
                                    <select name="furnished" class="form-control">
                                        <option value="">--choose--</option>
                                        <option value="Yes" {{ $data->furnished == 'Yes' ? 'selected' : '' }}>Yes</option>
                                        <option value="No" {{ $data->furnished == 'No' ? 'selected' : '' }}>No</option>
                                        <option value="Unknown" {{ $data->furnished == 'Unknown' ? 'selected' : '' }}>Unknown</option>
                                    </select>
                                </div>
                            </div>
                            {{--house--}}
                            <div class="col-md-3 house">
                                <div class="form-group">
                                    <label>Dimentions of the plot</label>
                                    <input type="text" name="dimentions_of_the_plot" value="{{ $data->dimentions_of_the_plot }}" class="form-control" placeholder="Dimentions of the plot">
                                </div>
                            </div>
                            <div class="col-md-3 house room_in_house">
                                <div class="form-group">
                                    <label>Number of Room</label>
                                    <input type="text" name="number_of_Room" value="{{ $data->number_of_Room }}" class="form-control" placeholder="Number of Room">
                                </div>
                            </div>
                            {{--software--}}
                            <div class="col-md-3 software">
                                <div class="form-group">
                                    <label>Source Code Owner</label>
                                    <input type="text" name="source_code_owner" value="{{ $data->source_code_owner }}" class="form-control" placeholder="Source Code Owner">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mt-4">
                                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                                </div>
                            </div>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    // ajax
    $('#department_id').on('change', function(){
        var value = $(this).val();
        $.ajax({
            type:'get',
            url: "{{url('/asset/department/{id}')}}",
            data: {
                value: value
            },
            success: function(data) {
                $('#serial').val(data);
            }
        });
    });

    // purchase date cant greater than service date
    $(".date").on("keyup change", function(e) {
        var purchase = $("#purchase_date").val();
        var service = $("#service_date").val();
        if ((purchase !== '') && (service !== '')) {
            if (service < purchase) {
                $("#service_date").val('');
                alert("Service date cannot be less than Purchase date.");
            }
        }
    });


    $(".vehicle").hide();
    $(".computer").hide();
    $(".tvs").hide();
    $(".land").hide();
    $(".house").hide();
    $(".software").hide();
    $(".computer-tv").hide();

    // selected in edit mode
    var selected_val = $('select[name="asset_type"]').val();
    if (selected_val == 'vehicle') {
        $(".computer").hide();
        $(".software").hide();
        $(".tvs").hide();
        $(".house").hide();
        $(".land").hide();
        $(".vehicle").show(300);
        $(".computer-tv").show(300);
    } else if (selected_val == 'computer') {
        $(".vehicle").hide();
        $(".software").hide();
        $(".tvs").hide();
        $(".house").hide();
        $(".land").hide();
        $(".computer").show(300);
        $(".computer-tv").show();
    } else if (selected_val == 'tvs') {
        $(".vehicle").hide();
        $(".software").hide();
        $(".house").hide();
        $(".land").hide();
        $(".computer").hide();
        $(".tvs").show(300);
        $(".computer-tv").show(300);
    } else if (selected_val == 'software') {
        $(".vehicle").hide();
        $(".computer").hide();
        $(".tvs").hide();
        $(".house").hide();
        $(".land").hide();
        $(".software").show(300);
        $(".computer-tv").hide();
    } else if (selected_val == 'land') {
        $(".vehicle").hide();
        $(".computer").hide();
        $(".tvs").hide();
        $(".software").hide();
        $(".land").show(300);
        $(".house_in_land").hide();
        $(".computer-tv").hide();
    } else if (selected_val == 'house') {
        $(".vehicle").hide();
        $(".computer").hide();
        $(".land").hide();
        $(".tvs").hide();
        $(".software").hide();
        $(".house").show(300);
        $(".house_in_land").show(300);
        $(".computer-tv").hide();
    }else {
        $(".vehicle").hide();
        $(".computer").hide();
        $(".land").hide();
        $(".software").hide();
        $(".tvs").hide();
        $(".house").hide();
        $(".house_in_land").hide();
        $(".computer-tv").hide();
    }

    // on change hide and show
    $('select[name="asset_type"]').on('change', function() {
        if ($(this).val() == 'vehicle') {
            $(".computer").hide();
            $(".software").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".land").hide();
            $(".vehicle").show(300);
            $(".computer-tv").show(300);
        } else if ($(this).val() == 'computer') {
            $(".vehicle").hide();
            $(".software").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".land").hide();
            $(".computer").show(300);
            $(".computer-tv").show(300);
        } else if ($(this).val() == 'tvs') {
            $(".vehicle").hide();
            $(".software").hide();
            $(".house").hide();
            $(".land").hide();
            $(".computer").hide();
            $(".tvs").show(300);
            $(".computer-tv").show(300);
        } else if ($(this).val() == 'software') {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".land").hide();
            $(".software").show(300);
            $(".computer-tv").hide();
        } else if ($(this).val() == 'land') {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".tvs").hide();
            $(".software").hide();
            $(".land").show(300);
            $(".house").hide();
            $(".house_in_land").hide();
            $(".computer-tv").hide();
            var house_in_land = $('select[name="house_in_land"]').val();
            if (house_in_land == 'Yes') {
                $(".house_in_land").show(300);
            }else {
                $(".house_in_land").hide();
            }
            var room_in_house = $('select[name="furnished"]').val();
            if (room_in_house == 'Yes') {
                $(".room_in_house").show(300);
            }else {
                $(".room_in_house").hide();
            }
        } else if ($(this).val() == 'house') {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".land").hide();
            $(".tvs").hide();
            $(".software").hide();
            $(".house").show(300);
            $(".house_in_land").show(300);
            $(".computer-tv").hide();
        }else {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".land").hide();
            $(".software").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".house_in_land").hide();
            $(".computer-tv").hide();
        }
    });

    var house_in_land = $('select[name="house_in_land"]').val();
    if (house_in_land == 'Yes') {
        $(".house_in_land").show(300);
    }else {
        $(".house_in_land").hide();
    }

    var room_in_house = $('select[name="furnished"]').val();
    if (room_in_house == 'Yes') {
        $(".room_in_house").show(300);
    }else {
        $(".room_in_house").hide();
    }

    $('select[name="house_in_land"]').on('change', function() {
        if ($(this).val() == 'Yes') {
            $(".house_in_land").show(300);
            var room_in_house = $('select[name="furnished"]').val();
            if (room_in_house == 'Yes') {
                $(".room_in_house").show(300);
            }else {
                $(".room_in_house").hide();
            }
        }else {
            $(".house_in_land").hide();
            $(".room_in_house").hide();
        }
    });

    $('select[name="furnished"]').on('change', function() {
        if ($(this).val() == 'Yes') {
            $(".room_in_house").show(300);
        }else {
            $(".room_in_house").hide();
        }
    });

    // menu open
    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-list-menu").addClass("active");
</script>
@endsection
