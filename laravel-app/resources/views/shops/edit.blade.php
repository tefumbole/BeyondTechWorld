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
                        <a href="{{route('shop.index')}}" class="btn btn-info"><i class="dripicons-list"></i> Shop Listing </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Shop Update</h4>
                        </div>
                        <div class="card-body">
                            {!! Form::open(['route' => ['shop.update', $data->id], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Shop Status <strong>*</strong> </label>
                                        <select name="is_active" class="form-control order-status">
                                            <option value="0" {{ $data->is_active == 0 ? 'selected' : '' }}>In Active</option>
                                            <option value="1" {{ $data->is_active == 1 ? 'selected' : '' }}>Active</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Admin commission <strong>*</strong> </label>
                                        <input type="number" name="commission" class="form-control" value="{{ $data->commission }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Can Donation <strong>*</strong> </label>
                                        <select name="can_donation" class="form-control">
                                            <option value="0" {{ $data->can_donation == 0 ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ $data->can_donation == 1 ? 'selected' : '' }}>Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Can Service <strong>*</strong> </label>
                                        <select name="can_service" class="form-control">
                                            <option value="0" {{ $data->can_service == 0 ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ $data->can_service == 1 ? 'selected' : '' }}>Yes</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Can Booking <strong>*</strong> </label>
                                        <select name="can_booking" class="form-control">
                                            <option value="0" {{ $data->can_booking == 0 ? 'selected' : '' }}>No</option>
                                            <option value="1" {{ $data->can_booking == 1 ? 'selected' : '' }}>Yes</option>
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
