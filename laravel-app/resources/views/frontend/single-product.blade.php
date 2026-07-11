@extends('frontend.layout.main')
<style>

    .rating-box {
        position: relative;
        background: #fff;
        padding: 25px 50px 35px;
        border-radius: 25px;
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.05);
    }
    .rating-box header {
        font-size: 22px;
        color: #dadada;
        font-weight: 500;
        margin-bottom: 20px;
        text-align: center;
    }
    .rating-box .stars {
        display: flex;
        align-items: center;
        gap: 25px;
    }
    .stars i {
        color: #e6e6e6;
        font-size: 35px;
        cursor: pointer;
        transition: color 0.2s ease;
    }
    .stars i.active {
        color: #ff9c1a;
    }
</style>
@section('content')
    <main class="main">
        <div class="section-box">
            <div class="breadcrumbs-div">
                <div class="container">
                    <ul class="breadcrumb">
                        <li><a class="font-xs color-gray-1000" href="/">Home</a></li>
                        <li><a class="font-xs color-gray-500" href="">{{ $product->name }}</a></li>
                    </ul>
                </div>
            </div>
        </div>
        @php
            $product_images = explode(",", $product->image);
            $product_image = htmlspecialchars($product_images[0]);
        @endphp
        <section class="section-box shop-template">
            <div class="container">
                @if(session()->has('message'))
                    <div class="alert alert-success text-center">{{ session()->get('message') }}</div>
                @endif
                <div class="row">
                    <div class="col-lg-6">
                        <div class="gallery-image">
                            <div class="galleries">
                                <div class="detail-gallery">
{{--                                    <label class="label">-17%</label>--}}
                                    <div class="product-image-slider">
                                        @foreach($product_images as $image)
                                        <figure class="border-radius-10"><img src="{{ url('public/images/product', $image) }}" alt="product image"></figure>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="slider-nav-thumbnails">
                                    @foreach($product_images as $image)
                                        <div>
                                            <div class="item-thumb"><img src="{{ url('public/images/product', $image) }}" alt="product image"></div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h3 class="color-brand-3 mb-25">{{ $product->name }}</h3>
                        <div class="row align-items-center">
                            <div class="col-lg-4 col-md-4 col-sm-3 mb-mobile"><span class="bytext color-gray-500 font-xs font-medium">by</span><a class="byAUthor color-gray-900 font-xs font-medium" href=""> {{ @$product->vendor->company_name }}</a>
                                <div class="rating mt-5">
                                    <div class="product-rate d-inline-block mr-15">
                                        <div class="product-rating" style="width: {{ $product->rating * 20 }}%"></div>
                                    </div>
                                    <span class="font-xs color-gray-500 font-medium"> ({{ $total_count }} reviews)</span></div>
                            </div>
                        </div>

{{--                        <span class="bytext">by</span><a class="byAUthor" href="shop-vendor-single.html"> Ecom Tech</a>--}}
                        <div class="border-bottom pt-20 mb-40"></div>
                        <div class="box-product-price">
                            @if($product->type == 'donation')
                                <h3 class="color-brand-3 price-main d-inline-block mr-10"><input type="number" class="form-control donate-price-{{$product->id}}" value="{{$product->price}}"></h3>
                            @else
                                <h3 class="color-brand-3 price-main d-inline-block mr-10">Price: {{ number_format($product->price, 2) }} {{ $currency->code }}</h3><br>
                                @if($product->rent_price_per_hour > 0 || $product->rent_price_per_day > 0 || $product->rent_price_per_month > 0)
                                    <hr>
                                    <h3 class="color-brand-3 price-main d-inline-block mr-10">Rent Prices</h3><br>
                                    @if($product->rent_price_per_hour > 0)
                                        <h6 class="color-brand-3 price-main d-inline-block mr-10">Hourly Rate: {{ number_format($product->rent_price_per_hour, 2) }} {{ $currency->code }}</h6><br>
                                    @endif
                                    @if($product->rent_price_per_day > 0)
                                    <h6 class="color-brand-3 price-main d-inline-block mr-10">Daily Rate: {{ number_format($product->rent_price_per_day, 2) }} {{ $currency->code }}</h6><br>
                                    @endif
                                    @if($product->rent_price_per_month > 0)
                                        <h6 class="color-brand-3 price-main d-inline-block mr-10">Monthly Rate: {{ number_format($product->rent_price_per_month, 2) }} {{ $currency->code }}</h6><hr>
                                    @endif
                                @endif
                            @endif

                        </div>
                        <div class="product-description mt-20 color-gray-900">{{ $product->detail }}</div>
                        <div class="buy-product mt-20">
                            <p class="font-sm mb-20">{{ $product->type == 'standard' ? "Quantity Left (".$product->qty.")" : "" }}</p>
                            <div class="box-quantity">
                                <div class="input-quantity">
                                    <input class="font-xl color-brand-3 quantity-product" type="text" value="1"><span class="minus-cart"></span><span class="plus-cart"></span>
                                </div>
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
                            </div>
                        </div>
                        <div class="info-product mt-20 font-md color-gray-900">{!! $product->product_details !!}</div>
                    </div>
                </div>
                <div class="border-bottom pt-20 mb-40"></div>

            </div>
        </section>
        <section class="section-box shop-template">
            <div class="container">
                <div class="pt-30 mb-10">
                    <ul class="nav nav-tabs nav-tabs-product" role="tablist">
                        <li><a>Reviews ({{ $total_count }})</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="" id="tab-reviews" role="tabpanel" aria-labelledby="tab-reviews">
                            <div class="comments-area">
                                <div class="row">
                                    <div class="col-lg-8">
                                        <h4 class="mb-30 title-question">Customer questions &amp; answers</h4>
                                        <div class="comment-list">
                                            @if(auth()->user())
                                                @php
                                                $user = auth()->user();
                                                $can_review = false;
                                                $orders = \App\Order::where('user_id', $user->id)->where('order_status', 1)->get();
                                                @endphp
                                                @if($orders)
                                                    @foreach($orders as $order)
                                                        @foreach($order->orderProducts as $orderProduct)
                                                            @if($orderProduct->product_id == $product->id)
                                                                @php $can_review = true; @endphp
                                                            @endif
                                                        @endforeach
                                                    @endforeach
                                                @endif
                                                @if($can_review == true)
                                                <div class="single-comment justify-content-between d-flex mb-30 hover-up">
                                                    <div class="user justify-content-between d-flex">
                                                        @if(count($product->reviews) > 0)
                                                            {!! Form::open(['route' => ['review.update', $product->reviews[0]->id], 'method' => 'put', 'files' => true]) !!}
                                                            <label for="rating">Rating</label>
                                                            <div class="stars">
                                                                <i class="fa-solid fa-star {{ $product->reviews[0]->rating > 0 ? 'active' : '' }}"></i>
                                                                <i class="fa-solid fa-star {{ $product->reviews[0]->rating > 1 ? 'active' : '' }}"></i>
                                                                <i class="fa-solid fa-star {{ $product->reviews[0]->rating > 2 ? 'active' : '' }}"></i>
                                                                <i class="fa-solid fa-star {{ $product->reviews[0]->rating > 3 ? 'active' : '' }}"></i>
                                                                <i class="fa-solid fa-star {{ $product->reviews[0]->rating > 4 ? 'active' : '' }}"></i>
                                                            </div>
                                                            <hr>
                                                            <label for="review">Review</label>
                                                            <textarea type="text" name="review" class="form-control" style="width: 300%" required>{{ $product->reviews[0]->review }}</textarea>
                                                            <input type="hidden" name="rating" class="review-rating" value="{{ $product->reviews[0]->rating }}">
                                                            <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                                                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                            <br>
                                                            <input type="submit" value="Update" class="btn btn-success">
                                                        </form>
                                                        @else
                                                            <form action="{{ route('review.store') }}" method="post">
                                                                @csrf
                                                                <label for="rating">Rating</label>
                                                                <div class="stars">
                                                                    <i class="fa-solid fa-star"></i>
                                                                    <i class="fa-solid fa-star"></i>
                                                                    <i class="fa-solid fa-star"></i>
                                                                    <i class="fa-solid fa-star"></i>
                                                                    <i class="fa-solid fa-star"></i>
                                                                </div>
                                                                <hr>
                                                                <label for="review">Review</label>
                                                                <textarea type="text" name="review" class="form-control" style="width: 300%" required></textarea>
                                                                <input type="hidden" name="rating" class="review-rating" value="1">
                                                                <input type="hidden" name="user_id" value="{{ auth()->user()->id }}">
                                                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                                                <br>
                                                                <input type="submit" value="Submit" class="btn btn-success">
                                                            {!! Form::close() !!}
                                                        @endif
                                                    </div>
                                                </div>
                                                @endif
                                            @endif
                                            @foreach($product->reviews as $review)
                                                <div class="single-comment justify-content-between d-flex mb-30 hover-up">
                                                    <div class="user justify-content-between d-flex">
                                                        <div class="thumb text-center col-md-3">
                                                            <img src="{{ asset('public/assets/imgs/template/account.svg') }}" alt="user_image" width="50px">
                                                            <a class="font-heading text-brand" href="#">{{ @$review->user->name }}</a>
                                                        </div>
                                                        <div class="desc col-md-9">
                                                            <div class=" justify-content-between mb-10">
                                                                <div class="d-flex align-items-center"><span class="font-xs color-gray-700">{{ $review->created_at->format('D, M d, Y H:i:s') }}</span></div>
                                                                <div class="product-rate d-inline-block">
                                                                    <div class="product-rating" style="width: {{ $review->rating * 20 }}%"></div>
                                                                </div>
                                                            </div>
                                                            <p class="mb-10 font-sm color-gray-900">
                                                                {{ $review->review }}
                                                            </p>
                                                        </div>

                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <h4 class="mb-30 title-question">Customer reviews</h4>
                                        <div class="d-flex mb-30">
                                            <div class="product-rate d-inline-block mr-15">
                                                <div class="product-rating" style="width: {{ $product->rating * 20 }}%"></div>
                                            </div>
                                            <h6>{{ $product->rating }} out of 5</h6>
                                        </div>
                                        <div class="progress"><span>5 star</span>
                                            <div class="progress-bar" role="progressbar" style="width: {{ $five_star_count  > 0 ? $five_star_count/$total_count * 100 - 1 : 0 }}%" aria-valuenow="{{ $five_star_count  > 0 ? $five_star_count/$total_count * 100 : 0 }}" aria-valuemin="0" aria-valuemax="100">{{ $five_star_count  > 0 ? $five_star_count/$total_count * 100 : 0 }}%</div>
                                        </div>
                                        <div class="progress"><span>4 star</span>
                                            <div class="progress-bar" role="progressbar" style="width: {{ $four_star_count  > 0 ? $four_star_count/$total_count * 100 : 0 }}%" aria-valuenow="{{ $four_star_count  > 0 ? $four_star_count/$total_count * 100 - 1 : 0 }}" aria-valuemin="0" aria-valuemax="100">{{ $four_star_count  > 0 ? $four_star_count/$total_count * 100 : 0 }}%</div>                                        </div>
                                        <div class="progress"><span>3 star</span>
                                            <div class="progress-bar" role="progressbar" style="width: {{ $three_star_count  > 0 ? $three_star_count/$total_count * 100 : 0 }}%" aria-valuenow="{{ $three_star_count  > 0 ? $three_star_count/$total_count * 100 - 1 : 0 }}" aria-valuemin="0" aria-valuemax="100">{{ $three_star_count  > 0 ? $three_star_count/$total_count * 100 : 0 }}%</div>                                        </div>
                                        <div class="progress"><span>2 star</span>
                                            <div class="progress-bar" role="progressbar" style="width: {{ $two_star_count  > 0 ? $two_star_count/$total_count * 100 : 0 }}%" aria-valuenow="{{ $two_star_count  > 0 ? $two_star_count/$total_count * 100 - 1 : 0 }}" aria-valuemin="0" aria-valuemax="100">{{ $two_star_count  > 0 ? $two_star_count/$total_count * 100 : 0 }}%</div>                                        </div>
                                        <div class="progress mb-30"><span>1 star</span>
                                            <div class="progress-bar" role="progressbar" style="width: {{ $one_star_count  > 0 ? $one_star_count/$total_count * 100 : 0 }}%" aria-valuenow="{{ $one_star_count  > 0 ? $one_star_count/$total_count * 100 - 1 : 0 }}" aria-valuemin="0" aria-valuemax="100">{{ $one_star_count  > 0 ? $one_star_count/$total_count * 100 : 0 }}%</div>                                        </div><a class="font-xs text-muted" >Aggregated rating from customer reviews.</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <script>
        // Select all elements with the "i" tag and store them in a NodeList called "stars"
        const stars = document.querySelectorAll(".stars i");
        // Loop through the "stars" NodeList
        stars.forEach((star, index1) => {
            // Add an event listener that runs a function when the "click" event is triggered
            star.addEventListener("click", () => {
                // Loop through the "stars" NodeList Again
                stars.forEach((star, index2) => {
                    document.querySelector(".review-rating").value = index1 + 1;
                    // Add the "active" class to the clicked star and any stars with a lower index
                    // and remove the "active" class from any stars with a higher index
                    index1 >= index2 ? star.classList.add("active") : star.classList.remove("active");
                });
            });
        });
    </script>
@endsection
