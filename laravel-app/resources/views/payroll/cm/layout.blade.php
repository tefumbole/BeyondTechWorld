@extends('layout.main')
@section('content')
<section class="container-fluid pt-3">
    @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
    @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif
    <h3 style="color:#0b3f90;font-weight:800;">Payroll</h3>
    <p class="text-muted">Take-home salary in, Cameroon statutory breakdown out.</p>
    @include('payroll.cm.nav')
    @yield('payroll')
</section>
@endsection
