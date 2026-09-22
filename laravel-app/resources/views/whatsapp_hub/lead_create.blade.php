@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <p><a href="{{ route('whatsapp.leads') }}">&larr; All leads</a></p>
        <h1 class="wa-title">Create Lead</h1>
        <p class="wa-sub">Use this for a number that is already in the ERP. Auto-capture only runs for unknown numbers.</p>
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="wa-card" style="max-width:640px">
            <form method="post" action="{{ route('whatsapp.leads.store') }}">
                @csrf
                <label>Existing WhatsApp conversation</label>
                <select name="conversation_id" class="form-control mb-3">
                    <option value="">— or enter a phone below —</option>
                    @foreach($conversations as $c)
                        <option value="{{ $c->id }}" {{ (string) old('conversation_id') === (string) $c->id ? 'selected' : '' }}>
                            {{ optional($c->contact)->displayName() }} · {{ optional($c->contact)->display_phone }} · {{ \Illuminate\Support\Str::limit($c->last_message, 40) }}
                        </option>
                    @endforeach
                </select>
                <label>Phone (if you did not pick a conversation)</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control mb-3" placeholder="675321739 or +237 675 321 739">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control mb-3" placeholder="Optional">
                <label>Company</label>
                <input type="text" name="company" value="{{ old('company') }}" class="form-control mb-3">
                <label>Category</label>
                <select name="category" class="form-control mb-3">
                    @foreach(\App\WhatsApp\LeadCatalog::categories() as $k=>$label)
                        <option value="{{ $k }}" {{ old('category', 'GENERAL_ENQUIRY') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <label>Priority</label>
                <select name="priority" class="form-control mb-3">
                    @foreach(\App\WhatsApp\LeadCatalog::priorities() as $k=>$label)
                        <option value="{{ $k }}" {{ old('priority', 'NORMAL') === $k ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                <label>Enquiry / summary</label>
                <textarea name="summary" class="form-control mb-3" rows="3" placeholder="What they asked for">{{ old('summary') }}</textarea>
                <button class="btn btn-primary" type="submit">Create Lead</button>
            </form>
        </div>
    </div>
</section>
@endsection
