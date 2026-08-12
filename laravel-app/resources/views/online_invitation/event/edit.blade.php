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
            <div class="card-header"><h4>Edit Event</h4></div>
            <div class="card-body">
                <form action="{{ route('online_invitation.events.update', $data->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Name *</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $data->name) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date & Time *</label>
                                <input type="datetime-local" name="event_at" class="form-control" value="{{ old('event_at', \Carbon\Carbon::parse($data->event_at)->format('Y-m-d\\TH:i')) }}" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" class="form-control" value="{{ old('location', $data->location) }}">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Template</label>
                                <select name="template_id" class="form-control">
                                    <option value="">Select Template</option>
                                    @foreach($templates as $t)
                                        <option value="{{ $t->id }}" {{ old('template_id', $data->template_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="3">{{ old('description', $data->description) }}</textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Allowed Categories</label>
                                @php($selected = collect(old('category_ids', $data->categories->pluck('id')->toArray())))
                                <select name="category_ids[]" class="form-control" multiple>
                                    @foreach($categories as $c)
                                        <option value="{{ $c->id }}" {{ $selected->contains($c->id) ? 'selected' : '' }}>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Hold Ctrl (or Cmd) to select multiple.</small>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <button class="btn btn-primary" type="submit">{{ trans('file.submit') }}</button>
                        <a class="btn btn-link" href="{{ route('online_invitation.events.index') }}">Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    $("ul#online_invitation #online-invitation-event-menu").addClass("active");
</script>

@endsection

