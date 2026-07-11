@extends('frontend.layout.main')
@section('content')
    <main class="main">
        <div class="section-box">
            <div class="breadcrumbs-div">
                <div class="container">
                    <ul class="breadcrumb">
                        <li><a class="font-xs color-gray-1000" href="/">Home</a></li>
                        <li><a class="font-xs color-gray-500" href="{{ route('shop', ['products' => 12]) }}">Shop</a></li>
                        <li><a class="font-xs color-gray-500" href="#">Cart</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <section class="section-box shop-template">
            <div class="container">
                <div class="row cart-details">
                    <div class="col-lg-9">
                        <div class="box-carts">
                            <div class="head-wishlist">
                                <div class="item-wishlist">

                                    <div class="wishlist-product"><span class="font-md-bold color-brand-3">Product</span></div>
                                    <div class="wishlist-price"><span class="font-md-bold color-brand-3">Unit Price</span></div>
                                    <div class="wishlist-status"><span class="font-md-bold color-brand-3">Quantity</span></div>
                                    <div class="wishlist-action"><span class="font-md-bold color-brand-3">Subtotal</span></div>
                                    <div class="wishlist-remove"><span class="font-md-bold color-brand-3">Remove</span></div>
                                </div>
                            </div>
                            <div class="content-wishlist mb-20">
                                @php $total = 0; @endphp
                                @if(session()->has('cart'))
                                    @foreach(session()->get('cart') as $item)
                                    <div class="item-wishlist">

                                        <div class="wishlist-product">
                                            <div class="product-wishlist">
                                                <div class="product-image"><a href="{{ route('product', ['id' => $item['products_id']]) }}"><img src="{{ url('public/images/product', $item['image']) }}" alt="beyond"></a></div>
                                                <div class="product-info"><a href="{{ route('product', ['id' => $item['products_id']]) }}">
                                                        <h6 class="color-brand-3">{{ $item['name'] }}</h6></a>
{{--                                                    <div class="rating"><img src="assets/imgs/template/icons/star.svg" alt="Ecom"><img src="assets/imgs/template/icons/star.svg" alt="Ecom"><img src="assets/imgs/template/icons/star.svg" alt="Ecom"><img src="assets/imgs/template/icons/star.svg" alt="Ecom"><img src="assets/imgs/template/icons/star.svg" alt="Ecom"><span class="font-xs color-gray-500"> (65)</span></div>--}}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="wishlist-price">
                                            <h4 class="color-brand-3">{{ number_format($item['price'], 2) }} {{ $currency->code }}</h4>
                                        </div>
                                        <div class="wishlist-status">
                                            <div class="box-quantity">
                                                <div class="input-quantity">
                                                    <input class="font-xl color-brand-3 cart-qty-{{ $item['products_id'] }}" type="text" value="{{ $item['quantity'] }}" onkeyup="updateCart({{ $item['products_id'] }},'/cart/update', this.value)">
                                                    <span class="minus-cart" onclick="updateCart({{ $item['products_id'] }},'/cart/minus')"></span>
                                                    <span class="plus-cart" onclick="updateCart({{ $item['products_id'] }},'/cart/plus')"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="wishlist-action">
                                            <h4 class="color-brand-3">{{ number_format($item['price'] * $item['quantity'], 2) }} {{ $currency->code }}</h4>
                                        </div>
                                        <div class="wishlist-remove"><a class="btn btn-delete" onclick="updateCart({{ $item['products_id'] }},'/cart/delete')"></a></div>
                                    </div>
                                    @php $total +=  $item['price'] * $item['quantity']; @endphp
                                @endforeach
                                @endif
                            </div>
                            <div class="row mb-40">
                                <div class="col-lg-6 col-md-6 col-sm-6-col-6"><a class="btn btn-buy w-auto arrow-back mb-10" href="{{ route('shop', ['products' => 12]) }}">Continue shopping</a></div>
                                <div class="col-lg-6 col-md-6 col-sm-6-col-6 text-md-end"><a class="btn btn-buy w-auto update-cart mb-10" onclick="window.location.reload()">Update cart</a></div>
                            </div>
{{--                            <div class="row mb-50">--}}
{{--                                <div class="col-lg-6 col-md-6">--}}
{{--                                    <div class="box-cart-left">--}}
{{--                                        <h5 class="font-md-bold mb-10">Calculate Shipping</h5><span class="font-sm-bold mb-5 d-inline-block color-gray-500">Flat rate:</span><span class="font-sm-bold d-inline-block color-brand-3">5%</span>--}}
{{--                                        <div class="form-group">--}}
{{--                                            <select class="form-control select-style1 color-gray-700">--}}
{{--                                                <option value="1">USA</option>--}}
{{--                                                <option value="1">EURO</option>--}}
{{--                                            </select>--}}
{{--                                        </div>--}}
{{--                                        <div class="row">--}}
{{--                                            <div class="col-lg-6 mb-10">--}}
{{--                                                <input class="form-control" placeholder="Stage / Country">--}}
{{--                                            </div>--}}
{{--                                            <div class="col-lg-6 mb-10">--}}
{{--                                                <input class="form-control" placeholder="PostCode / ZIP">--}}
{{--                                            </div>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                                <div class="col-lg-6 col-md-6">--}}
{{--                                    <div class="box-cart-right p-20">--}}
{{--                                        <h5 class="font-md-bold mb-10">Apply Coupon</h5><span class="font-sm-bold mb-5 d-inline-block color-gray-500">Using A Promo Code?</span>--}}
{{--                                        <div class="form-group d-flex">--}}
{{--                                            <input class="form-control mr-15" placeholder="Enter Your Coupon">--}}
{{--                                            <button class="btn btn-buy w-auto">Apply</button>--}}
{{--                                        </div>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="summary-cart">
                            <div class="border-bottom mb-10">
                                <div class="row">
                                    <div class="col-6"><span class="font-md-bold color-gray-500">Subtotal</span></div>
                                    <div class="col-6 text-end">
                                        <h4>	{{ number_format($total, 2) }} {{ $currency->code }}</h4>
                                    </div>
                                </div>
                            </div>
{{--                            <div class="border-bottom mb-10">--}}
{{--                                <div class="row">--}}
{{--                                    <div class="col-6"><span class="font-md-bold color-gray-500">Shipping</span></div>--}}
{{--                                    <div class="col-6 text-end">--}}
{{--                                        <h4>	Free</h4>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
{{--                            <div class="border-bottom mb-10">--}}
{{--                                <div class="row">--}}
{{--                                    <div class="col-6"><span class="font-md-bold color-gray-500">Estimate for</span></div>--}}
{{--                                    <div class="col-6 text-end">--}}
{{--                                        <h6>United Kingdom</h6>--}}
{{--                                    </div>--}}
{{--                                </div>--}}
{{--                            </div>--}}
                            <div class="mb-10">
                                <div class="row">
                                    <div class="col-6"><span class="font-md-bold color-gray-500">Total</span></div>
                                    <div class="col-6 text-end">
                                        <h4>	{{ number_format($total, 2) }} {{ $currency->code }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="box-button"><a class="btn btn-buy" href="{{ route('checkout') }}">Proceed To CheckOut</a></div>
                        </div>
                    </div>
                </div>
                <h4 class="color-brand-3">You may also like</h4>
                <div class="list-products-5 mt-20 mb-40">

                    @foreach($best_selling as $item)
                        @php
                            $product_image = explode(",", $item->image);
                            $product_image = htmlspecialchars($product_image[0]);
                        @endphp
                    <div class="card-grid-style-3">
                        <div class="card-grid-inner">
{{--                            <div class="tools"><a class="btn btn-trend btn-tooltip mb-10" href="#" aria-label="Trend" data-bs-placement="left"></a><a class="btn btn-wishlist btn-tooltip mb-10" href="shop-wishlist.html" aria-label="Add To Wishlist"></a><a class="btn btn-compare btn-tooltip mb-10" href="shop-compare.html" aria-label="Compare"></a><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="#ModalQuickview" data-bs-toggle="modal"></a></div>--}}
                            <div class="image-box"><span class="label bg-brand-2">hot</span><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="product image"></a></div>
                            <div class="info-right"><a class="font-xs color-gray-500" href="#">{{ @$item->category->name }}</a><br><a class="color-brand-3 font-sm-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                <div class="rating">
                                    <div class="product-rate d-inline-block">
                                        <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                    </div>
                                    <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                </div>
                                <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                                @if($item->qty > 0 || $item->type == 'digital')
                                <div class="mt-20 box-btn-cart"><a class="btn btn-cart" onclick="addtocart({{ $item->id }},'/addToCart')">Add To Cart</a></div>
                                @else
                                    <div class="mt-20 box-btn-cart">Out Of Stock</div>
                                @endif
                                {!! $item->product_details !!}
                            </div>
                        </div>
                    </div>
                    @endforeach

                </div>
                <h4 class="color-brand-3">Recently viewed items</h4>
                <div class="row mt-40">
                    @foreach($best_selling as $item)
                        @php
                            $product_image = explode(",", $item->image);
                            $product_image = htmlspecialchars($product_image[0]);
                        @endphp
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="card-grid-style-2 card-grid-none-border hover-up">
                            <div class="image-box"><a href="{{ route('product', ['id' => $item->id]) }}"><img src="{{ url('public/images/product', $product_image) }}" alt="Ecom"></a>
                            </div>
                            <div class="info-right"><span class="font-xs color-gray-500">HP</span><br><a class="color-brand-3 font-xs-bold" href="{{ route('product', ['id' => $item->id]) }}">{{ $item->name }}</a>
                                <div class="rating">
                                    <div class="product-rate d-inline-block">
                                        <div class="product-rating" style="width: {{ $item->rating * 20 }}%"></div>
                                    </div>
                                    <span class="font-xs color-gray-500">({{ count($item->reviews) }})</span>
                                </div>
                                <div class="price-info"><strong class="font-lg-bold color-brand-3 price-main">{{ number_format($item->price, 2) }} {{ $currency->code }}</strong></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
    <script>
        function updateCart(id,url, quantity = 1)
        {
            event.preventDefault();
            $('.preloader-active').css('display','block');

            if(quantity == ''){
                $('.cart-qty-'+id).val(1)
            }
            $.ajax({
                url: url,
                type: 'get',
                data: {
                    id: parseInt(id),
                    qty: parseInt(quantity),
                },
                dataType: 'JSON',
                success: function () {
                    $(".cart-details").load("/cart"+" .cart-details>*","");
                    $('.preloader-active').css('display','none');
                }
            });
        }
    </script>
@endsection
