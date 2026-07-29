@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <h4 class="mb-3"><i class="dripicons-ticket"></i> Digital Invitations</h4>
        <div class="alert alert-warning">
            <strong>Data connection not ready.</strong>
            Digital Invitations uses the Beyond data database (guests / invitations / events).
            Set these in <code>laravel-app/.env</code> then clear config cache:
            <pre class="mb-0 mt-2" style="white-space:pre-wrap;">BEYOND_DATA_DB_HOST=…
BEYOND_DATA_DB_PORT=3306
BEYOND_DATA_DB_DATABASE=…
BEYOND_DATA_DB_USERNAME=…
BEYOND_DATA_DB_PASSWORD=…</pre>
            @if(!empty($error))
                <hr>
                <small class="text-danger">{{ $error }}</small>
            @endif
        </div>
    </div>
</section>
@endsection
