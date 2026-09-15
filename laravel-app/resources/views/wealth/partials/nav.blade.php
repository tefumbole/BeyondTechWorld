@if(session('message'))
    <div class="alert alert-success">{{ session('message') }}</div>
@endif
@if(session('not_permitted'))
    <div class="alert alert-warning">{{ session('not_permitted') }}</div>
@endif
