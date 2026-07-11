<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="msapplication-TileColor" content="#0E0E0E">
    <meta name="template-color" content="#0E0E0E">
    <meta name="description" content="Beyond Enterprise — IT consultancy, networking, CCTV security, and professional audio-visual solutions.">
    <meta name="keywords" content="Beyond Enterprise, IT, networking, CCTV, security, audio visual, Kigali, Rwanda">
    <meta name="author" content="Beyond Enterprise">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="shortcut icon" type="image/x-icon" href="{{url('public/logo', $general_setting->site_logo)}}">
    <link href="{{ asset('public/assets/css/style.css?v=3.0.1') }}"  rel="stylesheet">
    <link href="{{ asset('public/assets/css/custom_style.css?v=3.0.1') }}"  rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css"></noscript>
    @if(Route::currentRouteName() === 'frontend.home')
        <style>#preloader-active{display:none!important}</style>
    @endif
    <title>{{ $general_setting->site_title }}</title>
</head>
<body>
<div id="preloader-active">
    <div class="preloader d-flex align-items-center justify-content-center">
        <div class="preloader-inner position-relative">
            <div class="text-center"><img class="mb-10" src="{{url('public/logo', $general_setting->site_logo)}}" alt="{{ $general_setting->site_title }}" width="120" height="40" decoding="async">
                <div class="preloader-dots"></div>
            </div>
        </div>
    </div>
</div>
<div class="box-notify">
    <div class="container position-relative">
        <div class="row">
            <div class="col-lg-4"><span class="notify-text color-white">Save 5% on Orders above 200,000</span></div>
            <div class="col-lg-4"><span class="notify-text color-white">Premium brand products for an affordable price</span></div>
            <div class="col-lg-4"><span class="notify-text color-white">Flexible Return Policy</span></div>
        </div><a class="btn btn-close"></a>
    </div>
</div>
<div class="topbar">
    <div class="container-topbar">
        <div class="menu-topbar-left d-none d-xl-block">
            <ul class="nav-small">
{{--                <li><a class="font-xs" href="">About Us</a></li>--}}
{{--                <li><a class="font-xs" href="">Careers</a></li>--}}
                @if(!auth()->user())
                <li><a class="font-md-bold" href="{{ route('create.shop') }}">Open a shop</a></li>
                @endif
            </ul>
        </div>
        <div class="info-topbar text-center d-none d-xl-block"><span class="font-xs color-brand-3">Free shipping for all orders over</span><span class="font-sm-bold color-success"> 200,000 CFA</span></div>
        <div class="menu-topbar-right"><span class="font-xs color-brand-3">Need help? Call Us:</span><span class="font-sm-bold color-success"><a href="tel:+237675321739"> +237675321739</a></span>
        </div>
    </div>
</div>
<header class="header sticky-bar">
    <div class="container">
        <div class="main-header">
            <div class="header-left">
                <div class="header-logo"><a class="d-flex" href="/"><img alt="{{ $general_setting->site_title }}" src="{{url('public/logo', $general_setting->site_logo)}}" width="140" height="48" decoding="async"></a></div>
                <div class="header-search">
                    <div class="box-header-search">
                        <form class="form-search" method="get" action="{{ route('frontend.product.search') }}">
                            @csrf
                            <div class="box-category">
                                <select name="category" class="select-active select2-hidden-accessible">
                                    <option value="0">All categories</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="box-keysearch">
                                <input class="form-control font-xs" type="text" name="search" value="" placeholder="Search for items">
                            </div>
                        </form>
                    </div>
                </div>
                <div class="header-nav">
                    <nav class="nav-main-menu d-none d-xl-block">
                        <ul class="main-menu">

                            <li><a href="{{ url('/') }}">Home</a></li>
                            <li><a href="{{ route('vendors', ['vendors' => 12]) }}">Vendors</a></li>
                            <li><a href="{{ route('shop', ['products' => 12]) }}">Shop</a></li>
                            <li><a href="{{ route('donation', ['products' => 12]) }}">Donatation</a></li>
                            <li><a href="{{ route('rent', ['products' => 12]) }}">Book / Rent</a></li>
                            <li class="has-children"><a >Services</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('service', ['products' => 12]) }}">Research AID</a></li>
                                </ul>
                            </li>
                            <li><a href="{{ url('/contact') }}">Contact Us</a></li>
                            @if(auth()->user())
                            <li><a href="{{ route('frontend.order.track') }}">Order Tracking</a></li>
                            @endif
                        </ul>
                    </nav>
                    <div class="burger-icon burger-icon-white"><span class="burger-icon-top"></span><span class="burger-icon-mid"></span><span class="burger-icon-bottom"></span></div>
                </div>
                <div class="header-shop">
                    <div class="d-inline-block box-dropdown-cart"><span class="font-lg icon-list icon-account"><span>{{ auth()->user() ? auth()->user()->name : 'Account' }}</span></span>
                        <div class="dropdown-account">
                            <ul>
                                @if(auth()->user())
                                    <li><a href="{{ route('frontend.user.account') }}">My Account</a></li>
                                    <li><a href={{ route('frontend.donation.index') }}>My Donations</a></li>
                                    <li><a href={{ route('frontend.order.index') }}>My Orders</a></li>
                                    <li><a href={{ route('frontend.book.index') }}>My Booking</a></li>
                                    <li><a href={{ route('frontend.service.index') }}>My Services</a></li>
                                    <li><a href="{{ route('logout') }}">Sign out</a></li>
                                @else
                                    <li><a href="{{ route('shop.login') }}">Sign in</a></li>
                                @endif
                            </ul>
                        </div>
{{--                    </div><a class="font-lg icon-list icon-wishlist" href="shop-wishlist.html"><span>Wishlist</span><span class="number-item font-xs">5</span></a>--}}
                    <div class="d-inline-block box-dropdown-cart"><span id="icon-cart" class="font-lg icon-list icon-cart"><span>Cart</span><span class="number-item font-xs" id="cart-count">{{ session()->has('cart') ? count(session()->get('cart')) : 0 }}</span></span>
                        <div id="dropdown-cart" class="dropdown-cart">
                            @php $total = 0; @endphp
                            @if(session()->has('cart'))
                                @foreach(session()->get('cart') as $cart)
                                    <div class="item-cart mb-20">
                                        <div class="cart-image"><img src="{{ url('public/images/product', $cart['image']) }}" alt="Ecom"></div>
                                        <div class="cart-info"><a class="font-sm-bold color-brand-3" href="{{ route('product', ['id' => $cart['products_id']]) }}">{{ $cart['name'] }}</a>
                                            <p><span class="color-brand-2 font-sm-bold">{{ $cart['quantity'] }} * {{ number_format($cart['price'], 2) }} {{ $currency->code }}</span></p>
                                        </div>
                                    </div>
                                    @php $total +=  $cart['quantity'] * $cart['price']; @endphp
                                @endforeach
                            @endif
                            <div class="border-bottom pt-0 mb-15"></div>
                            <div class="cart-total">
                                <div class="row">
                                    <div class="col-6 text-start"><span class="font-md-bold color-brand-3">Total</span></div>
                                    <div class="col-6"><span class="font-md-bold color-brand-1">{{ number_format($total, 2) }} {{ $currency->code }}</span></div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-6 text-start"><a class="btn btn-cart w-auto" href="{{ route('cart') }}">View cart</a></div>
                                    <div class="col-6"><a class="btn btn-buy w-auto" href="{{ route('checkout') }}">Checkout</a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-inline-block box-dropdown-rent-cart"><span id="icon-rent-cart" class="font-lg icon-list icon-cart"><span>Rent Cart</span><span class="number-item font-xs" id="cart-count">{{ session()->has('rent_cart') ? count(session()->get('rent_cart')) : 0 }}</span></span>
                        <div id="dropdown-rent-cart" class="dropdown-rent-cart">
                            @php $total = 0; @endphp
                            @if(session()->has('rent_cart'))
                                @foreach(session()->get('rent_cart') as $cart)
                                    <div class="item-cart mb-20">
                                        <div class="cart-image"><img src="{{ url('public/images/product', $cart['image']) }}" alt="Ecom"></div>
                                        <div class="cart-info"><a class="font-sm-bold color-brand-3" href="{{ route('product', ['id' => $cart['products_id']]) }}">{{ $cart['name'] }}</a>
                                            <p><span class="color-brand-2 font-sm-bold">{{ $cart['quantity'] }} * {{ number_format($cart['price'], 2) }} {{ $currency->code }}</span></p>
                                        </div>
                                    </div>
                                    @php $total +=  $cart['quantity'] * $cart['price']; @endphp
                                @endforeach
                            @endif
                            <div class="border-bottom pt-0 mb-15"></div>
                            <div class="cart-total">
                                <div class="row">
                                    <div class="col-6 text-start"><span class="font-md-bold color-brand-3">Total</span></div>
                                    <div class="col-6"><span class="font-md-bold color-brand-1">{{ number_format($total, 2) }} {{ $currency->code }}</span></div>
                                </div>
                                <div class="row mt-15">
                                    <div class="col-12 text-start"><a class="btn btn-cart w-auto" href="{{ route('rent.cart') }}">View Rent cart</a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
             </div>
        </div>
        <div class="header-search-mobile">
            <div class="box-header-search">
                <form class="form-search" method="get" action="{{ route('frontend.product.search') }}">
                    @csrf
                    <div class="box-category">
                        <select name="category" class="select-active select2-hidden-accessible">
                            <option value="0">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="box-keysearch">
                        <input class="form-control font-xs" type="text" name="search" value="" placeholder="Search for items">
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>
<div class="mobile-header-active mobile-header-wrapper-style perfect-scrollbar">
    <div class="mobile-header-wrapper-inner">
        <div class="mobile-header-content-area">
            <div class="mobile-logo"><a class="d-flex" href="{{ url('/') }}"><img width="100px" alt="Beyond" src="{{url('public/logo', $general_setting->site_logo)}}"></a></div>
            <div class="perfect-scroll">
                <div class="mobile-menu-wrap mobile-header-border">
                    <nav class="mt-15">
                        <ul class="mobile-menu font-heading">
                            <li><a href="{{ url('/') }}">Home</a></li>
                            <li><a href="{{ route('vendors', ['vendors' => 12]) }}">Vendors</a></li>
                            <li><a href="{{ route('shop', ['products' => 12]) }}">Shop</a></li>
                            <li><a href="{{ route('donation', ['products' => 12]) }}">Donatation</a></li>
                            <li><a href="{{ route('rent', ['products' => 12]) }}">Book / Rent</a></li>
                            <li class="has-children"><a >Services</a>
                                <ul class="sub-menu">
                                    <li><a href="{{ route('service', ['products' => 12]) }}">Research AID</a></li>
                                </ul>
                            </li>
                            <li><a href="{{ url('/contact') }}">Contact</a></li>
                            @if(auth()->user())
                                <li><a href={{ route('frontend.donation.index') }}>My Donations</a></li>
                                <li><a href={{ route('frontend.order.index') }}>My Orders</a></li>
                                <li><a href={{ route('frontend.book.index') }}>My Booking</a></li>
                                <li><a href={{ route('frontend.service.index') }}>My Services</a></li>
                                <li><a href="{{ route('frontend.order.track') }}">Order Tracking</a></li>
                            @endif
                            <hr>
                            @if(!auth()->user())
                                <li><a class="font-md-bold" href="{{ route('create.shop') }}">Open a shop</a></li>
                            @endif
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

@yield('content')
<section class="section-box box-newsletter">
    <div class="container">
        <ul class="list-col-5">
            <li>
                <div class="item-list">
                    <div class="icon-left"><img src="{{ asset('public/assets/imgs/template/delivery.svg')}}" alt="Product Image"></div>
                    <div class="info-right">
                        <h5 class="font-lg-bold color-gray-100">Free Delivery</h5>
                        <p class="font-sm color-gray-500">Orders over 200,000</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="item-list">
                    <div class="icon-left"><img src="{{ asset('public/assets/imgs/template/support.svg')}}" alt="Product Image"></div>
                    <div class="info-right">
                        <h5 class="font-lg-bold color-gray-100">Support 24/7</h5>
                        <p class="font-sm color-gray-500">Shop with an expert</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="item-list">
                    <div class="icon-left"><img src="{{ asset('public/assets/imgs/template/voucher.svg')}}" alt="Product Image"></div>
                    <div class="info-right">
                        <h5 class="font-lg-bold color-gray-100">Gift voucher</h5>
                        <p class="font-sm color-gray-500">Refer a friend</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="item-list">
                    <div class="icon-left"><img src="{{ asset('public/assets/imgs/template/return.svg')}}" alt="Product Image"></div>
                    <div class="info-right">
                        <h5 class="font-lg-bold color-gray-100">Return &amp; Refund</h5>
                        <p class="font-sm color-gray-500">Free return over CFA 200</p>
                    </div>
                </div>
            </li>
            <li>
                <div class="item-list">
                    <div class="icon-left"><img src="{{ asset('public/assets/imgs/template/secure.svg')}}" alt="Product Image"></div>
                    <div class="info-right">
                        <h5 class="font-lg-bold color-gray-100">Secure payment</h5>
                        <p class="font-sm color-gray-500">100% Protected</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</section>

@php
    $categories = \App\Category::where('is_active', 1)->take('7')->get();
    $brands = \App\Brand::where('is_active', 1)->take('7')->get();
@endphp
<footer class="footer">
    <div class="footer-1">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 width-25 mb-30">
                    <h4 class="mb-30 color-gray-1000">Contact</h4>
                    <div class="font-md mb-20 color-gray-900"><strong class="font-md-bold">Address:</strong> Beyond Enterprise, Kigali, Rwanda</div>
                    <div class="font-md mb-20 color-gray-900"><strong class="font-md-bold">Phone:</strong> <a href="tel:+237675321739">+237675321739</a></div>
                    <div class="font-md mb-20 color-gray-900"><strong class="font-md-bold">E-mail:</strong> info@beyondtechworld.com</div>
                    <div class="font-md mb-20 color-gray-900"><strong class="font-md-bold">Hours:</strong> 8:00 - 17:00, Mon - Sat</div>
                    <div class="mt-30"><a class="icon-socials icon-facebook" href="#"></a><a class="icon-socials icon-instagram" href="#"></a><a class="icon-socials icon-twitter" href="#"></a><a class="icon-socials icon-linkedin" href="#"></a></div>
                </div>
                <div class="col-lg-3 width-20 mb-30">
                    <h4 class="mb-30 color-gray-1000">Categories</h4>
                    <ul class="menu-footer">
                        @foreach($categories as $category)
                            <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-lg-3 width-20 mb-30">
                    <h4 class="mb-30 color-gray-1000">Brands</h4>
                    <ul class="menu-footer">
                        @foreach($brands as $brand)
                            <li><a href="{{ route('shop', ['products' => 12, 'category' => 'null', 'brand' => $brand->id]) }}">{{ $brand->title }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-lg-3 width-23">
                    <h4 class="mb-30 color-gray-1000">Payment Method</h4>
                    <div>
                        <p class="font-md color-gray-900">We are providing cash on delivery facility as well for customer satisfaction.</p>
                        <div class="mt-20"><a class="mr-10" href="#"><img width="100px" src="{{ asset('public/assets/imgs/page/checkout/mtn.png') }}" alt="Ecom"></a><a href="#"><img width="100px" src="{{ asset('public/assets/imgs/page/checkout/cod.svg') }}" alt="Ecom"></a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-2">
        <div class="footer-bottom-1">
            <div class="container">
                <div class="footer-2-top mb-20"><a href="/"><img alt="{{ $general_setting->site_title }}" src="{{url('public/logo', $general_setting->site_logo)}}" width="80px"></a></div>
                <div class="footer-2-bottom">
                    <div class="head-left-footer">
                        <h6 class="color-gray-1000">Categories:</h6>
                    </div>
                    <div class="tags-footer">
                        @foreach($categories as $category)
                            <a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a>
                        @endforeach
                    </div>
                </div>
                <div class="footer-2-bottom">
                    <div class="head-left-footer">
                        <h6 class="color-gray-1000">Brand:</h6>
                    </div>
                    <div class="tags-footer">
                        @foreach($brands as $brand)
                            <a href="{{ route('shop', ['products' => 12, 'category' => 'null', 'brand' => $brand->id]) }}">{{ $brand->title }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="container">
            <div class="footer-bottom mt-20">
                <div class="row">
                    <div class="col-lg-6 col-md-12 text-center text-lg-start"><span class="color-gray-900 font-sm">Copyright &copy; {{ date('Y') }} {{ $general_setting->developed_by }}. All rights reserved.</span></div>
                    <div class="col-lg-6 col-md-12 text-center text-lg-end">
                        <ul class="menu-bottom">
                            <li><a class="font-sm color-gray-900" href="{{ url('/') }}">Home</a></li>
                            <li><a class="font-sm color-gray-900" href="{{ route('shop', ['products' => 12]) }}">Shop</a></li>
                            <li><a class="font-sm color-gray-900" href="{{ url('/contact') }}">Contact Us</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/modernizr-3.6.0.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/jquery-3.6.0.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/jquery-migrate-3.3.0.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/waypoints.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/wow.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/magnific-popup.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/select2.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/isotope.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/scrollup.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/swiper-bundle.min.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/noUISlider.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/slider.js') }}"></script>
<!-- Count down-->
<script src="{{ asset('public/assets/js/vendors/counterup.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/jquery.countdown.min.js') }}"></script>
<!-- Count down--><script src="{{ asset('public/assets/js/vendors/jquery.elevatezoom.js') }}"></script>
<script src="{{ asset('public/assets/js/vendors/slick.js') }}"></script>
<script src="{{ asset('public/assets/js/main.js?v=3.0.0') }}"></script>
<script src="{{ asset('public/assets/js/shop.js?v=1.2.1') }}"></script>

@yield('script')
<script>
    $("#mtn-diff").change(function() {
        if ($(this).is(':checked')) {
            $(".mtn-number").removeClass('d-none');
        } else {
            $(".mtn-number").addClass('d-none');
        }
    });
    function addtocart(id, url)
    {
        event.preventDefault();
        if( $('.quantity-product').val() == null){
            var quantity = 1;
        }else{
            var quantity = $('.quantity-product').val();
        }


        $.ajax({
            url: url,
            type: 'get',
            data: {
                id: id,
                quantity: quantity
            },
            dataType: 'JSON',
            success: function (data) {
                $("#dropdown-cart").load("/"+" #dropdown-cart>*","");
                $("#icon-cart").load("/"+" #icon-cart>*","");
                $('#dropdown-cart').addClass('dropdown-open');
                $('.quantity-product').val(1);
                $(".cart-details").load("/cart"+" .cart-details>*","");
            }
        });
    }

    function addtoRentcart(id, url)
    {
        event.preventDefault();
        if( $('.quantity-product').val() == null){
            var quantity = 1;
        }else{
            var quantity = $('.quantity-product').val();
        }

        $.ajax({
            url: url,
            type: 'get',
            data: {
                id: id,
                quantity: quantity,
            },
            dataType: 'JSON',
            success: function (data) {
                $("#dropdown-rent-cart").load("/"+" #dropdown-rent-cart>*","");
                $("#icon-rent-cart").load("/"+" #icon-rent-cart>*","");
                $('#dropdown-rent-cart').addClass('dropdown-open');
                $('.quantity-product').val(1);
                $(".cart-details").load("/cart"+" .cart-details>*","");
                window.location.href = "{{ route('rent.cart') }}";
            }
        });
    }
</script>
</body>
</html>
