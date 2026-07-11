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
                <div class="tab-pane fade active show" id="tab-order-tracking" role="tabpanel" aria-labelledby="tab-order-tracking">
                    <p class="font-md color-gray-600">To track your order please enter your OrderID in the box below and press "Track" button. This was given to you on<br class="d-none d-lg-block">your receipt and in the confirmation email you should have received.</p>
                    <div class="row mt-30">
                        <div class="col-lg-6">
                            <div class="form-tracking">
                                <form action="{{ route('order.status') }}" method="post">
                                    @csrf
                                    <div class="d-flex">
                                        <div class="form-group box-input">
                                            <input class="form-control" type="text" value="{{ @$order_status->id }}" name="id" placeholder="order id">
                                        </div>
                                        <div class="form-group box-button">
                                            <button class="btn btn-buy font-md-bold" type="submit">Tracking Now</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="border-bottom mb-20 mt-20"></div>
                    @if(isset($message))

                    <h3 class="mb-10">Order Status:<span class="color-danger"> {{ $message }}</span></h3>
                        <br><br><hr><br><br><br>
                    @endif
                    @if(isset($order_status))
                        @if($order_status->order_status == 0)
                                <h3 class="mb-10">Order Status:<span class="color-warning"> Pending </span></h3>
                        @elseif($order_status->order_status == 1)
                                <h3 class="mb-10">Order Status:<span class="color-success"> Completed </span></h3>
                        @elseif($order_status->order_status == 2)
                                <h3 class="mb-10">Order Status:<span class="color-danger"> Cancel </span></h3>
                        @elseif($order_status->order_status == 3)
                            <h3 class="mb-10">Order Status:<span class="color-warning"> Ready For Delivery </span></h3>
                        @endif
                        </h3>
{{--                        <h6 class="color-gray-500">Estimated Delivery Date: 27 August - 29 August</h6>--}}
                        <div class="table-responsive">
                            <div class="list-steps">
                                <div class="item-step">
                                    <div class="rounded-step">
                                        <div class="icon-step step-1 active"></div>
                                        <h6 class="mb-5">Order Placed</h6>
{{--                                        <p class="font-md color-gray-500">15 August 2022</p>--}}
                                    </div>
                                </div>
                                <div class="item-step">
                                    <div class="rounded-step">
                                        <div class="icon-step step-2 active"></div>
                                        <h6 class="mb-5">In Progress</h6>
{{--                                        <p class="font-md color-gray-500">16 August 2022</p>--}}
                                    </div>
                                </div>
                                <div class="item-step">
                                    <div class="rounded-step">
                                        <div class="icon-step step-3 {{ $order_status->payment_status == 1 ? "active" : "" }}"></div>
                                        <h6 class="mb-5">Payment</h6>
{{--                                        <p class="font-md color-gray-500">17 August 2022</p>--}}
                                    </div>
                                </div>
                                <div class="item-step">
                                    <div class="rounded-step">
                                        @php
                                            $active = 0;
                                            if ($order_status->order_status == 3 || $order_status->order_status == 1) {
                                                $active = 'active';
                                            }
                                         @endphp
                                        <div class="icon-step step-4 {{ $active }}"></div>
                                        <h6 class="mb-5">Ready for Deilvery</h6>
                                        @if($order_status->order_status == 3 || $order_status->order_status == 1)
                                        <p class="font-md color-gray-500">Expected Delivery Date:<br> {{ $order_status->delivery_date }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="item-step">
                                    <div class="rounded-step">
                                        <div class="icon-step step-5 {{ $order_status->order_status == 1 ? "active" : "" }}"></div>
                                        <h6 class="mb-5">Completed</h6>
{{--                                        <p class="font-md color-gray-500">19 August 2022</p>--}}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <br><br><hr><br><br><br>
                    @endif
                    <h3>Store Location</h3>
                    <div class="map-account">
                        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d8103648.681833592!2d12.294004100000002!3d7.369617500000001!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x10613753703e0f21%3A0x2b03c44599829b53!2sCameroon!5e0!3m2!1sen!2s!4v1695987898647!5m2!1sen!2s"  style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <p class="color-gray-500 mb-20">Maecenas porttitor augue sit amet nibh venenatis bibendum. Morbi lorem elit, fringilla quis libero vitae, tincidunt commodo purus. Quisque diam nisi, tincidunt sed vehicula nec, fermentum vitae lectus. Curabitur sit amet sagittis libero. Pellentesque cursus turpis at ipsum luctus tempor.</p>
                        </div>
                        <div class="col-lg-6">
                            <p class="color-gray-500 mb-20">Ut auctor varius nisl, scelerisque dictum justo maximus ut. Fusce rhoncus, augue sed molestie consectetur, leo felis ultricies erat, nec lobortis enim dui eu justo. Pellentesque aliquam hendrerit venenatis. Integer efficitur bibendum lectus sed sollicitudin. Suspendisse faucibus posuere euismod.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        function updateCart(id,url)
        {
            event.preventDefault();
            $('.preloader-active').css('display','block');
            quantity = 1
            $.ajax({
                url: url,
                type: 'get',
                data: {
                    id: parseInt(id)
                },
                dataType: 'JSON',
                success: function () {
                    $(".cart-details").load("/cart"+" .cart-details>*","");
                    $(".icon-cart").load("/"+" .icon-cart>*","");
                    $('.preloader-active').css('display','none');
                }
            });
        }
    </script>
@endsection
