@extends('cloud.portal.layout')
@section('content')
<div class="card">
    <h1>Company settings</h1>
    <p class="muted">This is your company's own business logic. It is stored for this company only and is not copied from BeyondTechWorld.</p>
    @if($heroUrl)
        <img class="hero" src="{{ $heroUrl }}" alt="Hero">
    @endif
    <form method="POST" action="{{ route('cloud.settings.save') }}" enctype="multipart/form-data">
        @csrf
        <label>System name</label>
        <input name="system_name" value="{{ old('system_name', $tenant->system_name) }}" required>
        <label>Company logo</label>
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
        <p class="muted">JPG, PNG, or WebP under 2 MB. Stored for this company only.</p>
        <label>Hero page image</label>
        <input type="file" name="hero" accept="image/jpeg,image/png,image/webp">
        <p class="muted">JPG, PNG, or WebP. At least 400 by 200 pixels. Shown on your company portal.</p>
        <label>About the business</label>
        <textarea name="business_summary" rows="4">{{ old('business_summary', $summary) }}</textarea>
        <label>Services you offer</label>
        <textarea name="services" rows="4">{{ old('services', $services) }}</textarea>
        <label>Business rules</label>
        <textarea name="business_rules" rows="6" placeholder="Hours, deposits, what you will and will not do, how staff should answer.">{{ old('business_rules', $rules) }}</textarea>
        <button type="submit">Save settings</button>
    </form>
</div>
@endsection
