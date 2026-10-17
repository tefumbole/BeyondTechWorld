<p>
    <a href="{{ route('gbhs.dashboard') }}">Dashboard</a> ·
    <a href="{{ route('gbhs.website') }}">Website Manager</a> ·
    <a href="{{ route('gbhs.applications') }}">Membership Applications</a> ·
    <a href="{{ route('gbhs.members') }}">Alumni Members</a> ·
    <a href="{{ route('gbhs.batches') }}">Batches & Representatives</a> ·
    <a href="{{ route('gbhs.contributions') }}">Contributions & Donations</a> ·
    <a href="{{ route('gbhs.projects') }}">Projects & Fundraising</a> ·
    <a href="{{ route('gbhs.letters') }}">Letters & Documents</a> ·
    <a href="{{ route('gbhs.letters') }}">WhatsApp & Announcements</a> ·
    <a href="{{ route('gbhs.events') }}">Events & Golden Jubilee</a> ·
    <a href="{{ route('gbhs.reports') }}">Reports & Analytics</a> ·
    <a href="{{ route('gbhs.settings') }}">Association Settings</a>
</p>
@if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
@if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
