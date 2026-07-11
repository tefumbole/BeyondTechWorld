@extends('layout.main')
@section('content')

    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
    @endif
    <div class="row">
        <div class="container-fluid">
            <div class="col-md-12">
                <div class="brand-text float-left mt-4">
                    <h3>{{trans('file.welcome')}} <span>{{Auth::user()->name}}</span> </h3>
                </div>
                <div class="filter-toggle btn-group">
                    <button class="btn btn-secondary date-btn total-btn active" onclick="dashboardFilter('total')">{{trans('file.Total')}}</button>
                    <button class="btn btn-secondary date-btn today-btn" onclick="dashboardFilter('today')">{{trans('file.Today')}}</button>
                    <button class="btn btn-secondary date-btn month-btn" onclick="dashboardFilter('month')">{{trans('file.This Month')}}</button>
                    <button class="btn btn-secondary date-btn year-btn" onclick="dashboardFilter('year')">{{trans('file.This Year')}}</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Counts Section total-->
    <section class="dashboard-counts total">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 form-group">
                    <div class="row">
                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-archive" style="color: #6495ED"></i></div>
                                <div class="name"><strong style="color: #6495ED">Total Asset</strong></div>
                                <div class="count-number ">{{number_format((float)$total_asset_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-shopping-cart" style="color: #800080"></i></div>
                                <div class="name"><strong style="color: #800080">Asset Purchase</strong></div>
                                <div class="count-number ">{{number_format((float)$total_asset_purchase_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-cart-arrow-down" style="color: #C88141"></i></div>
                                <div class="name"><strong style="color: #C88141">Asset Sale</strong></div>
                                <div class="count-number ">{{number_format((float)$total_sale_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-up" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Asset Book Value</strong></div>
                                <div class="count-number ">{{number_format((float)$book_value, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-down" style="color: crimson"></i></div>
                                <div class="name"><strong style="color: crimson">Asset Deprication</strong></div>
                                <div class="count-number ">{{number_format((float)$deprication, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-money" style="color: #FF00FF"></i></div>
                                <div class="name"><strong style="color: #FF00FF">Asset Expense</strong></div>
                                <div class="count-number ">{{number_format((float)$expense_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-book" style="color: #733686"></i></div>
                                <div class="name"><strong style="color: #733686">PhotoCopy Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$copy_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-car" style="color: red"></i></div>
                                <div class="name"><strong style="color: red">Milage Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$automobile_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-wrench" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Repair Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$repair_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-adjust" style="color: blue"></i></div>
                                <div class="name"><strong style="color: blue">General Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$general_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-fire" style="color: #00FFFF"></i></div>
                                <div class="name"><strong style="color: #00FFFF">Asset Dispose</strong></div>
                                <div class="count-number ">{{number_format((float)$dispose_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-exchange" style="color: cornflowerblue"></i></div>
                                <div class="name"><strong style="color: cornflowerblue">Asset Transfer</strong></div>
                                <div class="count-number ">{{number_format((float)$transfer_sum, 2)}}</div>
                            </div>
                        </div>

{{--                        end--}}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Counts Section today -->
    <section class="dashboard-counts today">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 form-group">
                    <div class="row">
                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-archive" style="color: #6495ED"></i></div>
                                <div class="name"><strong style="color: #6495ED">Total Asset</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_total_asset_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-shopping-cart" style="color: #800080"></i></div>
                                <div class="name"><strong style="color: #800080">Asset Purchase</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_total_asset_purchase_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-cart-arrow-down" style="color: #C88141"></i></div>
                                <div class="name"><strong style="color: #C88141">Asset Sale</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_total_sale_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-up" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Asset Book Value</strong></div>
                                <div class="count-number ">{{number_format((float)$book_value, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-down" style="color: crimson"></i></div>
                                <div class="name"><strong style="color: crimson">Asset Deprication</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_deprication, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-money" style="color: #FF00FF"></i></div>
                                <div class="name"><strong style="color: #FF00FF">Asset Expense</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_expense_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-book" style="color: #733686"></i></div>
                                <div class="name"><strong style="color: #733686">PhotoCopy Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_copy_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-car" style="color: red"></i></div>
                                <div class="name"><strong style="color: red">Milage Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_automobile_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-wrench" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Repair Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_repair_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-adjust" style="color: blue"></i></div>
                                <div class="name"><strong style="color: blue">General Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_general_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-fire" style="color: #00FFFF"></i></div>
                                <div class="name"><strong style="color: #00FFFF">Asset Dispose</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_dispose_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-exchange" style="color: cornflowerblue"></i></div>
                                <div class="name"><strong style="color: cornflowerblue">Asset Transfer</strong></div>
                                <div class="count-number ">{{number_format((float)$daily_transfer_sum, 2)}}</div>
                            </div>
                        </div>

                        {{--                        end--}}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Counts Section month -->
    <section class="dashboard-counts month">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 form-group">
                    <div class="row">
                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-archive" style="color: #6495ED"></i></div>
                                <div class="name"><strong style="color: #6495ED">Total Asset</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_total_asset_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-shopping-cart" style="color: #800080"></i></div>
                                <div class="name"><strong style="color: #800080">Asset Purchase</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_total_asset_purchase_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-cart-arrow-down" style="color: #C88141"></i></div>
                                <div class="name"><strong style="color: #C88141">Asset Sale</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_total_sale_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-up" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Asset Book Value</strong></div>
                                <div class="count-number ">{{number_format((float)$book_value, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-down" style="color: crimson"></i></div>
                                <div class="name"><strong style="color: crimson">Asset Deprication</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_deprication, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-money" style="color: #FF00FF"></i></div>
                                <div class="name"><strong style="color: #FF00FF">Asset Expense</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_expense_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-book" style="color: #733686"></i></div>
                                <div class="name"><strong style="color: #733686">PhotoCopy Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_copy_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-car" style="color: red"></i></div>
                                <div class="name"><strong style="color: red">Milage Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_automobile_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-wrench" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Repair Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_repair_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-adjust" style="color: blue"></i></div>
                                <div class="name"><strong style="color: blue">General Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_general_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-fire" style="color: #00FFFF"></i></div>
                                <div class="name"><strong style="color: #00FFFF">Asset Dispose</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_dispose_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-exchange" style="color: cornflowerblue"></i></div>
                                <div class="name"><strong style="color: cornflowerblue">Asset Transfer</strong></div>
                                <div class="count-number ">{{number_format((float)$monthly_transfer_sum, 2)}}</div>
                            </div>
                        </div>

                        {{--                        end--}}
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Counts Section year -->
    <section class="dashboard-counts year">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12 form-group">
                    <div class="row">
                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-archive" style="color: #6495ED"></i></div>
                                <div class="name"><strong style="color: #6495ED">Total Asset</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_total_asset_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-shopping-cart" style="color: #800080"></i></div>
                                <div class="name"><strong style="color: #800080">Asset Purchase</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_total_asset_purchase_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-cart-arrow-down" style="color: #C88141"></i></div>
                                <div class="name"><strong style="color: #C88141">Asset Sale</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_total_sale_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-up" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Asset Book Value</strong></div>
                                <div class="count-number ">{{number_format((float)$book_value, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-chevron-down" style="color: crimson"></i></div>
                                <div class="name"><strong style="color: crimson">Asset Deprication</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_deprication, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-money" style="color: #FF00FF"></i></div>
                                <div class="name"><strong style="color: #FF00FF">Asset Expense</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_expense_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-book" style="color: #733686"></i></div>
                                <div class="name"><strong style="color: #733686">PhotoCopy Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_copy_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-car" style="color: red"></i></div>
                                <div class="name"><strong style="color: red">Milage Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_automobile_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-wrench" style="color: green"></i></div>
                                <div class="name"><strong style="color: green">Repair Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_repair_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-adjust" style="color: blue"></i></div>
                                <div class="name"><strong style="color: blue">General Activity</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_general_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-fire" style="color: #00FFFF"></i></div>
                                <div class="name"><strong style="color: #00FFFF">Asset Dispose</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_dispose_sum, 2)}}</div>
                            </div>
                        </div>

                        <!-- Count item widget-->
                        <div class="col-sm-3">
                            <div class="wrapper count-title text-center">
                                <div class="icon"><i class="fa fa-exchange" style="color: cornflowerblue"></i></div>
                                <div class="name"><strong style="color: cornflowerblue">Asset Transfer</strong></div>
                                <div class="count-number ">{{number_format((float)$yearly_transfer_sum, 2)}}</div>
                            </div>
                        </div>

                        {{--                        end--}}
                    </div>
                </div>
            </div>
        </div>
    </section>
<hr>
    <!-- Counts Section year -->
    <section class="dashboard-counts">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header align-items-center">
                    <h4 class="text-center">Asset Category Wise</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Count item widget-->
                        @foreach($dataa as $item)
                            @php
                                $category_assets = \App\Asset::where('category_id', $item->id)->where('is_active', 1)->get();
                                $book_value = 0;
                                foreach ($category_assets as $asset) {
                                    $calculation = \App\Asset::depricationCaluculate($asset);
                                    $book_value += $calculation['book_value'];
                                }
                            @endphp
                            <div class="col-sm-3">
                                <div class="wrapper count-title text-center">
                                    <a href="{{route('asset.dashboard.category', ['id' => $item->id])}}">
                                        <div class="icon"><i class="dripicons-bookmark" style="color: #733686"></i></div>
                                        <div class="name"><strong style="color: #733686"> {{ $item->name }}</strong></div>
                                        <div class="count-number">{{number_format((float)$book_value, 2)}}</div>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">

        // menu open
        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-dashboard-menu").addClass("active");

        $('.today').hide();
        $('.month').hide();
        $('.year').hide();

        function dashboardFilter(data){

            $('.total').hide();
            $('.today').hide();
            $('.month').hide();
            $('.year').hide();
            $('.date-btn').removeClass( 'active');
            $('.'+data+'-btn').addClass( 'active');

            if(data === 'total') {
                $('.total').show(500);
            } else if(data === 'today') {
                $('.today').show(500);
            } else if(data === 'month') {
                $('.month').show(500);
            } else if(data === 'year') {
                $('.year').show(500);
            }
        }
    </script>
@endsection
