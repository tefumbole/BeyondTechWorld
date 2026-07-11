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
                                <form class="form-search" method="get" action="{{ route('frontend.product.search.vendor') }}">
                                    @csrf
                                    <div class="box-keysearch">
                                        <label class="control-label color-danger">Search Vendor</label>
                                        <input class="form-control font-xs" type="text" name="search" value="" placeholder="Search Vendor">
                                    </div>
                                </form>
                            </div>
                    <div class="col-lg-12 order-first order-lg-last">

                        <div class="row mt-20" id="shop-products">
                            @foreach($vendors as $vendor)
                                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                                    <div class="card-grid-style-3">
                                        <div class="card-grid-inner">
                                            <div class="image-box">
                                                <a href="{{ route('vendor.products', ['id' => $vendor->id] ) }}">
                                                    <img src="{{ url('public/images/user', $vendor->sign) }}" alt="Vendor">
                                                </a>
                                            </div>
                                            <div class="info-right"><a class="font-xs color-gray-500" href="">{{ $vendor->name }}</a><br>
                                                <a class="color-brand-3 font-sm-bold" href="{{ route('vendor.products', ['id' => $vendor->id] ) }}">{{ $vendor->company_name }}</a>
                                                <div class="mt-20 box-btn-cart"><a class="btn btn-cart" href="{{ route('vendor.products', ['id' => $vendor->id] ) }}">Products</a></div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <nav>
                            <ul class="pagination">
                                {{ $vendors->links() }}
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </main>

@endsection
