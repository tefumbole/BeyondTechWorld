@extends('layout.main')
@section('content')

@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('not_permitted') }}
    </div>
@endif
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {!! session()->get('message') !!}
    </div>
@endif

<section>
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <a href="{{ route('online_invitation.events.create') }}" class="btn btn-info mb-2"><i class="dripicons-plus"></i> Add Event</a>

            <form method="GET" action="{{ route('online_invitation.events.index') }}" class="form-inline mb-2">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search events..." value="{{ request('q') }}">
                    <div class="input-group-append">
                        <button class="btn btn-secondary" type="submit"><i class="dripicons-search"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container-fluid mt-3">
        <div class="row">
            @forelse($data as $row)
                <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                    <div class="card h-100" style="border-radius: 10px;">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start">
                                <div style="min-width: 0;">
                                    <div class="text-muted" style="font-size: 12px;">Event #{{ $row->id }}</div>
                                    <h5 class="mb-1" style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $row->name }}</h5>
                                    <div class="text-muted" style="font-size: 13px;">
                                        <i class="dripicons-clock"></i> {{ $row->event_at }}
                                    </div>
                                    @if($row->location)
                                        <div class="text-muted" style="font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <i class="dripicons-location"></i> {{ $row->location }}
                                        </div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-secondary">{{ optional($row->template)->name ?: 'No Template' }}</span>
                                </div>
                            </div>

                            @if($row->categories && count($row->categories))
                                <div class="mt-3">
                                    @foreach($row->categories as $cat)
                                        <span class="badge badge-info mr-1 mb-1">{{ $cat->name }}</span>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mt-auto pt-3 d-flex justify-content-between">
                                <a href="{{ route('online_invitation.events.edit', $row->id) }}" class="btn btn-sm btn-outline-primary"><i class="dripicons-document-edit"></i> Edit</a>
                                <form action="{{ route('online_invitation.events.destroy', $row->id) }}" method="POST" onsubmit="return confirmDelete()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info mb-0">No events found.</div>
                </div>
            @endforelse
        </div>

        <nav>
            <ul class="pagination">
                {{ $data->appends(request()->query())->links() }}
            </ul>
        </nav>
    </div>
</section>

<script type="text/javascript">
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    $("ul#online_invitation #online-invitation-event-menu").addClass("active");
</script>

@endsection
