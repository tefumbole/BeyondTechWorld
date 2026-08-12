@extends('layout.main')
@section('content')
<section class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Self-request invite links</h3>
        <a class="btn btn-info" href="{{ route('online_invitation.request_links.create') }}">+ Create link</a>
    </div>
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Event</th><th>Type</th><th>Link</th><th>Uses</th><th></th></tr></thead>
            <tbody>
            @forelse($links as $link)
                <tr>
                    <td>{{ optional($link->event)->name }}</td>
                    <td>{{ optional($link->category)->name }}</td>
                    <td><code>{{ route('online_invitation.request.show', $link->token) }}</code></td>
                    <td>{{ $link->use_count }}@if($link->max_uses)/{{ $link->max_uses }}@endif</td>
                    <td>
                        <form method="POST" action="{{ route('online_invitation.request_links.destroy', $link->id) }}" onsubmit="return confirm('Deactivate this link?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger">Deactivate</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No request links yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $links->links() }}
</section>
<script type="text/javascript">
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    $("ul#online_invitation #online-invitation-request-links-menu").addClass("active");
</script>
@endsection
