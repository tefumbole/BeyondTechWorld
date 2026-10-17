@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Projects & Fundraising</h3>
    @include('gbhs.admin.nav')
    <form class="card p-3" method="post" action="{{ route('gbhs.projects.store') }}" enctype="multipart/form-data">
        @csrf
        <input class="form-control" name="title" placeholder="Title" required>
        <textarea class="form-control mt-2" name="description" placeholder="Description"></textarea>
        <input class="form-control mt-2" name="target" placeholder="Target XAF">
        <input class="form-control mt-2" name="committee" placeholder="Committee">
        <input class="form-control mt-2" name="status" placeholder="planned, active, or closed">
        <input class="form-control mt-2" type="date" name="starts_on">
        <input class="form-control mt-2" type="date" name="ends_on">
        <input class="mt-2" type="file" name="image">
        <label><input type="checkbox" name="published" value="1"> Published</label>
        <button class="btn btn-primary btn-sm" type="submit">Save project</button>
    </form>
    @foreach($projects as $project)
        <div class="card p-3 mt-2">{{ $project->title }} · {{ $project->status }} · raised {{ number_format($raised[$project->id] ?? 0) }} of {{ number_format($project->target) }}</div>
    @endforeach
</div></section>
@endsection
