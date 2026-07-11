@extends('layout.main') @section('content')

    @if($errors->has('name'))
        <div class="alert alert-danger alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif

    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    @if(in_array("orders-index", $all_permission))
                        <a href="{{route('order.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Order List')}} </a>
                    @endif
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Order Update</h4>
                        </div>
                        <div class="card-body">
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            {!! Form::open(['route' => ['order.update', $data->id], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Order Status <strong>*</strong> </label>
                                        <select name="order_status" class="form-control order-status">
                                            <option value="0" {{ $data->order_status == 0 ? 'selected' : '' }}>Pending</option>
                                            <option value="1" {{ $data->order_status == 1 ? 'selected' : '' }}>Complete</option>
                                            <option value="3" {{ $data->order_status == 3 ? 'selected' : '' }}>Ready For Delivery</option>
                                            <option value="2" {{ $data->order_status == 2 ? 'selected' : '' }}>Rejected</option>
                                        </select>
                                        <input type="hidden" name="id" value="{{ $data->id }}">
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Payment Status <strong>*</strong> </label>
                                        <select name="payment_status" class="form-control order-status">
                                            <option value="0" {{ $data->payment_status == 0 ? 'selected' : '' }}>Pending</option>
                                            <option value="1" {{ $data->payment_status == 1 ? 'selected' : '' }}>Complete</option>
                                            <option value="2" {{ $data->payment_status == 2 ? 'selected' : '' }}>Rejected</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12 delivery-date">
                                    <div class="form-group mt-4">
                                        <label>Delivery Expected Date <strong>*</strong> </label>
                                        <input type="date" name="delivery_date" value="{{ $data->delivery_date }}" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mt-4">
                                        <label>Is Approve </label>
                                        <input name="is_approve" value="1" type="checkbox" {{ $data->is_approve == 1 ? 'checked' : '' }}>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group mt-4">
                                        <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                                    </div>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">
        $("ul#order").siblings('a').attr('aria-expanded','true');
        $("ul#order").addClass("show");
        $("ul#order #order-list-menu").addClass("active");

        if($('.order-status').val() != 3) {
            $('.delivery-date').hide();
        }

        $('.order-status').on('change', function() {
            var status = $(this).val();
            if(status == 3) {
                $('.delivery-date').show(300);
            } else {
                $('.delivery-date').hide(300);
            }
        });

        tinymce.init({
            selector: 'textarea',
            height: 130,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor textcolor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table contextmenu paste code wordcount'
            ],
            toolbar: 'insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
            branding:false
        });

    </script>
@endsection
