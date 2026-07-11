@extends('layout.main') @section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if(in_array("donor-index", $all_permission))
                    <a href="{{route('letter.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Letters List')}} </a>
                @endif
                @if(in_array("letter_send", $all_permission))
                    @if($data->is_sign == 1 && $data->is_approve == 1 && $data->is_sent == 1)
                        <a href="{{ route('letter.send.whatsapp', $data->id) }}" class="btn btn-success"><i class="fa fa-whatsapp"></i> Send Whatsapp PDF</a>
                        <a href="{{ route('letter.send.mail', $data->id) }}" class="btn btn-primary"><i class="dripicons-mail"></i> Send Mail</a>
                        <a href="{{ route('letter.send.download', $data->id) }}" class="btn btn-warning"><i class="dripicons-download"></i> Download PDF</a>
                        <a href="{{ route('letter.send.print', $data->id) }}" class="btn btn-info"><i class="dripicons-print"></i> Print</a>
                    @endif
                @endif
                <div class="card">
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

                    <div class="card-body align-items-center" id="letter-body">
                        @include('letter.letter_body')
                    </div>
                </div>
                <div class="pull-right">
                    @if($data->is_edit == 0)
                        @if(in_array("letter_edit", $all_permission))
                            <a href="{{ route('letter.edit', $data->id) }}" class="btn btn-warning"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a>
                        @endif
                    @endif

                    @if($data->is_edit == 0 && $data->is_rejected == 0)
                        @if(in_array("letter_edit", $all_permission))
                                <a href="{{ route('letter.edit.ok', $data->id) }}" class="btn btn-success"><i class="fa fa-check"></i> OK</a>
                            @endif
                        @endif



                    @if($data->is_approve == 0 && $data->is_rejected == 0 && $data->is_edit == 1)
                        @if(in_array("letter_approve", $all_permission))
                            <a href="{{ route('letter.approve', $data->id) }}" class="btn btn-success"><i class="fa fa-check"></i> {{trans('file.Approve')}}</a>
                        @endif
                    @endif
                    @if($data->is_approve == 0 && $data->is_rejected == 0)
                        @if(in_array("letter_rejected", $all_permission))
                            <a href="{{ route('letter.reject', $data->id) }}" class="btn btn-danger"><i class="fa fa-close"></i> Reject</a>
                        @endif
                    @endif

                    @if(in_array("letter_sign", $all_permission))
                        @if($data->is_sign == 0 && $data->is_approve == 1)
                            <a href="{{ route('letter.sign', $data->id) }}" class="btn btn-success"><i class="fa fa-pencil"></i> Sign</a>
                            <a href="{{ route('letter.sign.send', $data->id) }}" class="btn btn-info"><i class="fa fa-send"></i> Sign & Send</a>
                        @endif
                    @endif

                    @if(in_array("letter_send", $all_permission))
                        @if($data->is_sign == 1 && $data->is_approve == 1 && $data->is_sent == 0)
                            <a href="{{ route('letter.send', $data->id) }}" class="btn btn-success"><i class="fa fa-send"></i> Send</a>
                            <a href="{{ route('letter.edit.last', $data->id) }}" class="btn btn-warning"><i class="fa fa-pencil"></i> {{trans('file.edit')}}</a>
                        @endif
                    @endif

                    @if(in_array("letter_send", $all_permission))
                        @if($data->is_sign == 1 && $data->is_approve == 1 && $data->is_sent == 1)
                            <a href="{{ route('letter.send', $data->id) }}" class="btn btn-success"><i class="fa fa-send"></i> Send Again</a>
                            <a href="{{ route('letter.edit.last', $data->id) }}" class="btn btn-warning"><i class="fa fa-pencil"></i> {{trans('file.edit')}}</a>
                        @endif
                    @endif

                    <a href="{{ route('letter.prev', ['id' => $data->id]) }}" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Prev</a>
                    <a href="{{ route('letter.next', ['id' => $data->id]) }}" class="btn btn-primary">Next <i class="fa fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">

    $("#print-btn").on("click", function(){
        var divToPrint=document.getElementById('letter-body');
        var newWin=window.open('','Print-Window');
        newWin.document.open();
        newWin.document.write('<link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">'+divToPrint.innerHTML+'</body>');
        newWin.document.close();
        setTimeout(function(){newWin.close();},30);
    });


    $("ul#letter").siblings('a').attr('aria-expanded','true');
    $("ul#letter").addClass("show");
    $("ul#letter").addClass("active");

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
