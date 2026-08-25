@php $people = $supervisors ?? []; @endphp
<div class="ip-card">
    <h5 style="font-weight:700;color:#0b3f90;margin-bottom:.75rem;">Your supervisor{{ count($people) === 1 ? '' : 's' }}</h5>
    @forelse($people as $s)
        <div class="ip-supervisor">
            <div class="ip-supervisor-avatar">{{ strtoupper(substr($s['name'], 0, 1)) }}</div>
            <div>
                <strong>{{ $s['name'] }}</strong>
                @if(!empty($s['source']))
                    <span class="ip-badge blue">{{ $s['source'] }}</span>
                @endif
                <div class="ip-meta mt-1">
                    @if(!empty($s['phone']))
                        WhatsApp / phone:
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $s['phone']) }}">{{ $s['phone'] }}</a>
                    @else
                        No phone on file
                    @endif
                    @if(!empty($s['email']))
                        · Email: <a href="mailto:{{ $s['email'] }}">{{ $s['email'] }}</a>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <p class="mb-0 text-muted">No supervisor is assigned yet. An Internship Administrator will assign one after placement.</p>
    @endforelse
</div>
