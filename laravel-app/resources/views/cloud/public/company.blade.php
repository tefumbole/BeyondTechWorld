<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->system_name ?: $tenant->name }}</title>
    <style>
        body { font-family: Georgia, serif; background: #f4f7fb; margin: 0; }
        main { max-width: 720px; margin: 32px auto; background: #fff; padding: 24px; border-radius: 12px; }
        img { max-width: 180px; height: auto; }
    </style>
</head>
<body>
<main>
    @if($logoUrl)<img src="{{ $logoUrl }}" alt="{{ $tenant->name }} logo">@endif
    <h1>{{ $tenant->system_name ?: $tenant->name }}</h1>
    @if($tenant->legal_name && $tenant->legal_name !== $tenant->name)
        <p>{{ $tenant->legal_name }}</p>
    @endif
    @if($summary)<p>{{ $summary }}</p>@endif
    @if($services)<p>{{ $services }}</p>@endif
    <p>{{ $tenant->phone }}</p>
    <p>{{ $tenant->email }}</p>
    <p>{{ $tenant->address }} {{ $tenant->city }} {{ $tenant->country }}</p>
</main>
</body>
</html>
