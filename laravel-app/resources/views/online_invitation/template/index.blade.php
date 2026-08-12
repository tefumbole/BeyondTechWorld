@extends('layout.main')
@section('content')
@php $oiTab = 'templates'; @endphp
@include('online_invitation.partials.tabs')

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
            <a href="{{ route('online_invitation.templates.create') }}" class="btn btn-info mb-2"><i class="dripicons-plus"></i> Add Template</a>

            <form method="GET" action="{{ route('online_invitation.templates.index') }}" class="form-inline mb-2">
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search templates..." value="{{ request('q') }}">
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
                @php
                    $bg = trim((string) $row->background);
                    $bgRef = null;
                    if ($bg !== '') {
                        $bgRef = $bg;
                        if (preg_match('/url\\(([^)]+)\\)/i', $bg, $m)) {
                            $bgRef = trim($m[1], " \t\n\r'\"");
                        }
                    }
                    $isImage = $bgRef && (preg_match('#^https?://#i', $bgRef) || preg_match('#^/(public/)?images/#i', $bgRef));
                    $isColor = $bg !== '' && preg_match('/^(#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})|rgba?\\(|hsla?\\(|transparent$)/i', $bg);
                    $imgSrc = $isImage
                        ? (preg_match('#^https?://#i', $bgRef)
                            ? \App\Support\OnlineInvitationUrl::ensurePublicInAppUrl($bgRef)
                            : \App\Support\OnlineInvitationUrl::ensurePublicInAppUrl(asset(ltrim($bgRef, '/'))))
                        : null;
                @endphp

                <div class="col-sm-6 col-md-4 col-lg-3 mb-3">
                    <div class="card h-100" style="border-radius: 10px; overflow: hidden;">
                        <div style="height: 140px; background: #f2f4f8;">
                            @if($imgSrc)
                                <img src="{{ $imgSrc }}" alt="Template background" style="width: 100%; height: 140px; object-fit: cover;">
                            @elseif($isColor)
                                <div style="width: 100%; height: 140px; background: {{ $bg }};"></div>
                            @else
                                <div class="d-flex align-items-center justify-content-center" style="height: 140px; color: #9aa3ad;">
                                    <i class="dripicons-photo" style="font-size: 36px;"></i>
                                </div>
                            @endif
                        </div>

                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-1" style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $row->name }}</h5>
                            <div class="text-muted" style="font-size: 12px;">#{{ $row->id }}</div>

                            <div class="mt-auto pt-3 d-flex justify-content-between">
                                <a href="{{ route('online_invitation.templates.edit', $row->id) }}" class="btn btn-sm btn-outline-primary"><i class="dripicons-document-edit"></i> Edit</a>
                                <form action="{{ route('online_invitation.templates.destroy', $row->id) }}" method="POST" onsubmit="return confirmDelete()">
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
                    <div class="alert alert-info mb-0">No templates found.</div>
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
    $("ul#online_invitation #online-invitation-template-menu").addClass("active");
</script>

@endsection
