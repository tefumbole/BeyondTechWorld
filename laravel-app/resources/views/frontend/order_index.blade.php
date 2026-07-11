@extends('frontend.layout.main')
@section('content')
    <main class="main">
        <div class="section-box">
            <div class="breadcrumbs-div">
                <div class="container">
                    <ul class="breadcrumb">
                        <li><a class="font-xs color-gray-1000" href="/">Home</a></li>
                        <li><a class="font-xs color-gray-500" href="{{ route('shop', ['products' => 12]) }}">Shop</a></li>
                        <li><a class="font-xs color-gray-500" href="#">Order</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <section class="section-box shop-template">
            <div class="container">
                @if(session()->has('message'))
                    <div class="alert alert-success alert-dismissible text-center">{{ session()->get('message') }}</div>
                @endif
                <div class="row cart-details">
                    <div class="col-lg-12">
                        <div class="tab-content mt-30">
                            <div class="tab-pane fade active show" id="tab-orders" role="tabpanel" aria-labelledby="tab-orders">

                                @foreach($data as $item)
                                <div class="box-orders">
                                    <div class="head-orders">
                                        <div class="head-left">
                                            <h5 class="mr-20">Order ID: #{{ $item->id }}</h5><span class="font-md color-brand-3 mr-20">Date: {{ $item->created_at->format('d M, Y')}}</span>
                                            Order Status:
                                            @if($item->order_status == 0)
                                                <span class="label-delivery">Pending</span>
                                            @elseif($item->order_status == 1)
                                                <td><span class="label-delivery label-delivered">Complete</span></td>
                                                @if($item->order_received == 1)
                                                    <td><span class="label-delivery label-delivered">Delivered</span></td>
                                                @else
                                                    <a class="mx-5" href="{{ route('order.received', $item->id) }}" onclick="return confirm('Are you sure you have received order?');"><h4>Received?</h4></a>
                                                @endif
                                            @elseif($item->order_status == 2)
                                                <td><span class="label-delivery label-cancel">Rejected</span></td>
                                            @elseif($item->order_status == 3)
                                                <td><span class="label-delivery">Ready For Delivery</span></td>
                                            @endif
                                            &nbsp; &nbsp; &nbsp; &nbsp; Payment Status:
                                            @if($item->payment_status == 0)
                                                <td><span class="label-delivery">Pending</span></td>
                                            @elseif($item->payment_status == 1)
                                                <td><span class="label-delivery label-delivered">Complete</span></td>
                                            @elseif($item->payment_status == 2)
                                                <td><span class="label-delivery label-cancel">Rejected</span></td>
                                                <a class="mx-5" href="{{ route('order.payment', $item->id) }}" onclick="return confirm('Are you sure you want payment again?');"><p>Are you want to payment again?</p></a>
                                            @elseif($item->order_status == 3)
                                                <td><span class="label-delivery">Ready For Delivery</span></td>
                                            @endif
                                        </div>
                                        <div class="head-right"><a class="btn btn-buy font-sm-bold w-auto" href="{{ route('order.invoice', ['id' => $item->id]) }}">Invoice <span class="fa fa-download"></span></a></div>
                                    </div>
                                    <div class="body-orders">
                                        <div class="list-orders">

                                            @foreach($item->orderProducts as $orderProduct)
                                                @php
                                                    $product_image = explode(",", $orderProduct->product->image);
                                                    $product_image = htmlspecialchars($product_image[0]);
                                                @endphp
                                            <div class="item-orders">
                                                <div class="image-orders"><img src="{{ url('public/images/product', $product_image) }}" alt="product image"></div>
                                                <div class="info-orders">
                                                    <h5>{{ $orderProduct->product->name }}</h5>
                                                    <hr>
                                                    <h6><a href="{{ route('product', ['id' => $orderProduct->product->id]) }}">Review this Product</a></h6>
                                                </div>
                                                <div class="quantity-orders">
                                                    <h5>Quantity: {{ $orderProduct->quantity }}</h5>
                                                </div>
                                                <div class="quantity-orders">
                                                    <h3>{{ number_format($orderProduct->price, 2) }} {{ $currency->code }}</h3>
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                        <div class="head-right" style="float: right"><a class="btn btn-border font-sm-bold w-auto">Total: {{ number_format($item->grand_total, 2) . ' ' . $currency->code}}</a></div>
                                    </div>
                                </div>
                                @endforeach
                                <nav>
                                    <ul class="pagination">
                                        {{ $data->links() }}
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
