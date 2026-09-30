@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        <h1 class="wa-title">WhatsApp Groups</h1>
        <p class="wa-sub">Discovered groups stay off until you enable one. Download contacts for every group this WhatsApp account belongs to, including NBC Praise Team.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
        <p class="mb-3">
            <a class="btn btn-primary" href="{{ route('whatsapp.groups.export') }}">Download all group contacts (CSV)</a>
        </p>
        <div class="wa-card table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Group</th><th>Mode</th><th></th></tr>
                </thead>
                <tbody>
                @forelse($groups as $group)
                    <tr>
                        <td>{{ $group->name ?: $group->group_jid }}</td>
                        <td>{{ $group->enabled ? $group->mode : 'OFF' }}</td>
                        <td>
                            <form method="post" action="{{ route('whatsapp.groups.mode', $group->id) }}" class="form-inline">
                                @csrf
                                <select name="mode" class="form-control form-control-sm mr-1">
                                    @foreach(['OFF' => 'Mute', 'MONITOR' => 'Monitor only', 'MENTION_ONLY' => 'Mention only', 'ACTIVE' => 'Activate AI'] as $value => $label)
                                        <option value="{{ $value }}" {{ $group->mode === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-primary" type="submit">Save</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3">No groups discovered yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
