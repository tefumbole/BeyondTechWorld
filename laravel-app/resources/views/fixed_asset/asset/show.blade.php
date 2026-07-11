@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('asset.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Assets List')}} </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>{{trans('file.Asset Information')}}</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6" style="padding: 8vw">
                                    @if($data->image)
                                    <img src="{{url('public/images/assets',$data->image)}}" height="200vw">
                                    @endif
                                    @if($data->images)
                                        @foreach($data->images as $image)
                                            <img src="{{url('public/images/assets',$image->image)}}" height="200vw">
                                        @endforeach
                                    @endif
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-striped table-hover">
                                        <tr>
                                            <th><h5>Sr No</h5></th>
                                            <td>{{ $data->serial_no }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Name</h5></th>
                                            <td>{{ $data->name }}</td>
                                        </tr>
{{--                                        <tr>--}}
{{--                                            <th><h5>Person Incharge</h5></th>--}}
{{--                                            <td>{{ $data->manager }}</td>--}}
{{--                                        </tr>--}}
                                        <tr>
                                            <th><h5>Assign To</h5></th>
                                            <td>{{ $data->Assign_to }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Set Type</h5></th>
                                            <td>{{ $data->set_type }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Depreciation Method</h5></th>
                                            <td>{{ $data->depreciation_type }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Asset Type</h5></th>
                                            <td>{{ $data->asset_type }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Category</h5></th>
                                            <td>{{ @$data->category->name }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Department Name</h5></th>
                                            <td>{{ @$data->department->name }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Department Code</h5></th>
                                            <td>{{ @$data->department->code }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Donor</h5></th>
                                            <td>{{ @$data->donor->name }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Region</h5></th>
                                            <td>{{ @$data->region->name }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Station</h5></th>
                                            <td>{{ @$data->station->name }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Manufacturer</h5></th>
                                            <td>{{ $data->manufacturer }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Model</h5></th>
                                            <td>{{ $data->model }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Serial</h5></th>
                                            <td>{{ $data->serial }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>remark</h5></th>
                                            <td>{{ $data->remark }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>City</h5></th>
                                            <td>{{ $data->city }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Physical Location</h5></th>
                                            <td>{{ $data->physical_location }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Price</h5></th>
                                            <td>{{ $data->price }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Life Span</h5></th>
                                            <td>{{ $data->life_span }} Years</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Scrap Value</h5></th>
                                            <td>{{ $data->scrap }}</td>
                                        </tr>
                                        @php
                                            $asset_calcultion = \App\Asset::depricationCaluculate($data);
                                                $depreciation = $asset_calcultion['depreciation'];
                                                $book_value = $asset_calcultion['book_value'];
                                                $available = $asset_calcultion['available'];
                                                $consume = $asset_calcultion['consume'];
                                        @endphp
                                        <tr>
                                            <th><h5>Consume Status</h5></th>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" style="width:{{@$available}}%" title="Available ({{@$available}}) days">
                                                        Available ({{@$available}})
                                                    </div>
                                                    <div class="progress-bar bg-danger" style="width:{{@$consume}}%" title="Consume ({{@$consume}}) days">
                                                        Consume ({{@$consume}})
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><h5>Depreciation</h5></th>
                                            <td>{{ round(@$depreciation, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Appreciation Percentage</h5></th>
                                            <td>{{ $data->appreciation }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Book Value</h5></th>
                                            <td>{{ round(@$book_value, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Purchase Date</h5></th>
                                            <td>{{ $data->purchase_date }}</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Service Date</h5></th>
                                            <td>{{ $data->service_date }}</td>
                                        </tr>
                                        @if($data->is_active == 3)
                                            <tr>
                                                <th><h5>Transfer Date</h5></th>
                                                <td>{{ $data->transfer_at }}</td>
                                            </tr>
                                        @endif

                                        {{--vehicles--}}
                                        @if($data->asset_type == 'vehicle')
                                            <tr>
                                                <th><h5>Driver</h5></th>
                                                <td>{{ $data->driver }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Engine Type</h5></th>
                                                <td>{{ $data->engine_type }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Number of Seats</h5></th>
                                                <td>{{ $data->number_of_Seats }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Horse Power</h5></th>
                                                <td>{{ $data->horse_power }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Milage At Purchase</h5></th>
                                                <td>{{ $data->milage_at_purchase }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Chassi Number</h5></th>
                                                <td>{{ $data->chassi_number }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Matricule</h5></th>
                                                <td>{{ $data->matricule }}</td>
                                            </tr>
                                        @endif
                                        {{--computer--}}
                                        @if($data->asset_type == 'computer')
                                            <tr>
                                                <th><h5>RAM</h5></th>
                                                <td>{{ $data->ram }} (GB)</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Hard Drive</h5></th>
                                                <td>{{ $data->hard_drive }} (GB)</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Operating System</h5></th>
                                                <td>{{ $data->operating_system }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Processor</h5></th>
                                                <td>{{ $data->processor }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Processor Speed</h5></th>
                                                <td>{{ $data->processor_speed }} (GHZ)</td>
                                            </tr>
                                        @endif
                                        {{--tvs--}}
                                        @if($data->asset_type == 'tvs')
                                            <tr>
                                                <th><h5>TV Size</h5></th>
                                                <td>{{ $data->tv_size }}</td>
                                            </tr>
                                        @endif
                                        {{--software--}}
                                        @if($data->asset_type == 'software')
                                            <tr>
                                                <th><h5>Source Code Owner</h5></th>
                                                <td>{{ $data->source_code_owner }}</td>
                                            </tr>
                                        @endif
                                        {{--land--}}
                                        @if($data->asset_type == 'land')
                                            <tr>
                                                <th><h5>House In Land</h5></th>
                                                <td>{{ $data->house_in_land }}</td>
                                            </tr>
                                            @if($data->house_in_land == 'Yes')
                                                <tr>
                                                    <th><h5>Furnished</h5></th>
                                                    <td>{{ $data->furnished }}</td>
                                                </tr>
                                                <tr>
                                                    <th><h5>Number of Rooms</h5></th>
                                                    <td>{{ $data->number_of_Room }}</td>
                                                </tr>
                                            @endif
                                        @endif
                                        {{--house--}}
                                        @if($data->asset_type == 'house')
                                            <tr>
                                                <th><h5>Furnished</h5></th>
                                                <td>{{ $data->furnished }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Dimentions Of The Plot</h5></th>
                                                <td>{{ $data->dimentions_of_the_plot }}</td>
                                            </tr>
                                            <tr>
                                                <th><h5>Number of Rooms</h5></th>
                                                <td>{{ $data->number_of_Room }}</td>
                                            </tr>
                                        @endif

                                        <tr>
                                            <th><h5>Status</h5></th>
                                            <td>
                                            @if($data->is_active == 0)
                                                Inactive
                                            @elseif($data->is_active == 1)
                                                Active
                                            @elseif($data->is_active == 2)
                                                Dispose
                                            @elseif($data->is_active == 3)
                                                Transfered
                                            @endif</td>
                                        </tr>
                                        <tr>
                                            <th><h5>Date</h5></th>
                                            <td>{{ $data->created_at }}</td>
                                        </tr>

                                    </table>
                                </div>
                            </div>
                            @if(isset($assetTransfer))
                            <div class="col-md-12">
                                <h3>Tranfer List</h3>
                                <div class="table-responsive">
                                    <table id="role-table" class="table">
                                        <thead>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Transfer From</th>
                                            <th>Transfer To</th>
                                            <th>Transfer Price</th>
                                            <th>Transfer Date</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($assetTransfer as $key=>$item)
                                            <tr>
                                                <td>{{ $item->assets->name }}</td>
                                                <td>{{ $item->fromDepartment->name }} | {{ $item->fromDepartment->code }}</td>
                                                <td>{{ $item->toDepartment->name }} | {{ $item->toDepartment->code }}</td>
                                                <td>{{ $item->price}}</td>
                                                <td>{{ $item->date}}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">
        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-list-menu").addClass("active");
    </script>
@endsection
