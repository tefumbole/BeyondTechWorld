@extends('frontend.layout.main')
@section('content')
    <style>
        .card h6 {
            margin-top: 10%;
            margin-bottom: 2%;
        }
        .animation{
            color: red;
            animation-name: example;
            animation: example 1s infinite;
        }
        @keyframes example {
            0%   {color:red; font-size:50px}
            25%  {color:black; font-size:49px}
            50%  {color:blue; font-size:50px}
            75%  {color:green; font-size:49px}
            100% {color:orange; font-size:50px}
        }
        .card {
            border: 30px solid rgba(0, 0, 0, .125);
        }
    </style>

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
                    @if(session()->has('not_permitted'))
                        <div class="alert alert-danger alert-dismissible text-center">{{ session()->get('not_permitted') }}</div>
                    @endif
                    <div class="col-lg-12 order-first order-lg-last">
                        <form class="card p-5" action="{{ route('service.order') }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-md-8">
                                    <h4>Calculate Your Service Price</h4><hr>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h6>Type Of Work <span class="text-danger">*</span></h6>
                                            <select id="product_id" name="product_id" class="form-control" required>
                                                <option value="">--choose--</option>
                                                @foreach($varientProducts as $item)
                                                    <option value="{{ $item->id }}">{{  $item->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Type of Service <span class="text-danger">*</span></h6>
                                            <select name="service_type" class="form-control service_type" onchange="updatePrice()" required>
                                                <option value="Writing">Writing</option>
                                                <option value="Editing">Editing</option>
                                                <option value="Proofreading">Proofreading</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Academic Level <span class="text-danger">*</span></h6>
                                            <select name="academic_year" class="form-control academic_year" onchange="updatePrice()" required>
                                                <option value="First Cycle">First Cycle</option>
                                                <option value="High School">High School</option>
                                                <option value="College" selected>College</option>
                                                <option value="Undergraduate">Undergraduate</option>
                                                <option value="Masters">Masters</option>
                                                <option value="PHD">Ph.D.</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Deadline  <span class="text-danger">*</span></h6>
                                            <select name="variant_id" class="form-control variant_id" onchange="updatePrice()" required>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Number of pages  <span class="text-danger">*</span></h6>
                                            <input type="number" name="number_of_pages" required class="form-control number_of_pages" value="1" onchange="updatePrice()">
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Words Count  <span class="text-danger">*</span></h6>
                                            <input type="text" name="word_count" readonly class="form-control word_count" value="275">
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Project Title</h6>
                                            <input type="text" name="project_title" class="form-control" placeholder="Project Title">
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Subject</h6>
                                            <input type="text" name="subject" class="form-control" placeholder="Subject">
                                        </div>
                                        <div class="col-md-4">
                                            <h6>References</h6>
                                            <input type="number" name="references" class="form-control" placeholder="1">
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Citation Sytle</h6>
                                            <select name="citation_style" class=" form-control">
                                                <option value="Non Specific">Non Specific</option>
                                                <option value="APA">APA</option>
                                                <option value="CBE">CBE</option>
                                                <option value="Chicago">Chicago</option>
                                                <option value="Harvard">Harvard</option>
                                                <option value="MLA">MLA</option>
                                                <option value="Oxford">Oxford</option>
                                                <option value="Turabian">Turabian</option>
                                                <option value="Vancouer">Vancouer</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Font Style</h6>
                                            <select name="font_style" class=" form-control">
                                                <option value="Calibri (Standard)">Calibri (Standard)</option>
                                                <option value="Arial">Arial</option>
                                                <option value="Times New Romans">Times New Romans</option>
                                                <option value="Verdana">Verdana</option>
                                                <option value="Georgia">Georgia</option>
                                                <option value="Trebuchet MS">Trebuchet MS</option>
                                                <option value="Courier New">Courier New</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <h6>Language</h6>
                                            <select name="language" class=" form-control">
                                                <option value="US English">US English</option>
                                                <option value="UK English">UK English</option>
                                                <option value="French">French</option>
                                                <option value="Oku">Oku</option>
                                            </select>
                                        </div>
{{--                                        <div class="col-md-4">--}}
{{--                                            <h6>Attach File</h6>--}}
{{--                                            <input type="file" name="customer_doc" class="form-control">--}}
{{--                                        </div>--}}
{{--                                        <div class="col-md-4">--}}
{{--                                            <h6>Cover Letter or Sample Doc</h6>--}}
{{--                                            <input type="file" name="sample_doc" class="form-control">--}}
{{--                                        </div>--}}
                                        <div class="col-md-12 mt-4">
                                            <h5 style="line-height: 1">Project Guide Lines</h5>
                                            <textarea name="project_guide_lines" class="form-control" placeholder="Project Guide Lines"></textarea>
                                        </div>
                                        <div class="col-md-8">
                                            <h6>Spacing  <span class="text-danger">*</span></h6>
                                            <div class="form-group mb-0">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" checked onchange="updatePrice()" type="radio" name="spacing" value="double-spacing" id="double-spacing" required>
                                                    <label class="form-check-label" for="double-spacing">Double Spacing</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" onchange="updatePrice()" type="radio" name="spacing" value="single-spacing" id="single-spacing" required>
                                                    <label class="form-check-label" for="single-spacing">Single Spacing</label>
                                                </div>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="col-md-12">
                                            <h5>Addons</h5>
                                            <input class="form-check-input" onchange="updatePrice()" type="checkbox" name="preferred_expert" value="1" id="preferred_expert">
                                            <label class="form-check-label" for="preferred_expert">Choose Preferred Expert (+8,750)</label>
                                            <br>
                                            <br>
                                            <input class="form-check-input" onchange="updatePrice()" type="checkbox" name="grammar_checker" value="1" id="grammar_checker">
                                            <label class="form-check-label" for="grammar_checker">Grammar Checker Report (+6,750)</label>
                                            <br>
                                            <br>
                                            <input class="form-check-input" onchange="updatePrice()" type="checkbox" name="one_page_summary" value="1" id="one_page_summary">
                                            <label class="form-check-label" for="one_page_summary">One Page Summary (+12,000)</label>
                                            <br>
                                            <br>
                                            <input class="form-check-input" onchange="updatePrice()" type="checkbox" name="abstract_page" value="1" id="abstract_page">
                                            <label class="form-check-label" for="abstract_page">Abstract Page (+12,000)</label>
                                            <br>
                                            <br>
                                            <input class="form-check-input" onchange="updatePrice()" type="checkbox" name="quality_double_checker" value="1" id="quality_double_checker">
                                            <label class="form-check-label" for="quality_double_checker">Quality Double Checker (+3,000)</label>
                                            <br>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 text-center mt-5">
                                    <br><br>
                                    <img src="{{ asset('public/assets/imgs/page/homepage1/assessment.jpg') }}">
                                    <h4>Total Price</h4><hr>
                                    <input type="hidden" name="actual-price" class="actual-price" value="0">
                                    <input type="hidden" name="total" class="hidden-price" value="0">
                                    <h2 class="m-5 animation"><span class="display-price">0</span> {{ $currency->code }}</h2>
                                    <input type="submit" value="Submit Order" class="btn btn-buy">
                                    <br><br><br>
                                    <img src="{{ asset('public/assets/imgs/page/homepage1/quality.jpg') }}">
                                </div>
                            </div>
                        </form>
                        <br><br><br>
                        <h3>Other Services</h3>
                        <hr>
                        <div class="row mt-20" id="shop-products">
                            @foreach($products as $product)
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                                    <div class="card-grid-style-3">
                                        <div class="card-grid-inner">
{{--                                            <div class="tools"><a class="btn btn-trend btn-tooltip mb-10" href="#" aria-label="Trend" data-bs-placement="left"></a><a class="btn btn-wishlist btn-tooltip mb-10" href="shop-wishlist.html" aria-label="Add To Wishlist"></a><a class="btn btn-compare btn-tooltip mb-10" href="shop-compare.html" aria-label="Compare"></a><a class="btn btn-quickview btn-tooltip" aria-label="Quick view" href="#ModalQuickview" data-bs-toggle="modal"></a></div>--}}
                                            <div class="image-box">
{{--                                                <span class="label bg-brand-2">-17%</span>--}}
                                                <a href="{{ route('service.detail', ['id' => $product->id] ) }}">
                                                    @php
                                                        $product_image = explode(",", $product->image);
                                                        $product_image = htmlspecialchars($product_image[0]);
                                                     @endphp
                                                    <img src="{{ url('public/images/product', $product_image) }}" alt="Ecom">
                                                </a>
                                            </div>
                                            <div class="info-right"><a class="font-xs color-gray-500" href="">{{ @$product->category->name }}</a><br>
                                                <a class="color-brand-3 font-sm-bold" href="{{ route('single.service', ['id' => $product->id] ) }}">{{ $product->name }}</a>
                                                <div class="rating">
                                                    <div class="product-rate d-inline-block">
                                                        <div class="product-rating" style="width: {{ $product->rating * 20 }}%"></div>
                                                    </div>
                                                    <span class="font-xs color-gray-500">({{ count($product->reviews) }})</span>
                                                </div>
                                                    <h5 class="color-brand-3 price-main d-inline-block mr-10">{{ number_format($product->price, 2) }} {{ $currency->code }}</h5>
                                                <div class="mt-20 box-btn-cart"><a class="btn btn-cart" href="{{ route('single.service', ['id' => $product->id] ) }}">Get Service</a></div>

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
@section('script')
    <script>

         function updatePrice() {
            service_type = $('.service_type').val();
            price = $('.actual-price').val();
            academic_year = $('.academic_year').val();
            spacing = $('input[name="spacing"]:checked').val();

            variant_price = $('.variant_id').find(':selected').attr('data-price');

            // deadline
            // price = +variant_price + +price;
            price = +price + +(variant_price/100*price)

            // number of pages & spacing
            number_of_pages = $('.number_of_pages').val();
            if(spacing == 'single-spacing') {
                $('.word_count').val(number_of_pages*275*2);
                price = price*2;
            } else {
                $('.word_count').val(number_of_pages*275);
            }
            price = price * number_of_pages;

             // service type
             switch(service_type) {
                 case 'Editing':
                     price = price/100*50;
                     break;
                 case 'Proofreading':
                     price = price/100*40;
                     break;
             }

             // academic year
             switch(academic_year) {
                 case 'First Cycle':
                     price =  price - price/100*18;
                     break;
                 case 'High School':
                     price =  price - price/100*18;
                     break;
                 // case 'College':
                 //     price = +price/100*10 + +price;
                 //     break;
                 case 'Undergraduate':
                     price =  price - price/100*9;
                     break;
                 // case 'Masters':
                 //     price = +price/100*30 + +price;
                 //     break;
                 case 'PHD':
                     price = +price/100*9 + +price;
                     break;
             }

             // addons
             if($('#preferred_expert').is(':checked')) {
                 price = 8750 + price;
             }
             if($('#grammar_checker').is(':checked')) {
                 price = 6750 + price;
             }
             if($('#one_page_summary').is(':checked')) {
                 price = 12000 + price;
             }
             if($('#abstract_page').is(':checked')) {
                 price = 12000 + price;
             }
             if($('#quality_double_checker').is(':checked')) {
                 price = 3000 + price;
             }

            $('.hidden-price').val(Math.round(price, 2));
            $('.display-price').text(addCommas(price));
        }

         function addCommas(nStr)
         {
             nStr += '';
             x = nStr.split('.');
             x1 = x[0];
             x2 = x.length > 1 ? '.' + x[1] : '';
             var rgx = /(\d+)(\d{3})/;
             while (rgx.test(x1)) {
                 x1 = x1.replace(rgx, '$1' + ',' + '$2');
             }
             return x1 + x2;
         }

        $('#product_id').on('change', function() {
            var id = $(this).val();
            if(id) {
                $.ajax({
                    url: '/service/variant/'+id,
                    type: 'get',
                    data: {
                        id: id
                    },
                    dataType: 'JSON',
                    success: function (data) {
                        $('.variant_id').html(data['html']);

                        variant_price = $('.variant_id').find(':selected').attr('data-price');
                        // console.log(variant_price, data['price'])
                        if(variant_price == undefined) {
                            variant_price = $('.variant_id').find('.variant_id option:first').attr('data-price');
                        }
                        price = +data['price'] + +(variant_price/100*data['price']);
                        $('.hidden-price').val(price);
                        $('.actual-price').val(data['price']);
                        $('.display-price').text(addCommas(price));
                    }
                });
            } else {
                $('.variant_id').html('');
            }

        });
    </script>
@endsection
