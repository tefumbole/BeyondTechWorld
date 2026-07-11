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
        <form method="get" action="{{ route('rent.checkout') }}">
        <section class="section-box shop-template">
            <div class="container">
                <div class="row cart-details">
                    <div class="col-lg-12">
                        <table class="table table-bordered table-striped">
                            <tr>
                                <th><span class="font-md-bold color-brand-3">Product</span></th>
                                <th><span class="font-md-bold color-brand-3">Booking Method</span></th>
                                <th><span class="font-md-bold color-brand-3">Number</span></th>
                                <th><span class="font-md-bold color-brand-3">Duration</span></th>
                                <th><span class="font-md-bold color-brand-3">Quantity</span></th>
                                <th><span class="font-md-bold color-brand-3">Unit Price</span></th>
                                <th><span class="font-md-bold color-brand-3">Subtotal</span></th>
                                <th><span class="font-md-bold color-brand-3">Remove</span></th>
                            </tr>
                            <tr>
                                @php $total = 0; @endphp
                                @if(session()->has('rent_cart'))
                                    @foreach(session()->get('rent_cart') as $item)
                                    @php
                                        $db_product = \App\Product::where('id', $item['products_id'])->first();
                                    @endphp
                                <td>
                                    <div class="product-image"><a href="{{ route('product', ['id' => $item['products_id']]) }}"><img src="{{ url('public/images/product', $item['image']) }}" alt="beyond" width="100px"></a></div>
                                    <div class="product-info"><a href="{{ route('product', ['id' => $item['products_id']]) }}">
                                            <h6 class="color-brand-3">{{ $item['name'] }}</h6></a>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <select class="form-control booking_method-{{ $item['products_id'] }}" name="booking_method[]" onchange="durationChange(this, '{{ $item['products_id'] }}')"  required>
{{--                                            <option value="">--choose--</option>--}}
                                            @if($db_product->rent_price_per_hour > 0)<option value="0" {{ $item['method'] == 0 ? 'selected' : '' }}>Hourly</option>@endif
                                            @if($db_product->rent_price_per_day > 0)<option value="1" {{ $item['method'] == 1 ? 'selected' : '' }}>Daily</option>@endif
                                            @if($db_product->rent_price_per_month > 0)<option value="2" {{ $item['method'] == 2 ? 'selected' : '' }}>Monthly</option>@endif
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="wishlist-price">
                                        <input type="number" class="form-control number-duration-{{ $item['products_id'] }}" onchange="durationChange(this, '{{ $item['products_id'] }}')" name="number[]" value="{{ $item['number'] ? $item['number'] : '1' }}" required />
                                    </div>
                                </td>
                                <td>
                                    <div class="wishlist-price">
                                        <input type="datetime-local" value="{{ $item['start'] ? $item['start'] : '' }}" name="start[]" class="start-{{ $item['products_id'] }} form-control" onchange="durationChange(this, '{{ $item['products_id'] }}')" required>
                                        <input type="datetime-local" value="{{ $item['end'] ? $item['end'] : '' }}" name="end[]" class="end-{{ $item['products_id'] }} form-control" onchange="durationChange(this, '{{ $item['products_id'] }}')" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="wishlist-status">
                                        <div class="box-quantity">
                                            <div class="input-quantity">
                                                <input id="quantity-{{ $item['products_id'] }}" class="font-xl color-brand-3 cart-qty-{{ $item['products_id'] }}" type="text" value="{{ $item['quantity'] }}" onkeyup="updateCart({{ $item['products_id'] }},'/rent/cart/update', this.value)">
                                                <span class="minus-cart" onclick="updateCart({{ $item['products_id'] }},'/rent/cart/minus')"></span>
                                                <span class="plus-cart" onclick="updateCart({{ $item['products_id'] }},'/rent/cart/plus')"></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wishlist-action">
                                        <h4 class="color-brand-3"><span>{{ number_format($item['price'], 2) }}</span> {{ $currency->code }}</h4>
                                    </div>
                                </td>
                                    <td>
                                        <div class="wishlist-action">
                                            <h4 class="color-brand-3">{{ number_format($item['price'] * $item['quantity'] * $item['number'], 2) }} {{ $currency->code }}</h4>
                                        </div>
                                    </td>
                                <td>
                                    <div class="wishlist-remove"><a class="btn btn-delete" onclick="updateCart({{ $item['products_id'] }},'/rent/cart/delete')"></a></div>
                                </td>
                            </tr>
                            @php $total +=  $item['price'] * $item['quantity'] * $item['number']; @endphp
                            @endforeach
                            @endif
                        </table>
                    </div>
                    <div class="col-lg-6">
                        <div class="summary-cart">
                            <div class="border-bottom mb-10">
                                <div class="row">
                                    <div class="col-6"><span class="font-md-bold color-gray-500">Subtotal</span></div>
                                    <div class="col-6 text-end">
                                        <input type="hidden" name="total_price" value="{{ $total }}">
                                        <h4>	{{ number_format($total, 2) }} {{ $currency->code }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-10">
                                <div class="row">
                                    <div class="col-6"><span class="font-md-bold color-gray-500">Total</span></div>
                                    <div class="col-6 text-end">
                                        <h4>	{{ number_format($total, 2) }} {{ $currency->code }}</h4>
                                    </div>
                                </div>
                            </div>
                            <div class="box-button"><input type="submit" class="btn btn-buy" value="Proceed To CheckOut"></div>
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
        </form>
    </main>
    <script>

        // $('.btn-buy').on('click', function() {
        //     $(input).validity.valid
        // });
        function updateCart(id,url, quantity = 1)
        {
            event.preventDefault();
            $('.preloader-active').css('display','block');

            if(quantity == ''){
                $('.cart-qty-'+id).val(1);
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
                    $(".cart-details").load("/rent-cart"+" .cart-details>*","");
                    $('.preloader-active').css('display','none');
                }
            });
        }

        function durationChange(selectObject, id){

            $('.preloader-active').css('display','block');
            var start = $('.start-'+id).val();
            var end = $('.end-'+id).val();
            var number_duration = $('.number-duration-'+id).val();
            var method = $('.booking_method-'+id).val();
            var product_quantity = $('.cart-qty-'+id).val(1);

            $.ajax({
                type: 'GET',
                url: '{{ route("frontend.booking.search_by_duration") }}',
                data: {
                    id: id,
                    method: method,
                    number: number_duration,
                    start: start,
                    end: end,
                },
                success: function(data) {
                    $(".cart-details").load("/rent-cart"+" .cart-details>*","");
                    $('.preloader-active').css('display','none');
                }
            });
        }
    </script>
@endsection
