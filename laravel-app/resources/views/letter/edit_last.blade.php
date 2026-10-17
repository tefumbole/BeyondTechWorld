@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    @if(in_array("donor-index", $all_permission))
                        <a href="{{route('letter.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Letters List')}} </a>
                    @endif
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>{{trans('file.Update Letter')}}</h4>
                        </div>
                        <div class="card-body">
                            @if(session()->has('not_permitted'))
                                <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
                            @endif
                            <p class="italic"><small>People already on this letter stay selected. Add customers, a WhatsApp group, a phone number, or CC, then send it again.</small></p>
                            {!! Form::open(['route' => ['letter.update.last', $data->id], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-md-12">
                                    <input type="hidden" name="people_type" value="directory">
                                    @include('letter.partials.directory_recipient_picker')
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.Comment')}} </label>
                                        <input type="text" name="comment" class="form-control"  value="{{ $data->comment }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Attachment </label>
                                        <input type="file" name="attachments[]" multiple class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <table class="table table-hover">
                                            <thead>
                                            <tr>
                                                <th><button type="button" class="btn btn-sm"><i class="fa fa-trash"></i></button></th>
                                                <th>Attachment</th>
                                                <th>Remove</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td><button type="button" class="btn btn-sm"><i class="fa fa-trash"></i></button></i></td>
                                                <td>
                                                    <img src="{{url('public/letter/attachment', $data->attachment)}}" height="60" width="60">
                                                </td>
                                                <td>Default image</td>
                                            </tr>
                                            @foreach($data->attachmentLib as $key => $image)
                                                @if($key == 0)
                                                    @continue
                                                @endif
                                                <tr>
                                                    <td><button type="button" class="btn btn-sm"><i class="fa fa-trash"></i></button></i></td>
                                                    <td>
                                                        <img src="{{url('public/letter/attachment', $image->attachment)}}" height="60" width="60">
                                                    </td>
                                                    <td><a href="{{route('letter.attachment.delete', ['id' => $image->id])}}" class="btn btn-sm btn-danger remove-img">X</a></td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group mt-4">
                                        <button type="submit" name="send_again" value="0" class="btn btn-primary">Save recipients</button>
                                        @if((int) $data->is_sent === 1 || ((int) $data->is_sign === 1 && (int) $data->is_approve === 1))
                                            <button type="submit" name="send_again" value="1" class="btn btn-success">Save and send again</button>
                                        @endif
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
        $("ul#letter").siblings('a').attr('aria-expanded','true');
        $("ul#letter").addClass("show");
        $("ul#letter #letter-index-menu").addClass("active");

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
