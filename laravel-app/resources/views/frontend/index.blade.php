@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box bg-home9">
            <div class="banner-hero banner-home9">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-6 col-lg-5 mb-20">
                            <div class="box-swiper">
                                <div class="swiper-container swiper-group-1 home-9">
                                    <div class="swiper-wrapper">
                                        <a href="{{ route('shop', ['products' => 12]) }}">
                                            <div class="swiper-slide">
                                                <div class="banner-big-home9 bg-2">
                                                    <div class="info-banner"><span class="font-sm text-uppercase label-green">new arrival</span>
                                                        <h4 class="mt-10 color-gray-1000">Be First to purchase<br class="d-none d-lg-block">Our limited stock.</h4>
                                                        <p class="font-nd color-brand-1">Increase your mobile battery life.</p>
                                                        <div class="mt-30"><a class="btn btn-brand-2 btn-arrow-right" href="{{ route('shop', ['products' => 12]) }}">Shop now</a></div>
                                                    </div>
                                                    <div class="box-img-banner"><img src="{{ asset('public/images/product/1696154689567Powerbankt2.jpg') }}" alt="Beyond" width="480" height="480" fetchpriority="high" decoding="async"></div>
                                                </div>
                                            </div>
                                        </a>
                                        <a href="{{ route('shop', ['products' => 12]) }}">
                                            <div class="swiper-slide">
                                                <div class="banner-big-home9 bg-2">
                                                    <div class="info-banner"><span class="font-sm text-uppercase label-green">new arrival</span>
                                                        <h4 class="mt-10 color-gray-1000">Be First to purchase<br class="d-none d-lg-block">Our limited stock.</h4>
                                                        <p class="font-md color-brand-1">Increase your mobile battery life.</p>
                                                        <div class="mt-30"><a class="btn btn-brand-2 btn-arrow-right" href="{{ route('shop', ['products' => 12]) }}">Shop now</a></div>
                                                    </div>
                                                    <div class="box-img-banner"><img src="{{ asset('public/images/product/1696154689567Powerbankt2.jpg') }}" alt="Beyond" width="480" height="480" fetchpriority="high" decoding="async"></div>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="swiper-pagination swiper-pagination-1"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 mb-20">
                            <div class="banner-small bg-5 text-center"><span class="color-brand-3 font-sm">New Arrivals</span>
                                <h4 class="mb-5 mt-5 color-gray-1000">Weekly Deal</h4><span class="color-brand-1 font-md">Up to<span class="color-brand-2 font-md font-bold">CFA 252.00</span><span class="color-brand-3 font-md">OFF</span></span>
                                <div class="mt-20"><a class="btn btn-brand-3 btn-arrow-right" href="{{ route('shop', ['products' => 12]) }}">Shop Now</a></div>
                                <!--<div class="mt-30"><img src="{{ asset('public/images/product/1654684933904Behringer%20B1520%20Pro.jpg') }}" alt="Beyond"></div>-->
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 col-12 mb-20">
                            <div class="banner-small bg-30 text-center"><span class="color-brand-3 font-sm">New Arrivals</span>
                                <h4 class="mt-5 mb-10 color-gray-1000">Certified Deals On Surface Pro 2022</h4>
                                <div class="mt-15"><a class="btn btn-brand-2 btn-arrow-right" href="{{ route('shop', ['products' => 12]) }}">Shop Now</a></div>
                                <!--<div class="mt-10"><img src="{{ asset('public/images/product/1654723287911FENDER%204%20string.jpg') }}" alt="Beyond"></div>-->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section-box bg-home9">
            <div class="container">
                <div class="row">
                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                        <div class="box-slider-product">
                            <div class="head-slider">
                                <div class="row">
                                    <div class="col-lg-7">
                                        <h5>New arrivals</h5>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="box-button-slider-2">
                                            <div class="swiper-button-prev swiper-button-prev-style-top swiper-button-prev-newarrival"></div>
                                            <div class="swiper-button-next swiper-button-next-style-top swiper-button-next-newarrival"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="content-products">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-group-3-newarrival">
                                        <div class="swiper-wrapper">
                                            @foreach($new_arrival as $item)
                                                @php
                                                    $product_image = explode(",", $item->image);
                                                    $product_image = htmlspecialchars($product_image[0]);
                                                @endphp
                                                <div class="swiper-slide">
                                                    <div class="card-product-small">
                                                        <div class="card-image"> <a href="{{ route('product', ['id' => $item->id]) }}"> <img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a></div>
                                                        <div class="card-info">
                                                            <div class="text-black-50">{{ $item->name }}</div>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>
                                                            <div class="box-prices">
                                                                <div class="price-bold color-brand-3">{{ number_format($item->price, 2) }} {{ $currency->code }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                        <div class="box-slider-product">
                            <div class="head-slider">
                                <div class="row">
                                    <div class="col-lg-7">
                                        <h5>Best selling</h5>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="box-button-slider-2">
                                            <div class="swiper-button-prev swiper-button-prev-style-top swiper-button-prev-bestselling"></div>
                                            <div class="swiper-button-next swiper-button-next-style-top swiper-button-next-bestselling"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="content-products">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-group-3-bestselling">
                                        <div class="swiper-wrapper">

                                            @foreach($best_selling as $item)
                                                @php
                                                    $product_image = explode(",", $item->image);
                                                    $product_image = htmlspecialchars($product_image[0]);
                                                @endphp

                                                <div class="swiper-slide">
                                                    <div class="card-product-small">
                                                        <div class="card-image"> <a href="{{ route('product', ['id' => $item->id]) }}"> <img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a></div>
                                                        <div class="card-info">
                                                            <div class="text-black-50">{{ $item->name }}</div>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>
                                                            <div class="box-prices">
                                                                <div class="price-bold color-brand-3">{{ number_format($item->price, 2) }} {{ $currency->code }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                        <div class="box-slider-product">
                            <div class="head-slider">
                                <div class="row">
                                    <div class="col-lg-7">
                                        <h5>Hot Deals</h5>
                                    </div>
                                    <div class="col-lg-5">
                                        <div class="box-button-slider-2">
                                            <div class="swiper-button-prev swiper-button-prev-style-top swiper-button-prev-hotdeal"></div>
                                            <div class="swiper-button-next swiper-button-next-style-top swiper-button-next-hotdeal"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="content-products">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-group-3-hotdeal">
                                        <div class="swiper-wrapper">

                                            @foreach($hot_deal as $item)
                                                @php
                                                    $product_image = explode(",", $item->image);
                                                    $product_image = htmlspecialchars($product_image[0]);
                                                @endphp

                                                <div class="swiper-slide">
                                                    <div class="card-product-small">
                                                        <div class="card-image"> <a href="{{ route('product', ['id' => $item->id]) }}"> <img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a></div>
                                                        <div class="card-info">
                                                            <div class="text-black-50">{{ $item->name }}</div>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>
                                                            <div class="box-prices">
                                                                <div class="price-bold color-brand-3">{{ number_format($item->price, 2) }} {{ $currency->code }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="bg-home9">
            <div class="section-box">
                <div class="container">
                    <div class="list-brands list-none-border">
                        <div class="box-swiper">
                            <div class="swiper-container swiper-group-10">
                                <div class="swiper-wrapper">
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/microsoft.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/sony.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/acer.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/nokia.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/assus.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/casio.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/dell.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/panasonic.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/vaio.svg') }}" alt="Product Image"></a></div>
                                    <div class="swiper-slide"><a href="{{ route('shop', ['products' => 12]) }}"><img src="{{ asset('public/assets/imgs/slider/logo/sharp.svg') }}" alt="Product Image"></a></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <section class="section-box pt-50 bg-home9">
            <div class="container">
                <div class="box-product-category">
                    <div class="d-flex">
                        <div class="box-category-left">
                            <div class="box-menu-category bg-white">
                                <h5 class="title-border-bottom mb-20">{{ $categories[0]->name }}</h5>
                                <ul class="list-nav-arrow">
                                    @foreach($categories as $category)
                                        <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="box-category-right">
                            <div class="row">
                                @foreach($first_category as $item)
                                    @php
                                        $product_image = explode(",", $item->image);
                                        $product_image = htmlspecialchars($product_image[0]);
                                    @endphp
                                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                                        <div class="card-grid-style-3">
                                            <div class="card-grid-inner">
                                                <div class="tools"><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="{{ route('product', ['id' => $item->id]) }}" data-bs-toggle="modal"></a></div>
                                                <div class="image-box"><span class="label bg-brand-2">sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a>
                                                </div>
                                                <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->brand->title }}</span><br><a class="color-brand-3 font-sm-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                    <div class="rating">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                        </div>
                                                        <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                    </div>                                        <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="bg-home9">
            <section class="section-box pt-50">
                <div class="container">
                    <div class="row">
                        <div class="col-xl-4 col-lg-7 col-md-7 col-sm-12 mb-30">
                            <div class="bg-4 block-charge"><span class="color-brand-3 font-sm-lh32">Power Bank</span>
                                <h3 class="font-xl mb-10">Quick Charge</h3>
                                <p class="font-base color-brand-3 mb-20">Lightweight and Portable<br class="d-none d-lg-block"> Dual port fast charge</p><a class="btn btn-brand-2 btn-arrow-right" href="{{ route('shop', ['products' => 12]) }}">Shop Now</a>
                            </div>
                        </div>
                        <div class="col-xl-5 col-lg-12 col-md-12 col-sm-12 mb-30">
                            <div class="bg-6 block-player">
                                <h3 class="font-33 mb-20">Xbox Series XS Game Controller</h3>
                                <div class="mb-30"><strong class="font-16">Replacement Kit D-pad ABXY Keys</strong></div><a class="btn btn-brand-3 btn-arrow-right" href="{{ route('shop', ['products' => 12]) }}">learn more</a>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-5 col-md-5 col-sm-12 mb-30">
                            <div class="bg-5 block-iphone"><span class="color-brand-3 font-sm-lh32">Starting from CFA 899</span>
                                <h3 class="font-xl mb-10">iPhone 12 Pro 128Gb</h3>
                                <p class="font-base color-brand-3 mb-10">Special Sale</p><a class="btn btn-arrow" href="{{ route('shop', ['products' => 12]) }}">learn more</a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <section class="section-box pt-30 bg-home9">
            <div class="container">
                <div class="box-product-category">
                    <div class="d-flex">
                        <div class="box-category-left">
                            <div class="box-menu-category bg-white">
                                <h5 class="title-border-bottom mb-20">{{ $categories[1]->name }}</h5>
                                <ul class="list-nav-arrow">
                                    @foreach($categories as $category)
                                        <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="box-category-right">
                            <div class="row">
                                @foreach($second_category as $item)
                                    @php
                                        $product_image = explode(",", $item->image);
                                        $product_image = htmlspecialchars($product_image[0]);
                                    @endphp
                                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                                        <div class="card-grid-style-3">
                                            <div class="card-grid-inner">
                                                <div class="tools"><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="{{ route('product', ['id' => $item->id]) }}" data-bs-toggle="modal"></a></div>
                                                <div class="image-box"><span class="label bg-brand-2">sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a>
                                                </div>
                                                <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->brand->title }}</span><br><a class="color-brand-3 font-sm-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                    <div class="rating">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                        </div>
                                                        <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                    </div>                                                <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="bg-home9">
            <section class="section-box mt-50">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-4 mb-20">
                            <div class="block-sale-1">
                                <div class="row">
                                    <div class="col-lg-8 col-sm-8 col-8"><span class="font-sm color-danger text-uppercase">10% </span><span class="font-sm text-uppercase color-brand-3">Sale Off</span>
                                        <h3 class="mb-10">Buy Soft Spongy Teddy Bear</h3><a class="btn btn-brand-2 btn-arrow-right" href="#">Shop Now</a>
                                    </div>
                                    <div class="col-lg-4 col-sm-4 col-4"><img src="{{ asset('public/assets/imgs/page/homepage7/sale1.png')}}" alt="Product Image"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-20">
                            <div class="block-sale-1 bg-4">
                                <div class="row">
                                    <div class="col-lg-8 col-sm-8 col-8"><strong class="font-sm color-danger font-bold text-uppercase">BIG DISCOUNT</strong>
                                        <h3 class="mb-10">Wooden toy products</h3><a class="btn btn-brand-2 btn-arrow-right" href="#">Shop Now</a>
                                    </div>
                                    <div class="col-lg-4 col-sm-4 col-4"><img src="{{ asset('public/assets/imgs/page/homepage7/sale2.png')}}" alt="Product Image"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4 mb-20">
                            <div class="block-sale-1 bg-10">
                                <div class="row">
                                    <div class="col-lg-8 col-sm-8 col-8"><strong class="font-sm color-danger font-bold text-uppercase">Flat 50% discount</strong>
                                        <h3 class="mb-10">Milk powder for mother & baby</h3><a class="btn btn-brand-2 btn-arrow-right" href="#">Shop Now</a>
                                    </div>
                                    <div class="col-lg-4 col-sm-4 col-4"><img src="{{ asset('public/assets/imgs/page/homepage7/sale3.png')}}" alt="Product Image"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <section class="section-box pt-30 bg-home9">
            <div class="container">
                <div class="box-product-category">
                    <div class="d-flex">
                        <div class="box-category-left">
                            <div class="box-menu-category bg-white">
                                <h5 class="title-border-bottom mb-20">{{ $categories[2]->name }}</h5>
                                <ul class="list-nav-arrow">
                                    @foreach($categories as $category)
                                        <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="box-category-right">
                            <div class="row">
                                @foreach($third_category as $item)
                                    @php
                                        $product_image = explode(",", $item->image);
                                        $product_image = htmlspecialchars($product_image[0]);
                                    @endphp
                                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                                        <div class="card-grid-style-3">
                                            <div class="card-grid-inner">
                                                <div class="tools"><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="{{ route('product', ['id' => $item->id]) }}" data-bs-toggle="modal"></a></div>
                                                <div class="image-box"><span class="label bg-brand-2">sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a>
                                                </div>
                                                <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->brand->title }}</span><br><a class="color-brand-3 font-sm-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                    <div class="rating">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                        </div>
                                                        <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                    </div>                                                <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="section-box pt-50 bg-home9">
            <div class="container">
                <div class="box-product-category">
                    <div class="d-flex">
                        <div class="box-category-left">
                            <div class="box-menu-category bg-white">
                                <h5 class="title-border-bottom mb-20">{{ $categories[3]->name }}</h5>
                                <ul class="list-nav-arrow">
                                    @foreach($categories as $category)
                                        <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="box-category-right">
                            <div class="row">
                                @foreach($forth_category as $item)
                                    @php
                                        $product_image = explode(",", $item->image);
                                        $product_image = htmlspecialchars($product_image[0]);
                                    @endphp
                                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                                        <div class="card-grid-style-3">
                                            <div class="card-grid-inner">
                                                <div class="tools"><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="{{ route('product', ['id' => $item->id]) }}" data-bs-toggle="modal"></a></div>
                                                <div class="image-box"><span class="label bg-brand-2">sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="{{ $item->name }}" width="300" height="300" loading="lazy" decoding="async"></a>
                                                </div>
                                                <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->brand->title }}</span><br><a class="color-brand-3 font-sm-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                    <div class="rating">
                                                        <div class="product-rate d-inline-block">
                                                            <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                        </div>
                                                        <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                    </div>                                                <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="bg-home9 pb-60">
            <section class="section-box mt-40">
                <div class="container">
                    <div class="banner-ads-3">
                        <h5 class="mb-5 color-gray-900">70% off on limited chairs</h5>
                        <p class="font-base color-gray-900 mb-10">Free shipping available for purchases more than CFA 200,000.</p><a class="btn btn-brand-3">View Products </a>
                    </div>
                </div>
            </section>
        </div>
        <section class="section-box mt-50">
            <div class="container">
                <div class="row">
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="box-slider-item">
                            <div class="head">
                                <h5>Best seller</h5>
                            </div>
                            <div class="content-slider">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-best-seller">
                                        <div class="swiper-wrapper pt-5">
                                            <div class="swiper-slide">
                                                @foreach ($best_selling->slice(0, 3) as $item)
                                                    @php
                                                        $product_image = explode(",", $item->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                    @endphp
                                                    <div class="card-grid-style-2 card-grid-none-border hover-up">
                                                        <div class="image-box"><span class="label bg-brand-1">hot sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="Product Image"></a>
                                                        </div>
                                                        <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->category->name }}</span><br><a class="color-brand-3 font-xs-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                            {{--                                                    <div class="rating"><img src="{{ asset('public/assets/imgs/template/icons/star.svg')}}" alt="Product Image"><img src="{{ asset('public/assets/imgs/template/icons/star.svg')}}" alt="Product Image"><img src="{{ asset('public/assets/imgs/template/icons/star.svg')}}" alt="Product Image"><img src="{{ asset('public/assets/imgs/template/icons/star.svg')}}" alt="Product Image"><img src="{{ asset('public/assets/imgs/template/icons/star.svg')}}" alt="Product Image"><span class="font-xs color-gray-500"> (65)</span></div>--}}
                                                            <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            <div class="swiper-slide">
                                                @foreach ($best_selling->slice(3, 3) as $item)
                                                    @php
                                                        $product_image = explode(",", $item->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                    @endphp
                                                    <div class="card-grid-style-2 card-grid-none-border hover-up">
                                                        <div class="image-box"><span class="label bg-brand-1">new sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="Product Image"></a>
                                                        </div>
                                                        <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->category->name }}</span><br><a class="color-brand-3 font-xs-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>                                                        <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="swiper-button-next swiper-button-next-style-2 swiper-button-next-bestseller"></div>
                                    <div class="swiper-button-prev swiper-button-prev-style-2 swiper-button-prev-bestseller"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="box-slider-item">
                            <div class="head">
                                <h5>Featured products</h5>
                            </div>
                            <div class="content-slider">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-featured">
                                        <div class="swiper-wrapper pt-5">
                                            <div class="swiper-slide">
                                                @foreach ($hot_deal->slice(0, 3) as $item)
                                                    @php
                                                        $product_image = explode(",", $item->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                    @endphp
                                                    <div class="card-grid-style-2 card-grid-none-border hover-up">
                                                        <div class="image-box"><span class="label label-green bg-brand-2">flash sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="Product Image"></a>
                                                        </div>
                                                        <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->category->name }}</span><br><a class="color-brand-3 font-xs-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>                                                        <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="swiper-button-next swiper-button-next-style-2 swiper-button-next-featured"></div>
                                    <div class="swiper-button-prev swiper-button-prev-style-2 swiper-button-prev-featured"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="box-slider-item">
                            <div class="head">
                                <h5>Most viewed</h5>
                            </div>
                            <div class="content-slider">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-mostviewed">
                                        <div class="swiper-wrapper pt-5">
                                            <div class="swiper-slide">
                                                @foreach ($hot_deal->slice(3, 3) as $item)
                                                    @php
                                                        $product_image = explode(",", $item->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                    @endphp
                                                    <div class="card-grid-style-2 card-grid-none-border hover-up">
                                                        <div class="image-box"><span class="label bg-brand-1">new sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="Product Image"></a>
                                                        </div>
                                                        <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->category->name }}</span><br><a class="color-brand-3 font-xs-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>                                                        <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="swiper-button-next swiper-button-next-style-2 swiper-button-next-mostviewed"></div>
                                    <div class="swiper-button-prev swiper-button-prev-style-2 swiper-button-prev-mostviewed"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="box-slider-item">
                            <div class="head">
                                <h5>Trending</h5>
                            </div>
                            <div class="content-slider">
                                <div class="box-swiper">
                                    <div class="swiper-container swiper-trending">
                                        <div class="swiper-wrapper pt-5">
                                            <div class="swiper-slide">
                                                @foreach ($new_arrival->slice(0, 3) as $item)
                                                    @php
                                                        $product_image = explode(",", $item->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                    @endphp
                                                    <div class="card-grid-style-2 card-grid-none-border hover-up">
                                                        <div class="image-box"><span class="label bg-brand-2">hot sale</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="Product Image"></a>
                                                        </div>
                                                        <div class="info-right"><span class="font-xs color-gray-500">{{ @$item->category->name }}</span><br><a class="color-brand-3 font-xs-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                                            <div class="rating">
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                                                </div>
                                                                <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                                            </div>                                                        <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    <div class="swiper-button-next swiper-button-next-style-2 swiper-button-next-trending"></div>
                                    <div class="swiper-button-prev swiper-button-prev-style-2 swiper-button-prev-trending"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
@endsection
