@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                <div class="row mb-100">
                    <form method="POST" action="{{ route('shop.password.change') }}" id="login-form">
                        @csrf
                    <div class="col-lg-1"></div>
                    <div class="col-lg-5">
                        <h3>Member Password Change</h3>
                        <p class="font-md color-gray-500">PLease enter your new password and confirm password!</p>
                        @if(session()->has('not_permitted'))
                            <div class="alert alert-danger alert-dismissible text-center"></button>{{ session()->get('not_permitted') }}</div>
                        @endif
                        @if(session()->has('success'))
                            <div class="alert alert-success alert-dismissible text-center"></button>{{ session()->get('success') }}</div>
                        @endif
                        <div class="form-register mt-30 mb-30">
                            <div class="form-group">
                                <label class="mb-5 font-sm color-gray-700">Password *</label>
                                <input class="form-control" name="password" type="password" placeholder="******************">
                            </div>
                            <div class="form-group">
                                <label class="mb-5 font-sm color-gray-700">Confirm Password *</label>
                                <input class="form-control" name="confirm_password" type="password" placeholder="******************">
                            </div>
                            <div class="form-group">
                                <input class="font-md-bold btn btn-buy" type="submit" value="Submit">
                            </div>
                            <div class="mt-20"><span class="font-xs color-gray-500 font-medium">Have not an account?</span><a class="font-xs color-brand-3 font-medium" href="{{ route('shop.signup') }}"> Sign Up</a></div>
                        </div>
                    </div>
                    </form>
                    <div class="col-lg-5"></div>
                </div>
            </div>
        </section>
@endsection
