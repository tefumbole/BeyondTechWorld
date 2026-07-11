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
                    <div class="col-lg-6">
                        <form method="post" action="{{ route('service.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input name="product_id" type="hidden" value="{{ $product->id }}">
                            <input name="service_type" type="hidden" value="{{ $data['service_type'] }}">
                            <input name="academic_year" type="hidden" value="{{ $data['academic_year'] }}">
                            <input name="variant_id" type="hidden" value="{{ $data['variant_id'] }}">
                            <input name="number_of_pages" type="hidden" value="{{ $data['number_of_pages'] }}">
                            <input name="word_count" type="hidden" value="{{ $data['word_count'] }}">
                            <input name="spacing" type="hidden" value="{{ $data['spacing'] }}">
                            <input name="total" type="hidden" value="{{ $data['total'] }}">


                            <input name="subject" type="hidden" value="{{ $data['subject'] }}">
                            <input name="project_title" type="hidden" value="{{ $data['project_title'] }}">
                            <input name="project_guide_lines" type="hidden" value="{{ $data['project_guide_lines'] }}">
                            <input name="citation_style" type="hidden" value="{{ $data['citation_style'] }}">
                            <input name="font_style" type="hidden" value="{{ $data['font_style'] }}">
                            <input name="language" type="hidden" value="{{ $data['language'] }}">
                            <input name="references" type="hidden" value="{{ $data['references'] }}">
                            <input name="quality_double_checker" type="hidden" value="{{ isset($data['quality_double_checker']) ? 1 : 0 }}">
                            <input name="abstract_page" type="hidden" value="{{ isset($data['abstract_page']) ? 1 : 0 }}">
                            <input name="one_page_summary" type="hidden" value="{{ isset($data['one_page_summary']) ? 1 : 0 }}">
                            <input name="grammar_checker" type="hidden" value="{{ isset($data['grammar_checker']) ? 1 : 0 }}">
                            <input name="preferred_expert" type="hidden" value="{{ isset($data['preferred_expert']) ? 1 : 0 }}">
{{--                            files--}}
{{--                            <input name="sample_doc" type="file" value="{{ $data['sample_doc'] }}">--}}
{{--                            <input name="customer_doc" type="file" value="{{ $data['customer_doc'] }}">--}}

                            <div class="box-border">
                                <div class="box-payment"><a class="btn btn-gpay"><img src="{{ asset('public/assets/imgs/page/checkout/mtn.png') }}" alt="Ecom" width="170px"></a>
                                    <a class="btn btn-amazon"><img src="{{ asset('public/assets/imgs/page/checkout/cod.svg') }}" alt="COD" width="170px"></a></div>
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
                                            <h5 class="font-md-bold color-brand-3 mt-15 mb-20">Shipping address</h5>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input class="form-control font-sm"  name="name" type="text" placeholder="name*" required>
                                                <input name="email" type="hidden">
                                                <input name="city" type="hidden">
                                                <input name="state" type="hidden">
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input class="form-control font-sm" name="address" type="text" placeholder="Address(Quarter) *" required>
                                            </div>
                                        </div>
                                    @else
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input class="form-control font-sm"  name="phone" value="{{ auth()->user() ? auth()->user()->phone : '+237' }}" type="text" placeholder="Phone*" required>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <h5 class="font-md-bold color-brand-3 mt-15 mb-20">Shipping address</h5>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input class="form-control font-sm"  name="name" value="{{ auth()->user() ? auth()->user()->name : '' }}"type="text" placeholder="name*" required>
                                            </div>
                                        </div>
                                        <div class="col-lg-12">
                                            <div class="form-group">
                                                <input name="email" type="hidden">
                                                <input name="city" type="hidden">
                                                <input name="state" type="hidden">
                                                <input class="form-control font-sm" name="address" value="{{ auth()->user() && auth()->user()->customer ? auth()->user()->customer->address : '' }}" type="text" placeholder="Address *" required>
                                            </div>
                                        </div>
                                    @endif
                                    <div class="col-lg-12">
                                        <h5 class="font-md-bold color-brand-3 text-sm-start text-center my-3">Additional Document</h5>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <input class="form-control" name="sample_doc" type="file">
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <h5 class="font-md-bold color-brand-3 text-sm-start text-center my-3">Upload Cover Page</h5>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <input class="form-control" name="customer_doc" type="file">
                                        </div>
                                    </div>
                                    <div class="border-bottom-4 text-center mb-20">
                                        <div class="text-or font-md color-gray-500">Payment Method</div>
                                    </div>
{{--                                    <div class="col-lg-12">--}}
{{--                                        <h5 class="font-md-bold color-brand-3 text-sm-start text-center my-3">Payment Method</h5>--}}
{{--                                    </div>--}}
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
                                                       type="text" minlength="9" maxlength="9" class="form-control" name="mtn_phone" value="" placeholder="MTN Phone Number*" >
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group mb-0">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="payment_method" value="COD" id="cod" required>
                                                <label class="form-check-label" for="cod">Cash On Delivery</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="payment_method" value="MTN" id="mtn" required>
                                                <label class="form-check-label" for="mtn">Mobile Money</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-20">
                                <div class="col-lg-6 col-5 mb-20"><a class="btn font-sm-bold color-brand-1 arrow-back-1" href="{{ route('cart') }}">Return to Cart</a></div>
                                <div class="col-lg-6 col-7 mb-20 text-end"><button type="submit" class="btn btn-buy w-auto arrow-next" >Place an Order</button></div>
                            </div>
                        </form>
                    </div>

                        <input name="service_type" type="hidden" value="{{ $data['service_type'] }}">
                        <input name="academic_year" type="hidden" value="{{ $data['academic_year'] }}">
                        <input name="variant_id" type="hidden" value="{{ $data['variant_id'] }}">
                        <input name="number_of_pages" type="hidden" value="{{ $data['number_of_pages'] }}">
                        <input name="word_count" type="hidden" value="{{ $data['word_count'] }}">
                        <input name="spacing" type="hidden" value="{{ $data['spacing'] }}">
                    <div class="col-lg-6">
                        <div class="box-border">
                            <h5 class="font-md-bold mb-20">Service Detail</h5>
                            <div class="form-group mb-0">
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Name</span></div>
                                    <div class="col-lg-6 col-6 text-end">{{ $product->name }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Subject</span></div>
                                    <div class="col-lg-6 col-6 text-end">{{ $data['subject']}}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Project Title</span></div>
                                    <div class="col-lg-6 col-6 text-end">{{ $data['project_title'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Guide Line</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['project_guide_lines'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Service Type</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['service_type'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Academic Year</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['academic_year'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Deadline</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['variant_id'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Number of Pages</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['number_of_pages'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Word Count</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['word_count'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-3 col-3"><span class="font-md-bold color-brand-3">Spacing</span></div>
                                    <div class="col-lg-9 col-9 text-end">{{ $data['spacing'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Font Style</span></div>
                                    <div class="col-lg-6 col-6 text-end">{{ $data['font_style'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Language</span></div>
                                    <div class="col-lg-6 col-6 text-end">{{ $data['language'] }}</div>
                                </div>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">References</span></div>
                                    <div class="col-lg-6 col-6 text-end">{{ $data['references']}}</div>
                                </div>
                                <hr>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Addons</span></div>
                                    <ul class="list-disc">
                                        @if(isset($data['quality_double_checker']))<li> Quality Double Checker</li>@endif
                                        @if(isset($data['abstract_page']))<li> Abstract Page</li>@endif
                                        @if(isset($data['one_page_summary']))<li> One Page Summary</li>@endif
                                        @if(isset($data['grammar_checker']))<li> Grammar Checker</li>@endif
                                        @if(isset($data['preferred_expert']))<li> Preferred Expert</li>@endif

                                    </ul>
                                </div>
                                <hr>
                                <div class="row mb-10">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Subtotal</span></div>
                                    <div class="col-lg-6 col-6 text-end"><span class="font-lg-bold color-brand-3">{{ number_format($data['total'], 2) }} {{ $currency->code }}</span></div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6 col-6"><span class="font-md-bold color-brand-3">Total</span></div>
                                    <div class="col-lg-6 col-6 text-end"><span class="font-lg-bold color-brand-3">{{ number_format($data['total'], 2) }} {{ $currency->code }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
@endsection
