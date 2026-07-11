@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                <div class="row mb-100">
                    <form method="POST" action="{{ route('forgot.password') }}" id="login-form">
                        @csrf
                    <div class="col-lg-1"></div>
                    <div class="col-lg-5">
                        <h3>Forgot Password</h3>
                        <p class="font-md color-gray-500">Please Enter your number, we will send your password by whatsapp!</p>
                        @if(session()->has('not_permitted'))
                            <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
                        @endif
                        <div class="form-register mt-30 mb-30">
                            <div class="form-group">
                                <label class="mb-5 font-sm color-gray-700">Whatsapp Phone Number *</label>
                                <input class="form-control" name="phone" type="text" placeholder="Whatsapp number" required>
                            </div>
                            <div class="form-group">
                                <input class="font-md-bold btn btn-buy" type="submit" value="Send Code">
                            </div>
                            <div class="mt-20"><span class="font-xs color-gray-500 font-medium">Have not an account?</span><a class="font-xs color-brand-3 font-medium" href="{{ route('shop.signup') }}">Sign Up</a></div>
                        </div>
                    </div>
                    </form>
                    <div class="col-lg-5"></div>
                </div>
            </div>
        </section>
@endsection
