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
                        <form id="product-form">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.name')}} *</strong> </label>
                                    <input type="text" name="name" required class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Assign To</label>
                                    <input type="text" name="Assign_to" class="form-control" placeholder="Assign To">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Assets Form</label>
                                    <select name="asset_type" class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        <option value="software">SOFTWARE</option>
                                        <option value="land">LAND</option>
                                        <option value="house">BUILDINGS, FIXTURE & FITTINGS</option>
                                        <option value="mediacal-equipment">EQUIPMENT</option>
                                        <option value="furniture">FURNITURE</option>
                                        <option value="vehicle">TRANSPORT EQUIPMENT</option>
                                        <option value="tvs">TVs</option>
                                        <option value="computer">COMPUTERS</option>
                                        <option value="general-electronics">GENERAL</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Type')}}</label>
                                    <select name="category_id" class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        @foreach($category as $item)
                                            <option value="{{ $item->id }}">  {{ $item->name }} </option>
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
                                            <option value="{{ $item->id }}">  {{ $item->name }} / {{ $item->code }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Donor')}}</label>
                                    <select name="donor_id" class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        @foreach($donor as $item)
                                            <option value="{{ $item->id }}">  {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Region')}}</label>
                                    <select name="region_id" class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        @foreach($region as $item)
                                            <option value="{{ $item->id }}">  {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Assets Station')}}</label>
                                    <select name="station_id" class="selectpicker form-control" data-live-search="true">
                                        <option value="">  -- choose -- </option>
                                        @foreach($station as $item)
                                            <option value="{{ $item->id }}">  {{ $item->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Bar Code</label>
                                    <input type="text" name="serial_no" id="serial" placeholder="serial" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Scrap Value</label>
                                    <input type="number" name="scrap" placeholder="Scrap Value" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3 land">
                                <div class="form-group">
                                    <label>Appreciation Value</label>
                                    <input type="number" name="appreciation" placeholder="Appreciation Value" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.Product Image')}}</strong> </label> <i class="dripicons-question" data-toggle="tooltip" title="{{trans('file.You can upload multiple image. Only .jpeg, .jpg, .png, .gif file can be uploaded. First image will be base image.')}}"></i>
                                    <div id="imageUpload" class="dropzone"></div>
                                    @if($errors->has('image'))
                                        <span>
                                       <strong>{{ $errors->first('image') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-md-3 computer-tv">
                                <div class="form-group">
                                    <label>manufacturer</label>
                                    <input type="text" name="manufacturer" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Set Type</label>
                                    <input type="text" name="set_type" class="form-control" placeholder="Set Type">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Depreciation Method</label>
                                    <select name="depreciation_type" class="form-control">
                                        <option value="fixed">Fixed</option>
                                        <option value="starigh_line" selected>Starigh Line</option>
                                        <option value="declining_balance">Declining Balance</option>
                                        <option value="double_declining_balance">Double Declining Balance</option>
                                        <option value="sum_of_the_years_digits">Sum of the Years Digits</option>
                                        <option value="units_of_production">Units of Production</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Remark</label>
                                    <input type="text" name="remark" class="form-control" placeholder="Remark">
                                </div>
                            </div>
                            <div class="col-md-3 computer-tv">
                                <div class="form-group">
                                    <label>Model</label>
                                    <input type="text" name="model" class="form-control">
                                </div>
                            </div>
{{--                            <div class="col-md-3">--}}
{{--                                <div class="form-group">--}}
{{--                                    <label>Serial</label>--}}
{{--                                    <input type="text" name="serial" class="form-control">--}}
{{--                                </div>--}}
{{--                            </div>--}}
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Price</label>
                                    <input type="number" name="price" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Physical Location</label>
                                    <input type="text" name="physical_location" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.City')}}</label>
                                    <input type="text" name="city" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Purchase Date</label>
                                    <input type="date" id="purchase_date" name="purchase_date" class="form-control date">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Service Date</label>
                                    <input type="date" id="service_date" name="service_date" class="form-control date">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Useul Life in Years</label>
                                    <input type="number" name="life_span" class="form-control life_span">
                                </div>
                            </div>

{{--                            vehicles--}}
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Manager</label>
                                    <input type="text" name="manager" class="form-control" placeholder="Manager">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Driver</label>
                                    <input type="text" name="driver" class="form-control" placeholder="Driver">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Engine Type</label>
                                    <select name="engine_type" class="form-control">
                                        <option value="">-- choose --</option>
                                        <option value="Super">Super</option>
                                        <option value="Gas">Gas</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Number of Seats</label>
                                    <input type="number" name="number_of_Seats" class="form-control" placeholder="Number of Seats">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Horse Power</label>
                                    <input type="text" name="horse_power" class="form-control" placeholder="Horse Power">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Milage at Purchase</label>
                                    <input type="text" name="milage_at_purchase" class="form-control" placeholder="Milage at Purchase">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Chassi Number</label>
                                    <input type="text" name="chassi_number" class="form-control" placeholder="Chassi Number">
                                </div>
                            </div>
                            <div class="col-md-3 vehicle">
                                <div class="form-group">
                                    <label>Matricule</label>
                                    <input type="text" name="matricule" class="form-control" placeholder="Matricule">
                                </div>
                            </div>

                            {{--computer--}}

                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>RAM (GB)</label>
                                    <input type="text" name="ram" class="form-control" placeholder="RAM">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Hard Drive (GB)</label>
                                    <input type="text" name="hard_drive" class="form-control" placeholder="Hard Drive">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Operating System</label>
                                    <input type="text" name="operating_system" class="form-control" placeholder="Operating System">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Processor</label>
                                    <input type="text" name="processor" class="form-control" placeholder="Processor">
                                </div>
                            </div>
                            <div class="col-md-3 computer">
                                <div class="form-group">
                                    <label>Processor Speed (GHZ)</label>
                                    <input type="text" name="processor_speed" class="form-control" placeholder="Processor Speed">
                                </div>
                            </div>

                            {{--tvs--}}
                            <div class="col-md-3 tvs">
                                <div class="form-group">
                                    <label>TV Size in Inches</label>
                                    <input type="text" name="tv_size" class="form-control" placeholder="TV Size in Inches">
                                </div>
                            </div>

                            {{--land--}}
                            <div class="col-md-3 land">
                                <div class="form-group">
                                    <label>Is there are house in the land (yes, no, unknown)</label>
                                    <select name="house_in_land" class="form-control">
                                        <option value="">--choose--</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 land house_in_land">
                                <div class="form-group">
                                    <label>Furnished</label>
                                    <select name="furnished" class="form-control">
                                        <option value="">--choose--</option>
                                        <option value="Yes">Yes</option>
                                        <option value="No">No</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>
                            </div>
                            {{--house--}}
                            <div class="col-md-3 house">
                                <div class="form-group">
                                    <label>Dimentions of the plot</label>
                                    <input type="text" name="dimentions_of_the_plot" class="form-control" placeholder="Dimentions of the plot">
                                </div>
                            </div>
                            <div class="col-md-3 house room_in_house">
                                <div class="form-group">
                                    <label>Number of Room</label>
                                    <input type="text" name="number_of_Room" class="form-control" placeholder="Number of Room">
                                </div>
                            </div>
                            {{--software--}}
                            <div class="col-md-3 software">
                                <div class="form-group">
                                    <label>Source Code Owner</label>
                                    <input type="text" name="source_code_owner" class="form-control" placeholder="Source Code Owner">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group mt-4">
                                    <input type="button" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-btn">
                                </div>
                            </div>
                        </div>
                        </form>
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
                $("#purchase_date").val('');
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

    $('select[name="asset_type"]').on('change', function() {
        if ($(this).val() == 'vehicle') {
            $(".computer").hide();
            $(".software").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".land").hide();
            $(".computer-tv").show(300);
            $(".vehicle").show(300);
            // life_span value
            $('.life_span').val(5);
        } else if ($(this).val() == 'computer') {
            $(".vehicle").hide();
            $(".software").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".land").hide();
            $(".computer-tv").show(300);
            $(".computer").show(300);
            // life_span value
            $('.life_span').val('');
        } else if ($(this).val() == 'tvs') {
            $(".vehicle").hide();
            $(".software").hide();
            $(".house").hide();
            $(".land").hide();
            $(".computer").hide();
            $(".computer-tv").show(300);
            $(".tvs").show(300);
            // life_span value
            $('.life_span').val('');
        } else if ($(this).val() == 'software') {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".land").hide();
            $(".computer-tv").hide();
            $(".software").show(300);
            // life_span value
            $('.life_span').val(5);
        } else if ($(this).val() == 'land') {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".tvs").hide();
            $(".house").hide();
            $(".software").hide();
            $(".computer-tv").hide();
            $(".land").show(300);
            $(".house_in_land").hide();
            // life_span value
            $('.life_span').val('');
        } else if ($(this).val() == 'house') {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".land").hide();
            $(".tvs").hide();
            $(".software").hide();
            $(".computer-tv").hide();
            $(".house").show(300);
            $(".house_in_land").show(300);
            // life_span value
            $('.life_span').val(39);
        } else if($(this).val() == 'furniture') {
            // life_span value
            $('.life_span').val(7);
        } else if($(this).val() == 'mediacal-equipment') {
            // life_span value
            $('.life_span').val(5);
        } else {
            $(".vehicle").hide();
            $(".computer").hide();
            $(".land").hide();
            $(".software").hide();
            $(".tvs").hide();
            $(".computer-tv").hide();
            $(".house").hide();
            $(".house_in_land").hide();
            // life_span value
            $('.life_span').val('');
        }
    });

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
    $("ul#assets #assets-add-menu").addClass("active");

    //dropzone portion
    Dropzone.autoDiscover = false;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(".dropzone").sortable({
        items:'.dz-preview',
        cursor: 'grab',
        opacity: 0.5,
        containment: '.dropzone',
        distance: 20,
        tolerance: 'pointer',
        stop: function () {
            var queue = myDropzone.getAcceptedFiles();
            newQueue = [];
            $('#imageUpload .dz-preview .dz-filename [data-dz-name]').each(function (count, el) {
                var name = el.innerHTML;
                queue.forEach(function(file) {
                    if (file.name === name) {
                        newQueue.push(file);
                    }
                });
            });
            myDropzone.files = newQueue;
        }
    });

    myDropzone = new Dropzone('div#imageUpload', {
        addRemoveLinks: true,
        autoProcessQueue: false,
        uploadMultiple: true,
        parallelUploads: 100,
        maxFilesize: 12,
        paramName: 'image',
        clickable: true,
        method: 'POST',
        url: '{{route('asset.store')}}',
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        renameFile: function(file) {
            var dt = new Date();
            var time = dt.getTime();
            return time + file.name;
        },
        acceptedFiles: ".jpeg,.jpg,.png,.gif",
        init: function () {
            var myDropzone = this;
            $('#submit-btn').on("click", function (e) {
                e.preventDefault();
                    if(myDropzone.getAcceptedFiles().length) {
                        myDropzone.processQueue();
                    }
                    else {
                        $.ajax({
                            type:'POST',
                            url:'{{route('asset.store')}}',
                            data: $("#product-form").serialize(),
                            success:function(response){
                                //console.log(response);
                                location.href = '../asset';
                            },
                            error:function(response) {
                                console.log(response);
                            },
                        });
                    }
            });

            this.on('sending', function (file, xhr, formData) {
                // Append all form inputs to the formData Dropzone will POST
                var data = $("#product-form").serializeArray();
                $.each(data, function (key, el) {
                    formData.append(el.name, el.value);
                });
            });
        },
        error: function (file, response) {
            console.log(response);
            if(response.errors.name) {
                $("#name-error").text(response.errors.name);
                this.removeAllFiles(true);
            }
            else if(response.errors.code) {
                $("#code-error").text(response.errors.code);
                this.removeAllFiles(true);
            }
            else {
                try {
                    var res = JSON.parse(response);
                    if (typeof res.message !== 'undefined' && !$modal.hasClass('in')) {
                        $("#success-icon").attr("class", "fas fa-thumbs-down");
                        $("#success-text").html(res.message);
                        $modal.modal("show");
                    } else {
                        if ($.type(response) === "string")
                            var message = response; //dropzone sends it's own error messages in string
                        else
                            var message = response.message;
                        file.previewElement.classList.add("dz-error");
                        _ref = file.previewElement.querySelectorAll("[data-dz-errormessage]");
                        _results = [];
                        for (_i = 0, _len = _ref.length; _i < _len; _i++) {
                            node = _ref[_i];
                            _results.push(node.textContent = message);
                        }
                        return _results;
                    }
                } catch (error) {
                    console.log(error);
                }
            }
        },
        successmultiple: function (file, response) {
            location.href = '../asset';
            //console.log(file, response);
        },
        completemultiple: function (file, response) {
            console.log(file, response, "completemultiple");
        },
        reset: function () {
            console.log("resetFiles");
            this.removeAllFiles(true);
        }
    });
</script>
@endsection
