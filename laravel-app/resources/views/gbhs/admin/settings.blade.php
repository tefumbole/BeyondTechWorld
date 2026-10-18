@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Association Settings</h3>
    @include('gbhs.admin.nav')
    <form class="card p-3" method="post" action="{{ route('gbhs.settings.save') }}" enctype="multipart/form-data">
        @csrf
        <input class="form-control" name="hero_title" value="{{ $setting->hero_title }}" placeholder="Hero title">
        <input class="form-control mt-2" name="hero_subtitle" value="{{ $setting->hero_subtitle }}">
        <input class="form-control mt-2" name="seo_title" value="{{ $setting->seo_title }}" placeholder="SEO title">
        <textarea class="form-control mt-2" name="seo_description">{{ $setting->seo_description }}</textarea>
        <input class="form-control mt-2" name="address" value="{{ $setting->address }}" placeholder="Address">
        <input class="form-control mt-2" name="phone" value="{{ $setting->phone }}" placeholder="President phone — shown as Contact us">
        <input class="form-control mt-2" name="email" value="{{ $setting->email }}" placeholder="Email">
        <input class="form-control mt-2" name="president_name" value="{{ $setting->president_name }}" placeholder="President name">
        <input class="form-control mt-2" name="president_title" value="{{ $setting->president_title }}" placeholder="Title">
        <label class="mt-2">Logo</label><input type="file" name="logo">
        <label class="mt-2">Hero image</label><input type="file" name="hero_image">
        <label class="mt-2">President signature image</label><input type="file" name="president_signature">
        @if($setting->president_signature)<p>A signature file is already stored.</p>@endif
        <h5 class="mt-3">Letter organization</h5>
        @if($organization)
            <p>{{ $organization->name }}</p>
            <input class="form-control" name="reference_prefix" value="{{ $organization->reference_prefix }}" placeholder="Reference prefix">
            <label class="mt-2">Letterhead logo</label><input type="file" name="org_logo">
            <label class="mt-2">Header</label><input type="file" name="org_header">
            <label class="mt-2">Footer</label><input type="file" name="org_footer">
        @else
            <p>The letters organization table is not installed yet. GBHS letters will use the GBHS/L- prefix once that migration has run.</p>
        @endif
        <label class="mt-2"><input type="checkbox" name="show_provisional" value="1" {{ $setting->show_provisional ? 'checked' : '' }}> Show provisional totals on the public site</label>
        <button class="btn btn-primary mt-2" type="submit">Save</button>
    </form>
    <form class="card p-3 mt-3" method="post" action="{{ route('gbhs.bylaws.store') }}" enctype="multipart/form-data">
        @csrf
        <h5>Bylaws PDF</h5>
        <input class="form-control" name="version" placeholder="Version" required>
        <input class="form-control mt-2" type="date" name="effective_on">
        <input class="mt-2" type="file" name="file" accept="application/pdf" required>
        <select class="form-control mt-2" name="status"><option value="draft">Draft</option><option value="published">Published</option></select>
        <button class="btn btn-primary btn-sm mt-2" type="submit">Store PDF</button>
    </form>
    @foreach($bylaws as $bylaw)
        <div class="mt-1">{{ $bylaw->version }} · {{ $bylaw->status }} · {{ $bylaw->effective_on }}</div>
    @endforeach
</div></section>
@endsection
