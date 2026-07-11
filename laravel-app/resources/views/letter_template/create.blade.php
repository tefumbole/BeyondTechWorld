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
                        <h4>{{trans('file.Add Letter')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'letter.store', 'method' => 'post', 'files' => true]) !!}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Letter Category')}} </label>
                                    <select class="form-control" name="category_id">
                                        <option value="">--choose any --</option>
                                    @foreach($category as $cat)
                                            <option value="{{$cat->id}}">{{$cat->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Letter Template')}} </label>
                                    <select class="form-control" name="template_id">
                                        <option value="">--choose any --</option>
                                        @foreach($template as $tem)
                                            <option value="{{$tem->id}}">{{$tem->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.name')}} <strong>*</strong> </label>
                                    <input type="text" name="name" required class="form-control">
                                    <input type="hidden" name="is_active" value="1">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.To')}} <strong>*</strong> </label>
                                    <select name="to[]" required class="selectpicker form-control" data-live-search="true" multiple>
                                        <option value="">--choose any --</option>
                                        @foreach($user as $u)
                                            <option value="{{$u->id}}">{{$u->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.CC')}} <strong>*</strong> </label>
                                    <select name="cc[]" required class="selectpicker form-control" data-live-search="true" multiple>
                                        <option value="">--choose any --</option>
                                        @foreach($user as $u)
                                            <option value="{{$u->id}}">{{$u->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.Header')}} <strong>*</strong> </label>
                                    <textarea name="header" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.Subject')}} <strong>*</strong> </label>
                                    <input type="text" name="subject" required class="form-control">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.Body')}} <strong>*</strong> </label>
                                    <textarea name="body" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>{{trans('file.Footer')}} <strong>*</strong> </label>
                                    <textarea name="footer" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <input type="checkbox" name="is_template"> &nbsp;
                                    <label><b>{{trans('file.Is Template')}}</b> </label>
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
    $("ul#letter #letter-create-menu").addClass("active");

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
