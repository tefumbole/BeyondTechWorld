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
                    @if(in_array("donor-index", $all_permission))
                        <a href="{{route('announcement.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Announcement List')}} </a>
                    @endif
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>{{trans('file.Update Announcement')}}</h4>
                        </div>
                        <div class="card-body">
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            {!! Form::open(['route' => ['announcement.update', $data->id], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>{{trans('file.name')}} <strong>*</strong> </label>
                                        <input type="text" name="name" required class="form-control" value="{{ $data->name }}">
                                        <input type="hidden" name="is_active" value="1">
                                    </div>
                                </div>
                                @if($data->people_type != 'csv')
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong> </label>
                                            <select name="to[]" required class="selectpicker form-control" data-live-search="true" multiple>
                                                <option value="">--choose any --</option>
                                                @foreach($user as $item)
                                                    <option {{ in_array($item->id, explode(",", $data->to)) ? 'selected' : '' }} value="{{$item->id}}">{{$item->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.CC')}} <strong>*</strong> </label>
                                            <select name="cc[]" class="selectpicker form-control" data-live-search="true" multiple>
                                                <option value="">--choose any --</option>
                                                @foreach($user as $item)
                                                    <option {{ in_array($item->id, explode(",", $data->cc)) ? 'selected' : '' }} value="{{$item->id}}">{{$item->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                @else
                                    <div class="col-md-6 csv">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <input type="file" name="to_csv" class="form-control to-csv" accept=".csv">
                                            <small style="color:#6c757d;display:block;margin-top:6px;">CSV columns (in order): name, phone_number, email, column1, column2, column3, column4, column5, column6, column7, column8, column9, column10.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 csv">
                                        <div class="form-group">
                                            <label>Old file <strong>*</strong></label>
                                            <a target=_blank"" href="{{url('public/announcement/csv',$data->to)}}"><span class="fa fa-download"></span> Download Old CSV</a>
                                        </div>
                                    </div>
                                @endif

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>{{trans('file.Subject')}} <strong>*</strong> </label>
                                        <input type="text" name="subject" required class="form-control"  value="{{ $data->subject }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Schedule Date & Time</label>
                                        <input type="datetime-local" name="date_time" class="form-control" value="{{ optional($data->date_time ? \Carbon\Carbon::parse($data->date_time) : null)->format('Y-m-d\\TH:i') }}">
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
                                                    <img src="{{url('public/announcement/attachment', $data->attachment)}}" height="60" width="60">
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
                                                        <img src="{{url('public/announcement/attachment', $image->attachment)}}" height="60" width="60">
                                                    </td>
                                                    <td><a href="{{route('announcement.attachment.delete', ['id' => $image->id])}}" class="btn btn-sm btn-danger remove-img">X</a></td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>{{trans('file.Header')}} <strong>*</strong> </label>
                                        <textarea name="header" class="form-control" rows="2">{{ $data->header }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>{{trans('file.Body')}} <strong>*</strong> </label>
                                        <div class="mb-2" style="font-size:12px;color:#6c757d;">
                                        Use placeholders to personalize your message: [name], [phone_number], [email], [column1] .. [column10] in csv.
                                        </div>
                                        <textarea name="body" class="form-control" rows="4">{{ $data->body }}</textarea>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label>{{trans('file.Footer')}} <strong>*</strong> </label>
                                        <textarea name="footer" class="form-control" rows="2">{{ $data->footer }}</textarea>
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


        $("ul#letter").siblings('a').attr('aria-expanded','true');
        $("ul#letter").addClass("show");
        $("ul#letter #announcement-menu").addClass("active");


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
