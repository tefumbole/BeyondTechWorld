@extends('layout.main')
@section('content')
<!-- this portion is for demo only -->
<!-- <style type="text/css">

  nav.navbar a.menu-btn {
    padding: 12 !important;
  }
  .color-switcher {
      background-color: #fff;
      border: 1px solid #e5e5e5;
      border-radius: 2px;
      padding: 10px;
      position: fixed;
      top: 150px;
      transition: all 0.4s ease 0s;
      width: 150px;
      z-index: 99999;
  }
  .hide-color-switcher {
      right: -150px;
  }
  .show-color-switcher {
      right: -1px;
  }
  .color-switcher a.switcher-button {
      background: #fff;
      border-top: #e5e5e5;
      border-right: #e5e5e5;
      border-bottom: #e5e5e5;
      border-left: #e5e5e5;
      border-style: solid solid solid solid;
      border-width: 1px 1px 1px 1px;
      border-radius: 2px;
      color: #161616;
      cursor: pointer;
      font-size: 22px;
      width: 45px;
      height: 45px;
      line-height: 43px;
      position: absolute;
      top: 24px;
      left: -44px;
      text-align: center;
  }
  .color-switcher a.switcher-button i {
    line-height: 40px
  }
  .color-switcher .color-switcher-title {
      color: #666;
      padding: 0px 0 8px;
  }
  .color-switcher .color-switcher-title:after {
      content: "";
      display: block;
      height: 1px;
      margin: 14px 0 0;
      position: relative;
      width: 20px;
  }
  .color-switcher .color-list a.color {
      cursor: pointer;
      display: inline-block;
      height: 30px;
      margin: 10px 0 0 1px;
      width: 28px;
  }
  .purple-theme {
      background-color: #7c5cc4;
  }
  .green-theme {
      background-color: #1abc9c;
  }
  .blue-theme {
      background-color: #3498db;
  }
  .dark-theme {
      background-color: #34495e;
  }
</style>
<div class="color-switcher hide-color-switcher">
    <a class="switcher-button"><i class="fa fa-cog fa-spin"></i></a>
    <h5>{{trans('file.Theme')}}</h5>
    <div class="color-list">
        <a class="color purple-theme" title="purple" data-color="default.css"></a>
        <a class="color green-theme" title="green" data-color="green.css"></a>
        <a class="color blue-theme" title="blue" data-color="blue.css"></a>
        <a class="color dark-theme" title="dark" data-color="dark.css"></a>
    </div>
</div> -->
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
@if(session()->has('message'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@php
    $role = DB::table('roles')->find(Auth::user()->role_id);
    $dashboard = DB::table('permissions')->where('name', 'dashboard')->first();
    $dashboard_active = DB::table('role_has_permissions')->where([
    ['permission_id', $dashboard->id],
    ['role_id', $role->id]
    ])->first();
 @endphp
@if($dashboard_active)
@php
    $roleName = ucfirst(str_replace('_', ' ', $role->name ?? 'User'));
    $totalCustomers = \App\Customer::count();
    $totalProducts = \App\Product::count();
    $activeBookings = \App\Booking::whereIn('booking_status', [1, 2])->count();
    $activeUsers = \App\User::where('is_active', true)->count();
    $whatsappReady = !empty(config('services.whatsapp.wasender_api_key'))
        && !empty(config('services.whatsapp.wasender_session_id'));
    $lastLogin = Auth::user()->updated_at ? Auth::user()->updated_at->isToday() ? 'Today' : Auth::user()->updated_at->format('M j, Y') : 'Today';
@endphp
<div class="beyond-dashboard">
  <div class="beyond-dashboard-hero">
    <div>
      <h1>Dashboard</h1>
      <p class="welcome-line">
        Welcome back, {{ Auth::user()->email ?? Auth::user()->name }}
        <span class="beyond-role-badge">{{ $roleName }}</span>
        · Last login: {{ $lastLogin }}
      </p>
    </div>
    <div class="beyond-dashboard-actions">
      <div class="filter-toggle btn-group">
        <button class="btn btn-secondary date-btn" data-start_date="{{date('Y-m-d')}}" data-end_date="{{date('Y-m-d')}}">{{trans('file.Today')}}</button>
        <button class="btn btn-secondary date-btn" data-start_date="{{date('Y-m-d', strtotime(' -7 day'))}}" data-end_date="{{date('Y-m-d')}}">{{trans('file.Last 7 Days')}}</button>
        <button class="btn btn-secondary date-btn active" data-start_date="{{date('Y').'-'.date('m').'-'.'01'}}" data-end_date="{{date('Y-m-d')}}">{{trans('file.This Month')}}</button>
        <button class="btn btn-secondary date-btn" data-start_date="{{date('Y').'-01'.'-01'}}" data-end_date="{{date('Y').'-12'.'-31'}}">{{trans('file.This Year')}}</button>
      </div>
      @if($role->name == 'Admin' || Auth::user()->role_id <= 2)
      <a href="{{ route('setting.general') }}" class="btn btn-primary"><i class="dripicons-gear"></i> Settings</a>
      @endif
    </div>
  </div>

  <div class="row">
    <div class="col-xl-9">
      <div class="beyond-stat-grid">
        <div class="beyond-stat-card">
          <div>
            <div class="label">{{ trans('file.revenue') }}</div>
            <div class="value revenue-data">{{ number_format((float)$revenue, 2, '.', ',') }}</div>
          </div>
          <div class="beyond-stat-icon blue"><i class="dripicons-graph-bar"></i></div>
        </div>
        <div class="beyond-stat-card">
          <div>
            <div class="label">{{ trans('file.profit') }}</div>
            <div class="value profit-data">{{ number_format((float)$profit, 2, '.', ',') }}</div>
          </div>
          <div class="beyond-stat-icon green"><i class="dripicons-trophy"></i></div>
        </div>
        <div class="beyond-stat-card">
          <div>
            <div class="label">Active Bookings</div>
            <div class="value">{{ number_format($activeBookings) }}</div>
          </div>
          <div class="beyond-stat-icon gold"><i class="dripicons-calendar"></i></div>
        </div>
        <div class="beyond-stat-card">
          <div>
            <div class="label">{{ trans('file.Customer List') }}</div>
            <div class="value">{{ number_format($totalCustomers) }}</div>
          </div>
          <div class="beyond-stat-icon purple"><i class="dripicons-user-group"></i></div>
        </div>
      </div>

      <div class="beyond-panel">
        <div class="beyond-panel-header">
          <h4>Quick Access</h4>
          <p>Frequently used management tools</p>
        </div>
        <div class="beyond-quick-grid">
          @if(Auth::user()->role_id <= 2)
          <a href="{{ route('user.index') }}" class="beyond-quick-card">
            <div class="icon-wrap blue" style="background:rgba(11,63,144,.12);color:#0b3f90;"><i class="dripicons-user-group"></i></div>
            <div>
              <h5>Manage Users</h5>
              <p>View and manage system users and roles</p>
            </div>
          </a>
          @endif
          <a href="{{ route('products.index') }}" class="beyond-quick-card">
            <div class="icon-wrap" style="background:rgba(0,168,107,.12);color:#00a86b;"><i class="dripicons-list"></i></div>
            <div>
              <h5>Products</h5>
              <p>Manage inventory, pricing, and product catalog</p>
            </div>
          </a>
          <a href="{{ route('booking.index') }}" class="beyond-quick-card">
            <div class="icon-wrap" style="background:rgba(198,171,71,.18);color:#9a7b1a;"><i class="dripicons-calendar"></i></div>
            <div>
              <h5>Rental Bookings</h5>
              <p>Track rentals, returns, and booking status</p>
            </div>
          </a>
          <a href="{{ route('setting.general') }}" class="beyond-quick-card">
            <div class="icon-wrap" style="background:rgba(115,54,134,.12);color:#733686;"><i class="dripicons-gear"></i></div>
            <div>
              <h5>General Settings</h5>
              <p>Configure system title, logo, and preferences</p>
            </div>
          </a>
        </div>
      </div>
    </div>

    <div class="col-xl-3">
      <div class="beyond-panel">
        <div class="beyond-panel-header">
          <h4>System Status</h4>
          <p>Application health overview</p>
        </div>
        <div class="beyond-status-list">
          <div class="beyond-status-item">
            <div>
              <strong>Database Connection</strong>
              <span>Primary application database</span>
            </div>
            <span class="beyond-status-badge ok">Active</span>
          </div>
          <div class="beyond-status-item">
            <div>
              <strong>WhatsApp / OTP Service</strong>
              <span>WasenderAPI messaging</span>
            </div>
            <span class="beyond-status-badge {{ $whatsappReady ? 'ok' : 'info' }}">{{ $whatsappReady ? 'Operational' : 'Configure' }}</span>
          </div>
          <div class="beyond-status-item">
            <div>
              <strong>Security</strong>
              <span>Authentication and OTP verification</span>
            </div>
            <span class="beyond-status-badge info">Enabled</span>
          </div>
          <div class="beyond-status-item">
            <div>
              <strong>Active Users</strong>
              <span>Currently enabled accounts</span>
            </div>
            <span class="beyond-status-badge ok">{{ $activeUsers }}</span>
          </div>
        </div>
        <div class="beyond-profile-cta">
          <p><strong>My Profile</strong><br>Update your account details and password.</p>
          <a href="{{ route('user.profile', ['id' => Auth::id()]) }}" class="btn btn-primary btn-block">Go to Profile Settings</a>
        </div>
      </div>
    </div>
  </div>

  <div class="beyond-analytics-section">
      <!-- Counts Section -->
      <section class="dashboard-counts">
        <div class="container-fluid">
          <div class="row">
            <div class="col-md-12 form-group d-none">
              <div class="row">
                <!-- legacy count widgets kept for filter JS -->
                <div class="col-sm-3">
                  <div class="wrapper count-title text-center">
                    <div class="count-number return-data">{{number_format((float)$return, 2, '.', '')}}</div>
                  </div>
                </div>
                <div class="col-sm-3">
                  <div class="wrapper count-title text-center">
                    <div class="count-number purchase_return-data">{{number_format((float)$purchase_return, 2, '.', '')}}</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-7 mt-4">
              <div class="card line-chart-example">
                <div class="card-header d-flex align-items-center">
                  <h4>{{trans('file.Cash Flow')}}</h4>
                </div>
                <div class="card-body">
                  @php
                    if($general_setting->theme == 'default.css'){
                      $color = '#733686';
                      $color_rgba = 'rgba(115, 54, 134, 0.8)';
                    }
                    elseif($general_setting->theme == 'green.css'){
                        $color = '#2ecc71';
                        $color_rgba = 'rgba(46, 204, 113, 0.8)';
                    }
                    elseif($general_setting->theme == 'blue.css'){
                        $color = '#3498db';
                        $color_rgba = 'rgba(52, 152, 219, 0.8)';
                    }
                    elseif($general_setting->theme == 'dark.css'){
                        $color = '#34495e';
                        $color_rgba = 'rgba(52, 73, 94, 0.8)';
                    }
                  @endphp
                  <canvas id="cashFlow" data-color = "{{$color}}" data-color_rgba = "{{$color_rgba}}" data-recieved = "{{json_encode($payment_recieved)}}" data-sent = "{{json_encode($payment_sent)}}" data-month = "{{json_encode($month)}}" data-label1="{{trans('file.Payment Recieved')}}" data-label2="{{trans('file.Payment Sent')}}"></canvas>
                </div>
              </div>
            </div>
            <div class="col-md-5 mt-4">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{date('F')}} {{date('Y')}}</h4>
                </div>
                <div class="pie-chart mb-2">
                    <canvas id="transactionChart" data-color = "{{$color}}" data-color_rgba = "{{$color_rgba}}" data-revenue={{$revenue}} data-purchase={{$purchase}} data-expense={{$expense}} data-label1="{{trans('file.Purchase')}}" data-label2="{{trans('file.revenue')}}" data-label3="{{trans('file.Expense')}}" width="100" height="95"> </canvas>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="container-fluid">
          <div class="row">
            <div class="col-md-12">
              <div class="card">
                <div class="card-header d-flex align-items-center">
                  <h4>{{trans('file.yearly report')}}</h4>
                </div>
                <div class="card-body">
                  <canvas id="saleChart" data-sale_chart_value = "{{json_encode($yearly_sale_amount)}}" data-purchase_chart_value = "{{json_encode($yearly_purchase_amount)}}" data-label1="{{trans('file.Purchased Amount')}}" data-label2="{{trans('file.Sold Amount')}}"></canvas>
                </div>
              </div>
            </div>
            <div class="col-md-7">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{trans('file.Recent Transaction')}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{trans('file.latest')}} 5</div>
                  </div>
                </div>
                <ul class="nav nav-tabs" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" href="#sale-latest" role="tab" data-toggle="tab">{{trans('file.Sale')}}</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#purchase-latest" role="tab" data-toggle="tab">{{trans('file.Purchase')}}</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#quotation-latest" role="tab" data-toggle="tab">{{trans('file.Quotation')}}</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="#payment-latest" role="tab" data-toggle="tab">{{trans('file.Payment')}}</a>
                  </li>
                </ul>

                <div class="tab-content">
                  <div role="tabpanel" class="tab-pane fade show active" id="sale-latest">
                      <div class="table-responsive">
                        <table class="table">
                          <thead>
                            <tr>
                              <th>{{trans('file.date')}}</th>
                              <th>{{trans('file.reference')}}</th>
                              <th>{{trans('file.customer')}}</th>
                              <th>{{trans('file.status')}}</th>
                              <th>{{trans('file.grand total')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($recent_sale as $sale)
                            <?php $customer = DB::table('customers')->find($sale->customer_id); ?>
                            <tr>
                              <td>{{ date($general_setting->date_format, strtotime($sale->created_at->toDateString())) }}</td>
                              <td>{{$sale->reference_no}}</td>
                              <td>{{$customer->name}}</td>
                              @if($sale->sale_status == 1)
                              <td><div class="badge badge-success">{{trans('file.Completed')}}</div></td>
                              @elseif($sale->sale_status == 2)
                              <td><div class="badge badge-danger">{{trans('file.Pending')}}</div></td>
                              @else
                              <td><div class="badge badge-warning">{{trans('file.Draft')}}</div></td>
                              @endif
                              <td>{{$sale->grand_total}}</td>
                            </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                  </div>
                  <div role="tabpanel" class="tab-pane fade" id="purchase-latest">
                      <div class="table-responsive">
                        <table class="table">
                          <thead>
                            <tr>
                              <th>{{trans('file.date')}}</th>
                              <th>{{trans('file.reference')}}</th>
                              <th>{{trans('file.Supplier')}}</th>
                              <th>{{trans('file.status')}}</th>
                              <th>{{trans('file.grand total')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($recent_purchase as $purchase)
                            <?php $supplier = DB::table('suppliers')->find($purchase->supplier_id); ?>
                            <tr>
                              <td>{{date($general_setting->date_format, strtotime($purchase->created_at->toDateString())) }}</td>
                              <td>{{$purchase->reference_no}}</td>
                              @if($supplier)
                                <td>{{$supplier->name}}</td>
                              @else
                                <td>N/A</td>
                              @endif
                              @if($purchase->status == 1)
                              <td><div class="badge badge-success">Recieved</div></td>
                              @elseif($purchase->status == 2)
                              <td><div class="badge badge-success">Partial</div></td>
                              @elseif($purchase->status == 3)
                              <td><div class="badge badge-danger">Pending</div></td>
                              @else
                              <td><div class="badge badge-danger">Ordered</div></td>
                              @endif
                              <td>{{$purchase->grand_total}}</td>
                            </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                  </div>
                  <div role="tabpanel" class="tab-pane fade" id="quotation-latest">
                      <div class="table-responsive">
                        <table class="table">
                          <thead>
                            <tr>
                              <th>{{trans('file.date')}}</th>
                              <th>{{trans('file.reference')}}</th>
                              <th>{{trans('file.customer')}}</th>
                              <th>{{trans('file.status')}}</th>
                              <th>{{trans('file.grand total')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($recent_quotation as $quotation)
                            <?php $customer = DB::table('customers')->find($quotation->customer_id); ?>
                            <tr>
                              <td>{{date($general_setting->date_format, strtotime($quotation->created_at->toDateString())) }}</td>
                              <td>{{$quotation->reference_no}}</td>
                              <td>{{$customer->name}}</td>
                              @if($quotation->quotation_status == 1)
                              <td><div class="badge badge-danger">Pending</div></td>
                              @else
                              <td><div class="badge badge-success">Sent</div></td>
                              @endif
                              <td>{{$quotation->grand_total}}</td>
                            </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                  </div>
                  <div role="tabpanel" class="tab-pane fade" id="payment-latest">
                      <div class="table-responsive">
                        <table class="table">
                          <thead>
                            <tr>
                              <th>{{trans('file.date')}}</th>
                              <th>{{trans('file.reference')}}</th>
                              <th>{{trans('file.Amount')}}</th>
                              <th>{{trans('file.Paid By')}}</th>
                            </tr>
                          </thead>
                          <tbody>
                            @foreach($recent_payment as $payment)
                            <tr>
                              <td>{{date($general_setting->date_format, strtotime($payment->created_at->toDateString())) }}</td>
                              <td>{{$payment->payment_reference}}</td>
                              <td>{{$payment->amount}}</td>
                              <td>{{$payment->paying_method}}</td>
                            </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-5">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{trans('file.Best Seller').' '.date('F')}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{trans('file.top')}} 5</div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                      <thead>
                        <tr>
                          <th>SL No</th>
                          <th>{{trans('file.Product Details')}}</th>
                          <th>{{trans('file.qty')}}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($best_selling_qty as $key=>$sale)
                        <?php $product = DB::table('products')->find($sale->product_id); ?>
                        <tr>
                          <td>{{$key + 1}}</td>
                          <td>{{@$product->name}}<br>[{{@$product->code}}]</td>
                          <td>{{$sale->sold_qty}}</td>
                        </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{trans('file.Best Seller').' '.date('Y'). '('.trans('file.qty').')'}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{trans('file.top')}} 5</div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                      <thead>
                        <tr>
                          <th>SL No</th>
                          <th>{{trans('file.Product Details')}}</th>
                          <th>{{trans('file.qty')}}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($yearly_best_selling_qty as $key => $sale)
                        <?php $product = DB::table('products')->find($sale->product_id); ?>
                        <tr>
                          <td>{{$key + 1}}</td>
                          <td>{{@$product->name}}<br>[{{@$product->code}}]</td>
                          <td>{{$sale->sold_qty}}</td>
                        </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <h4>{{trans('file.Best Seller').' '.date('Y') . '('.trans('file.price').')'}}</h4>
                  <div class="right-column">
                    <div class="badge badge-primary">{{trans('file.top')}} 5</div>
                  </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                      <thead>
                        <tr>
                          <th>SL No</th>
                          <th>{{trans('file.Product Details')}}</th>
                          <th>{{trans('file.grand total')}}</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($yearly_best_selling_price as $key => $sale)
                        <?php $product = DB::table('products')->find($sale->product_id); ?>
                        <tr>
                          <td>{{$key + 1}}</td>
                          <td>{{$product->name}}<br>[{{$product->code}}]</td>
                          <td>{{number_format((float)$sale->total_price, 2, '.', '')}}</td>
                        </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
              </div>
            </div>
          </div>
        </div>
      </section>
  </div>
</div>
@endif

<script type="text/javascript">
    // Show and hide color-switcher
    $(".color-switcher .switcher-button").on('click', function() {
        $(".color-switcher").toggleClass("show-color-switcher", "hide-color-switcher", 300);
    });

    // Color Skins
    $('a.color').on('click', function() {
        /*var title = $(this).attr('title');
        $('#style-colors').attr('href', 'css/skin-' + title + '.css');
        return false;*/
        $.get('setting/general_setting/change-theme/' + $(this).data('color'), function(data) {
        });
        var style_link= $('#custom-style').attr('href').replace(/([^-]*)$/, $(this).data('color') );
        $('#custom-style').attr('href', style_link);
    });

    $(".date-btn").on("click", function() {
        $(".date-btn").removeClass("active");
        $(this).addClass("active");
        var start_date = $(this).data('start_date');
        var end_date = $(this).data('end_date');
        $.get('dashboard-filter/' + start_date + '/' + end_date, function(data) {
            dashboardFilter(data);
        });
    });

    function dashboardFilter(data){
        $('.revenue-data').hide();
        $('.revenue-data').html(parseFloat(data[0]).toFixed(2));
        $('.revenue-data').show(500);

        $('.return-data').hide();
        $('.return-data').html(parseFloat(data[1]).toFixed(2));
        $('.return-data').show(500);

        $('.profit-data').hide();
        $('.profit-data').html(parseFloat(data[2]).toFixed(2));
        $('.profit-data').show(500);

        $('.purchase_return-data').hide();
        $('.purchase_return-data').html(parseFloat(data[3]).toFixed(2));
        $('.purchase_return-data').show(500);
    }
</script>
@endsection
