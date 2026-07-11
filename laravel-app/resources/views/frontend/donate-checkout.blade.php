@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <div class="section-box">
            <div class="breadcrumbs-div">
                <div class="container">
                    <ul class="breadcrumb">
                        <li><a class="font-xs color-gray-1000" href="/">Home</a></li>
                        <li><a class="font-xs color-gray-500" href="{{ route('shop', ['products' => 12]) }}">Shop</a></li>
                        <li><a class="font-xs color-gray-500" href="#">Checkout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <section class="section-box shop-template">
            <div class="container">
                <div class="row">
                    @if(session()->has('not_permitted'))
                        <div class="alert alert-danger alert-dismissible text-center">{{ session()->get('not_permitted') }}</div>
                    @endif

                        <div class="col-lg-3"></div>
                    <div class="col-lg-6">
                        <form method="post" action="{{ route('donate.store') }}">
                        @csrf
                        <div class="box-border">
                            <div class="box-payment"><a class="btn btn-gpay"><img src="{{ asset('public/assets/imgs/page/checkout/mtn.png') }}" alt="Ecom" width="170px"></a>
{{--                                <a class="btn btn-paypal"><img src="{{ asset('public/assets/imgs/page/checkout/paypal.svg') }}" alt="Ecom" width="170px"></a>--}}
{{--                                <a class="btn btn-amazon"><img src="{{ asset('public/assets/imgs/page/checkout/cod.svg') }}" alt="COD" width="170px"></a>--}}
                            </div>
                            <div class="border-bottom-4 text-center mb-20">
                                <div class="text-or font-md color-gray-500">Or</div>
                            </div>
                            <div class="row">
                                <div class="col-lg-6 col-sm-6 mb-20">
                                    <h5 class="font-md-bold color-brand-3 text-sm-start text-center">Whatsapp Number</h5>
                                </div>
                                @if(!auth()->user())
                                    <div class="col-lg-6 col-sm-6 mb-20 text-sm-end text-center"><span class="font-sm color-brand-3">Already have an account?</span><a class="font-sm color-brand-1" href="{{ route('shop.login') }}"> Login</a></div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <div class="col-auto">
                                            <label class="sr-only" for="inlineFormInputGroup">Username</label>
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
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <input class="form-control font-sm"  name="name" type="text" placeholder="name*" required>
                                        <input name="email" type="hidden">
                                        <input name="city" type="hidden">
                                        <input name="state" type="hidden">
                                        <input name="address" type="hidden">
                                        <input name="product_id" type="hidden" value="{{ $product->id }}">
                                    </div>
                                </div>
                                @else
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <input class="form-control font-sm"  name="phone" value="{{ auth()->user() ? auth()->user()->phone : '+237' }}" type="text" placeholder="Phone*" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <input class="form-control font-sm"  name="name" value="{{ auth()->user() ? auth()->user()->name : '' }}" type="text" placeholder="name*" required>
                                            <input name="email" type="hidden">
                                            <input name="city" type="hidden">
                                            <input name="state" type="hidden">
                                            <input name="address" type="hidden">
                                            <input name="product_id" type="hidden" value="{{ $product->id }}">
                                        </div>
                                    </div>
                                @endif
                                <div class="col-lg-12">
                                    <div class="box-border">
                                        <h5 class="font-md-bold mb-20">Your Donation Info</h5>
                                        <div class="form-group mb-0">
                                            <div class="row mb-10">
                                                <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">You can change donation amount:</span></div>
                                                <div class="col-lg-6 col-6">
                                                    <h3 class="color-brand-3 price-main d-inline-block mr-10"><input type="number" name="donation_amount" class="form-control" value="{{$product->price}}"></h3>
                                                    {{--                                        <span class="font-lg-bold color-brand-3">{{ number_format($product->price, 2) }} {{ $currency->code }}</span>--}}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <h5 class="font-md-bold color-brand-3 text-sm-start text-center my-3">Payment Method</h5>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group">
                                        <div class="form-group form-check">
                                            <input type="checkbox" class="form-check-input" id="mtn-diff">
                                            <label class="form-check-label" for="mtn-diff">Is MTN number differnet from whatsapp</label>
                                        </div>
                                        <div class="input-group mb-2 d-none mtn-number">
                                            <div class="input-group-prepend">
                                                <div class="input-group-text">+237</div>
                                            </div>
                                            <input oninvalid="this.setCustomValidity('Please enter complete 9 digit number')"
                                                   oninput="this.setCustomValidity('')"
                                                   onkeypress="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\..*?)\..*/g, '$1');"
                                                   type="text" minlength="9" maxlength="9" class="form-control" name="mtn_phone" value="" placeholder="MTN Phone Number*">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-group mb-0">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="payment_method" value="MTN" id="mtn" required checked>
                                            <label class="form-check-label" for="mtn">Mobile Money</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-20">
                            <div class="col-lg-6 col-5 mb-20"><a class="btn font-sm-bold color-brand-1 arrow-back-1" href="{{ route('donation', ['products'=> 12]) }}">Return to Donation List</a></div>
                            <div class="col-lg-6 col-7 mb-20 text-end"><button type="submit" class="btn btn-buy w-auto arrow-next" >Donate</button></div>
                        </div>
                    </div>

                        </form>
                </div>
            </div>
        </section>
@endsection
