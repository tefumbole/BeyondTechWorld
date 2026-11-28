<div class="mb-3">
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.twilio_log') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('whatsapp.twilio_log') }}">Twilio log</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.chats') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('whatsapp.chats') }}">Conversations</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.leads*') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('whatsapp.leads') }}">Leads</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.groups*') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('whatsapp.groups') }}">Groups</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.attendance') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('whatsapp.attendance') }}">Attendance</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.documents') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('whatsapp.documents') }}">Documents</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.assistant*') ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.assistant') }}">AI Assistant</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.brief') ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.brief') }}">AI brief</a>
    <a class="btn btn-sm {{ request()->routeIs('whatsapp.people') ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.people') }}">People I know</a>
    @if(\App\Http\Controllers\Mai\MaiDashboardController::allows(Auth::user()))
        <a class="btn btn-sm {{ request()->routeIs('whatsapp.mai.dashboard') ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('whatsapp.mai.dashboard') }}">Whats MAI</a>
    @endif
</div>
