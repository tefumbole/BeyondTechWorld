@extends('frontend.layout.main')
@section('content')
    <main class="main">
        <div class="section-box">
            <div class="breadcrumbs-div mb-0">
                <div class="container">
                    <ul class="breadcrumb">
                        <li><a class="font-xs color-gray-1000" href="/">Home</a></li>
                        <li><a class="font-xs color-gray-500" href="">Contact</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <section class="section-box shop-template mt-0">
            <div class="container">
                <div class="box-contact">
                    <div class="row">
                        @if(session()->has('not_permitted'))
                            <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
                        @endif
                        @if(session()->has('message'))
                            <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
                        @endif
                        <div class="col-lg-6">
                            <form method="post" action="{{ route('contact.message') }}">
                                @csrf
                                <div class="contact-form">
                                    <h3 class="color-brand-3 mt-60">Contact Us</h3>
                                    <p class="font-sm color-gray-700 mb-30">Our team would love to hear from you!</p>
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input class="form-control" name="phone" type="tel" placeholder="Phone number *" required>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <textarea class="form-control" name="message" placeholder="Message" rows="10"></textarea>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input class="btn btn-buy w-auto" type="submit" value="Send message">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-lg-6">
                            <div class="map">
                                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d8103648.681833592!2d12.294004100000002!3d7.369617500000001!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x10613753703e0f21%3A0x2b03c44599829b53!2sCameroon!5e0!3m2!1sen!2s!4v1695987898647!5m2!1sen!2s" height="550" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-contact-address pt-80 pb-50">
                    <div class="row">
                        <div class="col-lg-3 mb-30">
                            <h3 class="mb-5">Visit our stores</h3>
                            <p class="font-sm color-gray-700 mb-30">Find us at these locations</p><a class="btn btn-buy w-auto">View map</a>
                        </div>
                        <div class="col-lg-3">
                            <div class="mb-30">
                                <h4>Beyond Enterprise</h4>
                                <p class="font-sm color-gray-700">Kigali, <br>Rwanda</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-contact-support pt-80 pb-50 background-gray-50">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-3 mb-30 text-center text-lg-start">
                            <h3 class="mb-5">We‘d love to here from you</h3>
                            <p class="font-sm color-gray-700">Chat with our friendly team</p>
                        </div>
                        <div class="col-lg-3 text-center mb-30">
                            <div class="box-image mb-20"><img src="{{ asset('public/assets/imgs/page/contact/chat.svg') }}" alt="Ecom"></div>
                            <h4 class="mb-5">Chat to sales</h4>
                            <p class="font-sm color-gray-700 mb-5">Speak to our team.</p><a class="font-sm color-gray-900" href="mailto:info@beyondtechworld.com">info@beyondtechworld.com</a>
                        </div>
                        <div class="col-lg-3 text-center mb-30">
                            <div class="box-image mb-20"><img src="{{ asset('public/assets/imgs/page/contact/call.svg') }}" alt="Ecom"></div>
                            <h4 class="mb-5">Call us</h4>
                            <p class="font-sm color-gray-700 mb-5">Mon-Fri from 8am to 5pm</p><a class="font-sm color-gray-900" href="tel:+237675321739">+237675321739</a>
                        </div>
                        <div class="col-lg-3 text-center mb-30">
                            <div class="box-image mb-20"><img src="{{ asset('public/assets/imgs/page/contact/map.svg') }}" alt="Ecom"></div>
                            <h4 class="mb-5">Visit us</h4>
                            <p class="font-sm color-gray-700 mb-5">Visit our office</p><span class="font-sm color-gray-900">Kigali, <br>Rwanda</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
@endsection
