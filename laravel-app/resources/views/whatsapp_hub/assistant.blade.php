@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <h1 class="wa-title">Beyond Assistant</h1>
        <p class="wa-sub">Controlled ERP intelligence. API keys are never displayed.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        <div class="mb-3">
            <a class="btn btn-sm {{ $tab === 'status' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant', ['tab'=>'status']) }}">Status</a>
            <a class="btn btn-sm {{ $tab === 'knowledge' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant', ['tab'=>'knowledge']) }}">Knowledge</a>
            <a class="btn btn-sm {{ $tab === 'intents' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant', ['tab'=>'intents']) }}">Intents</a>
            <a class="btn btn-sm {{ $tab === 'tools' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant', ['tab'=>'tools']) }}">Tools</a>
            <a class="btn btn-sm {{ $tab === 'activity' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant', ['tab'=>'activity']) }}">Activity</a>
            <a class="btn btn-sm {{ $tab === 'failures' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant', ['tab'=>'failures']) }}">Failures</a>
        </div>

        @if($tab === 'status')
            <div class="wa-card">
                <p>Assistant: <span class="wa-badge {{ $enabled ? 'wa-badge-ok' : 'wa-badge-bad' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</span></p>
                <p>Provider: {{ config('assistant.provider') }} · {{ $configured ? 'Configured' : 'Missing key' }}</p>
                <p>Model (config name only): {{ config('assistant.model') }}</p>
                <p>Usage {{ $from->toDateString() }} – {{ $to->toDateString() }}: {{ $usage['requests'] }} requests · {{ $usage['input_tokens'] }} in / {{ $usage['output_tokens'] }} out tokens. Cost is not estimated.</p>
                <form method="post" action="{{ route('whatsapp.assistant.enabled') }}">
                    @csrf
                    <input type="hidden" name="assistant_enabled" value="{{ $enabled ? 0 : 1 }}">
                    <button class="btn {{ $enabled ? 'btn-danger' : 'btn-success' }}" type="submit">{{ $enabled ? 'Disable Beyond Assistant' : 'Enable Beyond Assistant' }}</button>
                </form>
            </div>
        @elseif($tab === 'knowledge')
            <div class="wa-card">
                <form method="post" action="{{ route('whatsapp.assistant.knowledge.store') }}" class="mb-4">
                    @csrf
                    <input type="text" name="title" class="form-control mb-2" placeholder="Title" required>
                    <input type="text" name="category" class="form-control mb-2" placeholder="Category" value="general">
                    <textarea name="content" class="form-control mb-2" rows="3" required placeholder="Approved content"></textarea>
                    <label><input type="checkbox" name="enabled" value="1" checked> Enabled</label>
                    <button class="btn btn-primary btn-sm ml-2" type="submit">Add</button>
                </form>
                @foreach($knowledge as $row)
                    <form method="post" action="{{ route('whatsapp.assistant.knowledge.update', $row->id) }}" class="mb-3">
                        @csrf
                        <strong>{{ $row->title }}</strong> <span class="small text-muted">{{ $row->category }} · updated {{ $row->updated_at }}</span>
                        <textarea name="content" class="form-control mb-1" rows="3">{{ $row->content }}</textarea>
                        <input type="hidden" name="title" value="{{ $row->title }}">
                        <input type="hidden" name="category" value="{{ $row->category }}">
                        <label><input type="checkbox" name="enabled" value="1" {{ $row->enabled ? 'checked' : '' }}> Enabled</label>
                        <button class="btn btn-sm btn-secondary" type="submit">Save</button>
                    </form>
                @endforeach
            </div>
        @elseif($tab === 'intents')
            <div class="wa-card">
                @foreach($intents as $name => $meta)
                    <div class="mb-2"><strong>{{ $name }}</strong> · {{ $meta['sensitivity'] }} · ERP {{ $meta['requires_erp'] ? 'yes' : 'no' }}</div>
                @endforeach
            </div>
        @elseif($tab === 'tools')
            <div class="wa-card">
                @foreach($tools as $name => $meta)
                    <div class="mb-2"><strong>{{ $name }}</strong> — {{ $meta['description'] }} ({{ $meta['sensitivity'] }}{{ !empty($meta['write']) ? ', write' : ', read' }})</div>
                @endforeach
            </div>
        @elseif($tab === 'activity')
            <div class="wa-card table-responsive">
                <table class="table">
                    <thead><tr><th>When</th><th>Intent</th><th>Action</th><th>Status</th><th>Sent</th><th>ms</th></tr></thead>
                    <tbody>
                    @forelse($activities as $a)
                        <tr>
                            <td>{{ $a->created_at }}</td>
                            <td>{{ $a->intent }} {{ $a->confidence }}</td>
                            <td>{{ $a->action }}</td>
                            <td>{{ $a->status }}</td>
                            <td>{{ $a->sent ? 'yes' : 'no' }}</td>
                            <td>{{ $a->duration_ms }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No activity yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                {{ $activities->links() }}
            </div>
        @else
            <div class="wa-card">
                @forelse($failures as $fail)
                    <div class="mb-2">#{{ $fail->id }} {{ $fail->intent }} — {{ $fail->error }}</div>
                @empty
                    <p class="text-muted mb-0">No failures recorded.</p>
                @endforelse
            </div>
        @endif
    </div>
</section>
@endsection
