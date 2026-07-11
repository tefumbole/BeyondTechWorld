@extends('layout.main') @section('content')
<section class="forms">
    <div class="container-fluid">
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

        <div class="row">
            <div class="col-md-12">
                @if(in_array("donor-index", $all_permission))
                    <a href="{{route('letter.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Letters List')}} </a>
                @endif
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <div class="row">
                            <div class="col-md-12">
                                <form method="POST" action="{{ route('letter.reject.store', [$data->id]) }}">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                @if(Auth::user()->otp_verify == 0)
                                                    <label>OTP <strong>*</strong> </label>
                                                    <input type="number" placeholder="One Time Password" name="otp" required class="form-control">
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <button class="btn btn-default"><i class="fa fa-close"></i> Reject</button>
                                </form>

                            </div>
                        </div>
                    </div>
                    <div class="card-body align-items-center" id="letter-body">
                        @include('letter.letter_body')
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">

    $("ul#letter").siblings('a').attr('aria-expanded','true');
    $("ul#letter").addClass("show");
    $("ul#letter #letter-rejected-menu").addClass("active");


</script>
@endsection
