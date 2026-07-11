@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('asset.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Assets List')}} </a>
                    <div class="card">
                        <div class="card-header align-items-center">
                            <h4 class="text-center">Asset Report Dashboard</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <!-- Count item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.category')}}">
                                            <div class="icon"><i class="dripicons-bookmark" style="color: #733686"></i></div>
                                            <div class="name"><strong style="color: #733686">Category</strong></div>
                                            <div class="count-number">{{ $category_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- Count item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.category.new')}}">
                                            <div class="icon"><i class="dripicons-bookmark" style="color: #86366f"></i></div>
                                            <div class="name"><strong style="color: #733686">Refined Category</strong></div>
                                            <div class="count-number">{{ $category_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- Count item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.department')}}">
                                            <div class="icon"><i class="dripicons-store" style="color: #5f8636"></i></div>
                                            <div class="name"><strong style="color: #5f8636">Department</strong></div>
                                            <div class="count-number">{{ $department_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- Count item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.donor')}}">
                                            <div class="icon"><i class="dripicons-user-group" style="color: #ff8952"></i></div>
                                            <div class="name"><strong style="color: #ff8952">Donor</strong></div>
                                            <div class="count-number">{{ $donor_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- Count item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.region')}}">
                                            <div class="icon"><i class="dripicons-map" style="color: #00c689"></i></div>
                                            <div class="name"><strong style="color: #00c689">Region</strong></div>
                                            <div class="count-number ">{{ $region_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- Count item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.station')}}">
                                            <div class="icon"><i class="dripicons-rocket" style="color: #297ff9"></i></div>
                                            <div class="name"><strong style="color: #297ff9">Station</strong></div>
                                            <div class="count-number">{{ $station_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.repair')}}">
                                            <div class="icon"><i class="fa fa-wrench" style="color: #15bfe7"></i></div>
                                            <div class="name"><strong style="color: #15bfe7">Repair Report</strong></div>
                                            <div class="count-number">{{ $repair_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.expense')}}">
                                            <div class="icon"><i class="fa fa-car" style="color: #9d2c94"></i></div>
                                            <div class="name"><strong style="color: #9d2c94">Automobile Report</strong></div>
                                            <div class="count-number">{{ $automobile_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.photocopy')}}">
                                            <div class="icon"><i class="fa fa-book" style="color: #297ff9"></i></div>
                                            <div class="name"><strong style="color: #297ff9">PhotoCopy Report</strong></div>
                                            <div class="count-number">{{ $copy_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.general')}}">
                                            <div class="icon"><i class="dripicons-archive" style="color: #b79a0d"></i></div>
                                            <div class="name"><strong style="color: #b79a0d">General Report</strong></div>
                                            <div class="count-number">{{ $general_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.dispose')}}">
                                            <div class="icon"><i class="fa fa-fire" style="color: #fb0b0b"></i></div>
                                            <div class="name"><strong style="color: #fb0b0b">Dispose Report</strong></div>
                                            <div class="count-number">{{ $dispose_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                                <!-- item widget-->
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{route('asset.report.transfer')}}">
                                            <div class="icon"><i class="fa fa-exchange" style="color: #285860"></i></div>
                                            <div class="name"><strong style="color: #285860">Transfer Report</strong></div>
                                            <div class="count-number">{{ $transfer_count }}</div>
                                        </a>
                                    </div>
                                </div>
                                <!-- item widget-->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">
        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-report-menu").addClass("active");
    </script>
@endsection
