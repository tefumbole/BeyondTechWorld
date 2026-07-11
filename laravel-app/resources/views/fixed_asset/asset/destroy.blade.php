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
                        {!! Form::open(['route' => ['asset.dispose'], 'method' => 'post', 'files' => true]) !!}
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{trans('file.Fixed Assets')}}</label>
                                    <select name="asset_id[]" class="selectpicker form-control" data-live-search="true" multiple required>
                                        <option value="">  -- choose -- </option>
                                        @foreach($assets as $item)
                                            <option value="{{ $item->id }}" {{ @$data->id == $item->id ? 'selected' : '' }}> {{ $item->name }} - {{ $item->serial_no }} </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Disposal Method</label>
                                    <select name="type" class="selectpicker form-control" data-live-search="true" required>
                                        <option value="">  -- choose -- </option>
                                        <option value="Sold">Sold</option>
                                        <option value="Discarded">Discarded</option>
                                        <option value="Scrapt">Scrapt</option>
                                        <option value="Burnt">Burnt</option>
                                        <option value="Buried">Buried</option>
                                        <option value="Donated">Donated</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 others">
                                <div class="form-group">
                                    <label>Other Method</label>
                                    <input type="text" name="other" class="form-control" placeholder="other method">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Disposal Price</label>
                                    <input type="number" name="price" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Disposal Date</label>
                                    <input type="date" name="date" class="form-control" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Remark</label>
                                    <input type="text" name="remarks" class="form-control" placeholder="Remark">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group mt-4">
                                    <input type="submit" value="Dispose" class="btn btn-primary" >
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
