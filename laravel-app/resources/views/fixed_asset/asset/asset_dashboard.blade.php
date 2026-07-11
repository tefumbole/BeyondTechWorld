@extends('layout.main')
@section('content')

    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session()->get('not_permitted') }}
        </div>
    @endif

    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            {{ session()->get('message') }}
        </div>
    @endif

    <style>
        .waterm-mark {
            width: 20%;
            position: absolute;
            top: 139%;
            right: 40%;
            opacity: 0.2;
            z-index: 1;
        }
        .for-print {
            display: none;
        }
        @keyframes floatUpDown {
            0% { transform: translateY(30px); }
            50% { transform: translateY(-100px); }
            100% { transform: translateY(30px); }
        }

        .float-animate {
            animation: floatUpDown 0.5s ease-in-out;
        }
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .for-print {
                display: block !important;
                margin-bottom: 20px;
            }

            .for-print .card {
                border: none !important;
                box-shadow: none !important;
                background: white !important;
                padding: 20px;
            }

            .for-print .row {
                display: flex;
                flex-wrap: wrap;
            }

            .for-print .col-md-6 {
                width: 50%;
                padding: 10px;
                box-sizing: border-box;
            }

            /* Hide everything else */
            .btn, .side-navbar, .header, #search-form {
                display: none !important;
            }
            #print-footer {
                bottom: 0;
            }
            a {
                text-decoration: none !important;
            }
        }
    </style>

    <div class="container-fluid header">
        <div class="row align-items-center">
            <!-- Welcome Section -->
            <div class="col-md-6">
                <div class="brand-text mt-3">
                    <h3>{{ trans('file.welcome') }} <span>{{ Auth::user()->name }}</span></h3>
                </div>
            </div>

            <!-- Date Filter Buttons -->
            <div class="col-md-6 text-md-right mt-3">
                <div class="btn-group filter-toggle">
                    <button id="total-date" class="btn btn-secondary date-btn total-btn" onclick="dashboardFilter(this)"
                            data-start="" data-end="">{{ trans('file.Total') }}</button>

                    <button id="today-date" class="btn btn-secondary date-btn today-btn" onclick="dashboardFilter(this)"
                            data-start="{{ now()->toDateString() }}" data-end="{{ now()->toDateString() }}">{{ trans('file.Today') }}</button>

                    <button id="month-date" class="btn btn-secondary date-btn month-btn" onclick="dashboardFilter(this)"
                            data-start="{{ now()->startOfMonth()->toDateString() }}"
                            data-end="{{ now()->toDateString() }}">{{ trans('file.This Month') }}</button>

                    <button id="year-date" class="btn btn-secondary date-btn year-btn" onclick="dashboardFilter(this)"
                            data-start="{{ now()->startOfYear()->toDateString() }}"
                            data-end="{{ now()->toDateString() }}">{{ trans('file.This Year') }}</button>
                </div>
            </div>
        </div>
        <button id="print-btn" class="btn btn-info"><span class="fa fa-print"></span> Print</button>

    </div>
<div id="print-area">
{{--        form filter--}}
    <div class="container-fluid">
        <form method="post" action="{{ route('asset.dashboard') }}" id="search-form">
            @csrf
                <div class="row m-2 p-3" style="background: lightgrey; border-radius: 10px">
                    <div class="col-md-4 product-report-filter mt-3">
                        <div class="form-group row">
                            <label class="d-tc mt-2"><strong>{{trans('file.Choose Your Date')}}</strong> &nbsp;</label>
                            <div class="d-tc">
                                <div class="input-group">
                                    <input type="text" class="daterangepicker-field form-control" value="{{$search_start_date ?? $start_date}} To {{$search_end_date ?? $end_date}}" required />
                                    <input type="hidden" id="start_date" name="start_date" value="{{$search_start_date ?? $start_date}}" />
                                    <input type="hidden" id="end_date" name="end_date" value="{{$search_end_date ?? $end_date}}" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3">
                        <div class="form-group row">
                            <label class="d-tc mt-2"><strong>Department</strong> &nbsp;</label>
                            <div class="d-tc">
                                <select id="department_id" name="department_id" class="selectpicker form-control" data-live-search="true"  >
                                    <option value="0">All Departments</option>
                                    @foreach($departments as $item)
                                        <option {{ @$department_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name }} ({{$item->code}})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3">
                        <div class="form-group row">
                            <label class="d-tc mt-2"><strong>Region</strong> &nbsp;</label>
                            <div class="d-tc">
                                <select id="region_id" name="region_id" class="selectpicker form-control" data-live-search="true"  >
                                    <option value="0">All Regions</option>
                                    @foreach($regions as $item)
                                        <option {{ @$region_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3">
                        <div class="form-group row">
                            <label class="d-tc mt-2"><strong>Stations</strong> &nbsp;</label>
                            <div class="d-tc">
                                <select id="station_id" name="station_id" class="selectpicker form-control" data-live-search="true"  >
                                    <option value="0">All Stations</option>
                                    @foreach($stations as $item)
                                        <option {{ @$station_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3">
                        <div class="form-group row">
                            <label class="d-tc mt-2"><strong>Donors</strong> &nbsp;</label>
                            <div class="d-tc">
                                <select id="donor_id" name="donor_id" class="selectpicker form-control" data-live-search="true"  >
                                    <option value="0">All Donors</option>
                                    @foreach($donors as $item)
                                        <option {{ @$donor_id == $item->id ? "selected" : "" }} value="{{$item->id}}">{{$item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mt-3">
                        <div class="form-group row">
                            <div class="d-tc">
                                <button type="submit" class="btn btn-primary">Search</button>
                                {{--                                <button type="button" class="btn btn-primary" onclick="dashboardFilter()">Search</button>--}}
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <!-- Counts Section -->
        <section class="dashboard-counts">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-12 form-group">
                        <div class="row">
                            <div class="col-sm-12 for-print">
                                <img src="{{url('public/logo', $general_setting->email_header)}}" style=" width: 100%;">
                                <img src="{{url('public/logo', $general_setting->email_water_mark)}}" class="waterm-mark">
                                <div class="card p-3 mb-4">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <h4>Date Range:</h4>
                                            <div>{{ $search_start_date ?? $start_date }} to {{ $search_end_date ?? $end_date }}</div>
                                        </div>

                                        <div class="col-md-6 mb-2">
                                            <h4>Department:</h4>
                                            <div>{{ \App\Department::find($department_id)->name ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-6 mb-2">
                                            <h4>Region:</h4>
                                            <div>{{ \App\Region::find($region_id)->name ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-6 mb-2">
                                            <h4>Station:</h4>
                                            <div>{{ \App\Station::find($station_id)->name ?? '-' }}</div>
                                        </div>

                                        <div class="col-md-6 mb-2">
                                            <h4>Donor:</h4>
                                            <div>{{ \App\Donor::find($donor_id)->name ?? '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-archive" style="color: #6495ED"></i></div>
                                    <div class="name"><strong style="color: #6495ED">Total Asset</strong></div>
                                    <div class="count-number" id="total_asset">{{ number_format((float)$total_asset_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-shopping-cart" style="color: #800080"></i></div>
                                    <div class="name"><strong style="color: #800080">Asset Purchase</strong></div>
                                    <div class="count-number" id="asset_purchase">{{ number_format((float)$total_asset_purchase_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-cart-arrow-down" style="color: #C88141"></i></div>
                                    <div class="name"><strong style="color: #C88141">Asset Sale</strong></div>
                                    <div class="count-number" id="asset_sale">{{ number_format((float)$total_sale_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-chevron-up" style="color: green"></i></div>
                                    <div class="name"><strong style="color: green">Asset Book Value</strong></div>
                                    <div class="count-number" id="book_value">{{ number_format((float)$book_value, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-chevron-down" style="color: crimson"></i></div>
                                    <div class="name"><strong style="color: crimson">Asset Deprication</strong></div>
                                    <div class="count-number" id="depreciation">{{ number_format((float)$deprication, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-money" style="color: #FF00FF"></i></div>
                                    <div class="name"><strong style="color: #FF00FF">Asset Expense</strong></div>
                                    <div class="count-number" id="asset_expense">{{ number_format((float)$expense_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-book" style="color: #733686"></i></div>
                                    <div class="name"><strong style="color: #733686">PhotoCopy Activity</strong></div>
                                    <div class="count-number" id="photocopy_activity">{{ number_format((float)$copy_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-car" style="color: red"></i></div>
                                    <div class="name"><strong style="color: red">Milage Activity</strong></div>
                                    <div class="count-number" id="milage_activity">{{ number_format((float)$automobile_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-wrench" style="color: green"></i></div>
                                    <div class="name"><strong style="color: green">Repair Activity</strong></div>
                                    <div class="count-number" id="repair_activity">{{ number_format((float)$repair_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-adjust" style="color: blue"></i></div>
                                    <div class="name"><strong style="color: blue">General Activity</strong></div>
                                    <div class="count-number" id="general_activity">{{ number_format((float)$general_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-fire" style="color: #00FFFF"></i></div>
                                    <div class="name"><strong style="color: #00FFFF">Asset Dispose</strong></div>
                                    <div class="count-number" id="asset_dispose">{{ number_format((float)$dispose_sum, 2) }}</div>
                                </div>
                            </div>

                            <!-- Count item widget-->
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <div class="icon"><i class="fa fa-exchange" style="color: cornflowerblue"></i></div>
                                    <div class="name"><strong style="color: cornflowerblue">Asset Transfer</strong></div>
                                    <div class="count-number" id="asset_transfer">{{ number_format((float)$transfer_sum, 2) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <hr>

        <!-- Category data -->
        <section class="dashboard-counts">
            <div class="container-fluid">
                <div class="card">
                    <div class="card-header align-items-center">
                        <h4 class="text-center">Asset Category Wise</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            @foreach($dataa as $item)
                                @php
                                   $categoryIds = !empty($item->child_ids) ? array_merge([$item->id], $item->child_ids) : [$item->id];

                                   $category_assets = \App\Asset::whereIn('category_id', $categoryIds)->where('is_active', 1)->whereBetween('created_at', [$start_date, $end_date]);
                                   $category_assets = \App\Http\Controllers\AssetController::dashboardCalculation($department_id, $region_id, $station_id, $donor_id, $category_assets);
                                   $category_assets = $category_assets->get();
                                   $book_value = 0;
                                   foreach ($category_assets as $asset) {
                                       $calculation = \App\Asset::depricationCaluculate($asset);
                                       $book_value += $calculation['book_value'];
                                   }
                                @endphp
                                <div class="col-sm-3">
                                    <div class="wrapper count-title text-center">
                                        <a href="{{ route('asset.dashboard.category', ['id' => $item->id]) }}">
                                            <div class="icon"><i class="dripicons-bookmark" style="color: #733686"></i></div>
                                            <div class="name"><strong style="color: #733686"> {{ $item->name }}</strong></div>
                                            <div class="count-number">{{ number_format((float)$book_value, 2) }}</div>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <img class="for-print"  id="print-footer" src="{{url('public/logo', $general_setting->email_footer)}}" style=" width: 100%;">
    </div>
    <script type="text/javascript">


        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-dashboard-menu").addClass("active");


        $("#print-btn").on("click", function () {
            $(".for-print").show();
            $("#search-form, .btn-primary, .side-navbar, .header").hide();

            setTimeout(() => {
                window.print();
                $("#search-form, .btn-primary, .side-navbar, .header").show();
                $(".for-print").hide();
            }, 100);
        });

        $(".daterangepicker-field").daterangepicker({
            callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $(".product-report-filter input[name=start_date]").val(start_date);
                $(".product-report-filter input[name=end_date]").val(end_date);
            }
        });

        // Helper to store item with expiry (2 minutes)
        function setWithExpiry(key, value, ttl = 0.5 * 60 * 1000) {
            const now = new Date();
            const item = {
                value: value,
                expiry: now.getTime() + ttl,
            };
            localStorage.setItem(key, JSON.stringify(item));
        }

        // Helper to get item and check expiry
        function getWithExpiry(key) {
            const itemStr = localStorage.getItem(key);
            if (!itemStr) return null;

            const item = JSON.parse(itemStr);
            const now = new Date();

            if (now.getTime() > item.expiry) {
                localStorage.removeItem(key); // expired
                return null;
            }

            return item.value;
        }

        function dashboardFilter(button) {
            const startDate = button.getAttribute('data-start');
            const endDate = button.getAttribute('data-end');

            $("#start_date").val(startDate);
            $("#end_date").val(endDate);

            // Store clicked button's id with half-minute expiry
            setWithExpiry('activeFilterButton', button.id);

            // Submit the form
            document.getElementById('search-form').submit();
        }

        document.addEventListener('DOMContentLoaded', function () {
            const activeButtonId = getWithExpiry('activeFilterButton');
            let button = activeButtonId ? document.getElementById(activeButtonId) : null;

            // If no button found or ID is invalid, fall back to #total-date
            if (!button) {
                button = document.getElementById('total-date');
            }

            if (button) {
                // Remove 'active' class from all filter buttons
                document.querySelectorAll('.dashboard-filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });

                // Add 'active' to the selected/fallback button
                button.classList.add('active');
            }
        });

        {{--function dashboardFilter(button) {--}}
        {{--    $("#loader").css('display', 'block');--}}

        {{--    let startDate, endDate;--}}
        {{--    if(button) {--}}
        {{--        startDate = button.getAttribute('data-start');--}}
        {{--        endDate = button.getAttribute('data-end');--}}
        {{--    } else {--}}
        {{--        startDate = $("#start_date").val();--}}
        {{--        endDate = $("#end_date").val();--}}
        {{--    }--}}
        {{--    let department_id = $("#department_id").val();--}}
        {{--    let donor_id = $("#donor_id").val();--}}
        {{--    let region_id = $("#region_id").val();--}}
        {{--    let station_id = $("#station_id").val();--}}

        {{--    document.querySelectorAll('.date-btn').forEach(btn => btn.classList.remove('active'));--}}

        {{--    // Add 'active' class to the clicked button--}}
        {{--    if(button) { button.classList.add('active'); }--}}
        {{--    let queryString = `?start_date=${startDate}&end_date=${endDate}&department_id=${department_id}&donor_id=${donor_id}&region_id=${region_id}&station_id$={station_id}`;--}}

        {{--    fetch("{{ route('asset.dashboard') }}" + queryString, {--}}
        {{--        method: "GET",--}}
        {{--        headers: { "X-Requested-With": "XMLHttpRequest" }--}}
        {{--    })--}}
        {{--        .then(response => response.text())--}}
        {{--        .then(html => {--}}
        {{--            let dashboardCounts = document.querySelector('.dashboard-counts');--}}

        {{--            // Update content--}}
        {{--            dashboardCounts.innerHTML =--}}
        {{--                new DOMParser().parseFromString(html, "text/html")--}}
        {{--                    .querySelector('.dashboard-counts').innerHTML;--}}

        {{--            // Remove old animation class if it exists--}}
        {{--            dashboardCounts.classList.remove("float-animate");--}}

        {{--            // Add floating animation class--}}
        {{--            setTimeout(() => {--}}
        {{--                dashboardCounts.classList.add("float-animate");--}}
        {{--            }, 100); // Small delay to ensure animation triggers--}}

        {{--        })--}}
        {{--        .catch(error => console.error("Error:", error))--}}
        {{--        .finally(() => {--}}
        {{--            $("#loader").css('display', 'none');--}}
        {{--        });--}}
        {{--}--}}
    </script>

@endsection
