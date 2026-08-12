@extends('layout.main')
@section('content')

@if($errors->any())
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        @foreach($errors->all() as $error) {{ $error }} <br> @endforeach
    </div>
@endif

<section>
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h4>Create Template</h4></div>
            <div class="card-body">
                <form action="{{ route('online_invitation.templates.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                                <div class="form-group">
                                    <label>Background Image</label>
                                    <input type="file" name="background_image" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Any portrait image is fine — it will be auto-fitted to 1024×1536 px.</small>
                                </div>
                            </div>
                        </div>
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit">{{ trans('file.submit') }}</button>
                        <a class="btn btn-link" href="{{ route('online_invitation.templates.index') }}">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    $("ul#online_invitation #online-invitation-template-menu").addClass("active");
</script>

@endsection
