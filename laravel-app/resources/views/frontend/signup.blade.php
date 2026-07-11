@extends('frontend.layout.main')
@section('content')
<style>
    .input-group-text {
        padding: 0.9rem 0.75rem;
    }
</style>
    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                <div class="row mb-100">
                    <form method="POST" action="{{ route('shop.signup') }}" id="login-form">
                        @csrf
                    <div class="col-lg-12">
                        <h3>Create An Account</h3>
{{--                        <p class="font-md color-gray-500">Welcome back!</p>--}}
                        @if(session()->has('not_permitted'))
                            <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
                        @endif
                        <div class="form-register mt-30 mb-30">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">Username *</label>
                                    <input class="form-control" name="name" type="text" placeholder="user name*">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">Email </label>
                                    <input class="form-control" name="email" type="text" placeholder="email@gmail.com">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <div class="col-auto">
                                        <label class="mb-5 font-sm color-gray-700">Phone *</label>
                                        <div class="input-group mb-2">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">+237</div>
                                            </div>
                                            <input oninvalid="this.setCustomValidity('Please enter complete 9 digit number')"
                                                   oninput="this.setCustomValidity('')"
                                                   onkeypress="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                                   type="text" minlength="9" maxlength="9" class="form-control" name="phone" value="" placeholder="Phone*" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">Password *</label>
                                    <input class="form-control" name="password" type="password" placeholder="******************">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">Confirm Password *</label>
                                    <input class="form-control" name="confirm_password" type="password" placeholder="******************">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">City</label>
                                    <input class="form-control" name="city" type="text" placeholder="city">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">State</label>
                                    <input class="form-control" name="state" type="text" placeholder="state">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">Country</label>
                                    <input class="form-control" name="country" type="text" placeholder="Country">
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-5 font-sm color-gray-700">Address *</label>
                                    <input class="form-control" name="address" type="text" placeholder="Address" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <input class="font-md-bold btn btn-buy" type="submit" value="Sign In">
                            </div>
                            <div class="mt-20"><span class="font-xs color-gray-500 font-medium">Already have an account?</span><a class="font-xs color-brand-3 font-medium" href="{{ route('shop.login') }}"> Login</a></div>
                        </div>
                        </div>
                    </div>
                    </form>
                    <div class="col-lg-5"></div>
                </div>
            </div>
        </section>
@endsection
