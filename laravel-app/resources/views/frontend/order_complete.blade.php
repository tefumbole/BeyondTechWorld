@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                <div class="row mb-100">
                    <div class="col-lg-3"></div>
                    <div class="col-lg-5">
                        <h3>{{ $message }}</h3>
                        <p class="font-md color-gray-500">
                            Dear {{ $user->name }}! Your order has been placed, Your order tracking ID is <b class="text-danger">{{ $order->id }}</b>.
                        </p><br>
                        <h6>Thank you.</h6>
                        <div class="head-right"><a class="btn btn-buy font-sm-bold w-auto" href="{{ route('order.invoice', ['id' => $order->id]) }}">Invoice <span class="fa fa-download"></span></a></div>
                        <br><br><hr>
                        <a class="btn btn-buy w-auto arrow-back mb-10" href="{{ route('shop', ['products' => 12]) }}">Continue shopping</a>
                    </div>
                </div>
            </div>
        </section>
@endsection
