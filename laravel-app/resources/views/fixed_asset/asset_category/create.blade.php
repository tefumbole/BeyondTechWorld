@extends('layout.main') @section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if(in_array("asset-type-index", $all_permission))
                    <a href="{{route('assetCategory.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Assets Type List')}} </a>
                @endif
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Add Assets Type')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'assetCategory.store', 'method' => 'post', 'files' => true]) !!}
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{trans('file.name')}} *</strong> </label>
                                    <input type="text" name="name" required class="form-control">
                                </div>
                            </div>
                            @php
                            $parent_category = \App\AssetCategory::where('is_active', true)->where('parent_id', 0)->get();
                            @endphp
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{trans('file.Parent Category')}}</label>
                                    <select name="parent_id" class="form-control">
                                        <option value="0"> -- choose --</option>
                                        @foreach($parent_category as $cat)
                                            <option value="{{ $cat->id }}"> {{ $cat->name }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>{{trans('file.Image')}} </label>
                                    <input type="file" name="image" class="form-control">
                                </div>
                            </div>  <div class="col-md-12">
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
    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-category-menu").addClass("active");
</script>
@endsection
