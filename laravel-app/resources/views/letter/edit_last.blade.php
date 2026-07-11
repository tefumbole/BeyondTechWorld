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
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            {!! Form::open(['route' => ['letter.update.last', $data->id], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                @if($user)
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
                                    <div class="col-md-12 csv">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <input type="file" name="to_csv" class="form-control to-csv" accept=".csv">
                                        </div>
                                    </div>
                                    <div class="col-md-6 csv">
                                        <div class="form-group">
                                            <label>Old file <strong>*</strong></label>
                                            <a target=_blank"" href="{{url('public/letter/csv',$data->to)}}"><span class="fa fa-download"></span> Download Old CSV</a>
                                        </div>
                                    </div>
                                @endif
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
