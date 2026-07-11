@extends('layout.main') @section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <a href="{{route('asset.dispose.list')}}" class="btn btn-info"><i class="dripicons-list"></i> Dispose Assets List </a>
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>Disposal Asset</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => ['asset.dispose.update'], 'method' => 'post', 'files' => true]) !!}
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Fixed Assets')}}</label><br>
                                    {{ $data->assets->name }}
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Disposal Method</label>
                                    <select name="type" id="select-method" class="selectpicker form-control" data-live-search="true" required>
                                        <option value="">  -- choose -- </option>
                                        <option value="Sold" {{ $data->method == 'Sold' ? 'selected' : '' }}>Sold</option>
                                        <option value="Discarded" {{ $data->method == 'Discarded' ? 'selected' : '' }}>Discarded</option>
                                        <option value="Scrapt" {{ $data->method == 'Scrapt' ? 'selected' : '' }}>Scrapt</option>
                                        <option value="Burnt" {{ $data->method == 'Burnt' ? 'selected' : '' }}>Burnt</option>
                                        <option value="Buried" {{ $data->method == 'Buried' ? 'selected' : '' }}>Buried</option>
                                        <option value="Donated" {{ $data->method == 'Donated' ? 'selected' : '' }}>Donated</option>
                                        <option value="Others" {{ $data->method == 'Others' ? 'selected' : '' }}>Others</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 others">
                                <div class="form-group">
                                    <label>Other Method</label>
                                    <input type="text" name="other" class="form-control" placeholder="other method" value="{{ $data->other }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Disposal Price</label>
                                    <input type="hidden" name="id" value="{{ $data->id }}">
                                    <input type="number" name="price" class="form-control" value="{{ $data->price }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Disposal Date</label>
                                    <input type="date" name="date" class="form-control" required value="{{ $data->date }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Remark</label>
                                    <input type="text" name="remarks" class="form-control" placeholder="Remark" value="{{ $data->remarks }}">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group mt-4">
                                    <input type="submit" value="Update Dispose" class="btn btn-primary" >
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

    $(".others").hide();

    if($('#select-method').val() == 'Others') {
        $(".others").show();
    }

    // on change hide and show
    $('select[name="type"]').on('change', function() {
        if ($(this).val() == 'Others') {
            $(".others").show(300);
        }else {
            $(".others").hide();
        }
    });

    // menu open
    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-dispose-menu").addClass("active");
</script>
@endsection
