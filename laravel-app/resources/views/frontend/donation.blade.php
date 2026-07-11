@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <div class="section-box">
            <div class="breadcrumbs-div">
                <div class="container">
                    <ul class="breadcrumb">
                        <li><a class="font-xs color-gray-1000" href="/">Home</a></li>
                        <li><a class="font-xs color-gray-500" href="#">Shop</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="section-box shop-template mt-30">
            <div class="container">
                <div class="row">
                    <div class="col-lg-12 order-first order-lg-last">
                        <div class="header-search">
                            <div class="box-header-search">
                                <form class="form-search" method="get" action="{{ route('frontend.product.search.donation') }}">
                                    @csrf
                                    <div class="box-keysearch">
                                        <label class="control-label color-danger">Search donation</label>
                                        <input class="form-control font-xs" type="text" name="search" value="" placeholder="Search donation">
                                    </div>
                                </form>
                            </div>
                    <div class="col-lg-12 order-first order-lg-last">

                        <div class="row mt-20" id="shop-products">
                            @foreach($products as $product)
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                                    <div class="card-grid-style-3">
                                        <div class="card-grid-inner">
{{--                                            <div class="tools"><a class="btn btn-trend btn-tooltip mb-10" href="#" aria-label="Trend" data-bs-placement="left"></a><a class="btn btn-wishlist btn-tooltip mb-10" href="shop-wishlist.html" aria-label="Add To Wishlist"></a><a class="btn btn-compare btn-tooltip mb-10" href="shop-compare.html" aria-label="Compare"></a><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="#ModalQuickview" data-bs-toggle="modal"></a></div>--}}
                                            <div class="image-box">
{{--                                                <span class="label bg-brand-2">-17%</span>--}}
                                                <a href="{{ route('donate.detail', ['id' => $product->id] ) }}">
                                                    @php
                                                        $product_image = explode(",", $product->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                     @endphp
                                                    <img src="{{ url('public/images/product', $product_image) }}" alt="Ecom">
                                                </a>
                                            </div>
                                            <div class="info-right"><a class="font-xs color-gray-500" href="">{{ @$product->category->name }}</a><br>
                                                <a class="color-brand-3 font-sm-bold" href="{{ route('donate.detail', ['id' => $product->id] ) }}">{{ $product->name }}</a>
                                                <div class="rating">
                                                    <div class="product-rate d-inline-block">
                                                        <div class="product-rating" style="width: {{ $product->rating * 20 }}%"></div>
                                                    </div>
                                                    <span class="font-xs color-gray-500">({{ count($product->reviews) }})</span>
                                                </div>
{{--                                                    <h5 class="color-brand-3 price-main d-inline-block mr-10">{{ number_format($product->price, 2) }} {{ $currency->code }}</h5>--}}

                                                <div class="mt-20 box-btn-cart"><a class="btn btn-cart" href="{{ route('donate', ['id' => $product->id] ) }}">Donate Now</a></div>

                                                <div>{{ $product->product_detail }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <nav>
                            <ul class="pagination">
                                {{ $products->links() }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </main>

@endsection
