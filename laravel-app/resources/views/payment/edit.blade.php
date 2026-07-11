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
                        <a href="{{route('payment.list')}}" class="btn btn-info"><i class="dripicons-list"></i> Payment Request List </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Payment Request Update</h4>
                        </div>
                        <div class="card-body">
                            {!! Form::open(['route' => ['payment.update', $data->id], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Payment Status <strong>*</strong> </label>
                                        <select name="status" class="form-control order-status">
                                            <option value="0" {{ $data->status == 0 ? 'selected' : '' }}>Pending</option>
                                            <option value="1" {{ $data->status == 1 ? 'selected' : '' }}>Complete</option>
                                            <option value="2" {{ $data->status == 2 ? 'selected' : '' }}>Rejected</option>
                                        </select>
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
        $("ul#payment_request").siblings('a').attr('aria-expanded','true');
        $("ul#payment_request").addClass("show");
        $("ul#payment_request #payment-list-menu").addClass("active");

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
