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
                    <div class="col-lg-9 order-first order-lg-last">
                        <div class="banner-ads-top mb-30"><a href=""><img src="{{ asset('public/assets/imgs/page/shop/banner.png') }}" alt="Ecom"></a></div>
                        <div class="box-filters mt-0 pb-5 border-bottom">
                            <div class="row">
                                <div class="col-xl-2 col-lg-3 mb-10 text-lg-start text-center"><a class="btn btn-filter font-sm color-brand-3 font-medium" href="#ModalFiltersForms" data-bs-toggle="modal">All Fillters</a></div>
                                <div class="col-xl-10 col-lg-9 mb-10 text-lg-end text-center"><span class="font-sm color-gray-900 font-medium border-1-right span">Showing {{ $products_pick }} of {{ $products_count }} results</span>
{{--                                    <div class="d-inline-block"><span class="font-sm color-gray-500 font-medium">Sort by:</span>--}}
{{--                                        <div class="dropdown dropdown-sort border-1-right">--}}
{{--                                            <button class="btn dropdown-toggle font-sm color-gray-900 font-medium" id="dropdownSort" type="button" data-bs-toggle="dropdown" aria-expanded="false">Latest products</button>--}}
{{--                                            <ul class="dropdown-menu dropdown-menu-light" aria-labelledby="dropdownSort" style="margin: 0px;">--}}
{{--                                                <li><a class="dropdown-item active" href="#">Latest products</a></li>--}}
{{--                                                <li><a class="dropdown-item" href="#">Oldest products</a></li>--}}
{{--                                                <li><a class="dropdown-item" href="#">Comments products</a></li>--}}
{{--                                            </ul>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
                                    <div class="d-inline-block"><span class="font-sm color-gray-500 font-medium">Show</span>
                                        <div class="dropdown dropdown-sort border-1-right">
                                            <select class="form-control" onchange="location = this.value;">
                                                <option value="{{ route('shop', ['products' => 12]) }}" {{ $products_pick == 28 ? 'selected' : '' }}>28 items</option>
                                                <option value="{{ route('shop', ['products' => 24]) }}" {{ $products_pick == 36 ? 'selected' : '' }}>36 items</option>
                                                <option value="{{ route('shop', ['products' => 36]) }}" {{ $products_pick == 48 ? 'selected' : '' }}>48 items</option>
                                            </select>
                                        </div>
                                    </div>
{{--                                    <div class="d-inline-block"><a class="view-type-grid mr-5 active" href="shop-grid.html"></a><a class="view-type-list" href="shop-list.html"></a></div>--}}
                                </div>
                            </div>
                        </div>
                        <div class="row mt-20" id="shop-products">
                            @foreach($products as $product)
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                                    <div class="card-grid-style-3">
                                        <div class="card-grid-inner">
{{--                                            <div class="tools"><a class="btn btn-trend btn-tooltip mb-10" href="#" aria-label="Trend" data-bs-placement="left"></a><a class="btn btn-wishlist btn-tooltip mb-10" href="shop-wishlist.html" aria-label="Add To Wishlist"></a><a class="btn btn-compare btn-tooltip mb-10" href="shop-compare.html" aria-label="Compare"></a><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="#ModalQuickview" data-bs-toggle="modal"></a></div>--}}
                                            <div class="image-box">
{{--                                                <span class="label bg-brand-2">-17%</span>--}}
                                                <a href="{{ route('product', ['id' => $product->id]) }}">
                                                    @php
                                                        $product_image = explode(",", $product->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                     @endphp
                                                    <img src="{{ url('public/images/product', $product_image) }}" alt="Ecom">
                                                </a>
                                            </div>
                                            <div class="info-right"><a class="font-xs color-gray-500" href="">{{ @$product->category->name }}</a><br>
                                                <a class="color-brand-3 font-sm-bold" href="{{ route('product', ['id' => $product->id]) }}">{{ $product->name }}</a>
                                                <div class="rating">
                                                    <div class="product-rate d-inline-block">
                                                        <div class="product-rating" style="width: {{ $product->rating * 20 }}%"></div>
                                                    </div>
                                                    <span class="font-xs color-gray-500">({{ count($product->reviews) }})</span>
                                                </div>
                                                @if($product->price > 0)
                                                    <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($product->price, 2) }} {{ $currency->code }}</strong></div>
                                                @endif
{{--                                                @if($product->qty > 0 || $product->type != 'standard')--}}
{{--                                                    <div class="mt-20 box-btn-cart"><a class="btn btn-cart" onclick="addtocart({{ $product->id }},'/addToCart')">Add To Cart</a></div>--}}
{{--                                                @else--}}
{{--                                                    <div class="mt-20 box-btn-cart">Out Of Stock</div>--}}
{{--                                                @endif--}}
                                                @if($product->type == 'service')
                                                    <div class="button-buy"><a class="btn btn-cart" href="{{ route('single.service', ['id' => $product->id] ) }}">Get Service</a></div>
                                                @elseif($product->type == 'donation')
                                                    <div class="button-buy"><a class="btn btn-cart" href="{{ route('donate', ['id' => $product->id] ) }}">Donate Now</a></div>
                                                @else
                                                    @if(($product->qty > 0 || $product->type != 'standard') && $product->price > 0)
                                                        <div class="button-buy"><a class="btn btn-cart" onclick="addtocart({{ $product->id }},'/addToCart')">Add to cart</a></div>
                                                    @else
                                                        <div class="m-20 box-btn-cart">Out of Stock for Sale</div>
                                                    @endif
                                                    @if(($product->qty > 0 || $product->type != 'standard') && ($product->rent_price_per_hour > 0 || $product->rent_price_per_day > 0 || $product->rent_price_per_month > 0))
                                                        <div class="button-buy"><a class="btn btn-cart" onclick="addtoRentcart({{ $product->id }},'/addToRentCart')">Book / Rent</a></div>
                                                    @endif
                                                @endif
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
                    <div class="col-lg-3 order-last order-lg-first">
                        <div class="sidebar-border mb-40">
                            <div class="sidebar-head">
                                <h6 class="color-gray-900">Products Filter</h6>
                            </div>
                            <div class="sidebar-content">
                                <h6 class="color-gray-900 mt-10 mb-10">Price</h6>
                                <div class="box-slider-range mt-20 mb-15">
                                    <div class="row mb-20">
                                        <div class="col-sm-12">
                                            <div id="slider-range"></div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <label class="lb-slider font-sm color-gray-500">Price Range:</label><span class="min-value-money font-sm color-gray-1000"></span>
                                            <label class="lb-slider font-sm font-medium color-gray-1000"></label>-
                                            <span class="max-value-money font-sm font-medium color-gray-1000"></span>
                                        </div>
                                        <div class="col-lg-12">
                                            <input class="form-control min-value" type="hidden" name="minValue" value="">
                                            <input class="form-control max-value" type="hidden" name="maxValue" value="">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="sidebar-border mb-0">
                            <div class="sidebar-head">
                                <h6 class="color-gray-900">Product Categories</h6>
                            </div>
                            <div class="sidebar-content">
                                <ul class="list-nav-arrow">
                                    @foreach($categories as $category)
                                        <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                                    @endforeach
                                </ul>
                                <div>
                                    <div class="collapse" id="moreMenu">
                                        <ul class="list-nav-arrow">
                                            @foreach($next_categories as $category)
                                                <li><a href="{{ route('shop', ['products' => 12, 'category' => $category->id, 'brand' => 'null']) }}">{{ $category->name }}</a></li>
                                            @endforeach
                                        </ul>
                                    </div><a class="link-see-more mt-5" data-bs-toggle="collapse" href="#moreMenu" role="button" aria-expanded="false" aria-controls="moreMenu">See More</a>
                                </div>
                            </div>
                        </div>
                        <div class="sidebar-border mb-0">
                            <div class="sidebar-head">
                                <h6 class="color-gray-900">Product Brands</h6>
                            </div>
                            <div class="sidebar-content">
                                <ul class="list-nav-arrow">
                                        @foreach($brands as $brand)
                                            <li><a href="{{ route('shop', ['products' => 12, 'category' => 'null', 'brand' => $brand->id]) }}">{{ $brand->title }}</a></li>
                                        @endforeach
                                </ul>
                                <div>
                                    <div class="collapse" id="moreMenu">
                                        <ul class="list-nav-arrow">
                                            @foreach($next_brands as $brand)
                                                <li><a href="{{ route('shop', ['products' => 12, 'category' => 'null', 'brand' => $brand->id]) }}">{{ $brand->title }}</a></li>
                                            @endforeach
                                        </ul>
                                    </div><a class="link-see-more mt-5" data-bs-toggle="collapse" href="#moreMenu" role="button" aria-expanded="false" aria-controls="moreMenu">See More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

@endsection
