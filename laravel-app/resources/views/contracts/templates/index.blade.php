@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h1 class="ct-title">Contract Templates</h1>
                <p class="ct-subtitle">Manage reusable template definitions and published versions.</p>
            </div>
            <a href="{{ route('contracts.templates.create') }}" class="ct-btn"><i class="dripicons-plus"></i> New Template</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="ct-card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Version</th>
                            <th>Active</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($templates ?? [] as $tpl)
                            <tr>
                                <td><strong>{{ $tpl->name }}</strong></td>
                                <td><code>{{ $tpl->code }}</code></td>
                                <td>{{ optional($tpl->type)->name ?? optional($tpl->type)->code ?? '—' }}</td>
                                <td>{{ optional($tpl->currentVersion)->version_no ?? '—' }}</td>
                                <td>
                                    @if($tpl->active)
                                        <span class="ct-badge ct-badge-success">Active</span>
                                    @else
                                        <span class="ct-badge">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-right text-nowrap">
                                    <a href="{{ route('contracts.templates.edit', $tpl->id) }}" class="btn btn-sm btn-primary">Edit / Publish</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No templates yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
