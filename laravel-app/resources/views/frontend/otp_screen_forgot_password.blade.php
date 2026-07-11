@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                @if($errors->has('name'))
                    <div class="alert alert-danger alert-dismissible text-center">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
                @endif
                @if(session()->has('not_permitted'))
                    <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
                @endif
                <div class="row mb-100">
                    <form method="POST" action="{{ route('otp.verify.password') }}" id="login-form">
                        @csrf
                    <div class="col-lg-3"></div>
                    <div class="col-lg-5">
                        <h3>Whatsapp OTP Verify</h3>
                        <p class="font-md color-gray-500">Please verify whatsapp otp to receive your new password...!</p>
                        <div class="form-register mt-30 mb-30">
                            <div class="form-group">
                                <label class="mb-5 font-sm color-gray-700">OTP *</label>
                                <input class="form-control" name="otp" type="text" placeholder="******" required>
                            </div>
                            <div class="form-group">
                                <input class="font-md-bold btn btn-buy" type="submit" value="Verify">
                            </div>
                        </div>
                    </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
@endsection
