@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                <div class="row mb-100">
                    <div class="col-lg-3"></div>
                    <div class="col-lg-5">
                        <h6>{{ $message }}</h6>
                        <br><br><hr>
                        <a class="btn btn-buy w-auto arrow-back mb-10" href="{{ route('service', ['products' => 12]) }}">Continue Service</a>
                    </div>
                </div>
            </div>
        </section>
@endsection
