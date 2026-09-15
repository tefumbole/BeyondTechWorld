@php $wmTab = $wmTab ?? ''; @endphp
<nav class="wm-nav" aria-label="Wealth Manager">
    <a href="{{ route('wealth.overview') }}" class="{{ $wmTab==='overview' ? 'is-active' : '' }}">Overview</a>
    <a href="{{ route('wealth.health') }}" class="{{ $wmTab==='health' ? 'is-active' : '' }}">Financial Health</a>
    <a href="{{ route('wealth.income') }}" class="{{ $wmTab==='income' ? 'is-active' : '' }}">Income</a>
    <a href="{{ route('wealth.expenses') }}" class="{{ $wmTab==='expenses' ? 'is-active' : '' }}">Expenses</a>
    <a href="{{ route('wealth.programs') }}" class="{{ $wmTab==='programs' ? 'is-active' : '' }}">Programs</a>
    <a href="{{ route('wealth.allocations') }}" class="{{ $wmTab==='allocations' ? 'is-active' : '' }}">Allocations</a>
    <a href="{{ route('wealth.investments') }}" class="{{ $wmTab==='investments' ? 'is-active' : '' }}">Investments</a>
    <a href="{{ route('wealth.charity') }}" class="{{ $wmTab==='charity' ? 'is-active' : '' }}">Charity</a>
    <a href="{{ route('wealth.reports') }}" class="{{ $wmTab==='reports' ? 'is-active' : '' }}">Reports</a>
    <a href="{{ route('wealth.settings') }}" class="{{ $wmTab==='settings' ? 'is-active' : '' }}">Settings</a>
</nav>
@if(session('message'))
    <div class="alert alert-success">{{ session('message') }}</div>
@endif
@if(session('not_permitted'))
    <div class="alert alert-warning">{{ session('not_permitted') }}</div>
@endif
