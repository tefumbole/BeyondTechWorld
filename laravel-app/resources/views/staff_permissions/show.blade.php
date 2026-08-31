@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid" style="max-width:860px;margin:0 auto;">
        <div class="mb-3">
            <a href="{{ route($permTab) }}" class="text-muted">&larr; Back</a>
            <h3 style="color:#0b3f90;font-weight:800;">{{ $pageTitle }}</h3>
        </div>

        @include('staff_permissions.partials.tabs')

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('not_permitted'))
            <div class="alert alert-danger">{{ session('not_permitted') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="card card-body mb-3">
            <p class="mb-1"><strong>{{ $item->full_name }}</strong>
                <span class="badge badge-secondary ml-1">{{ $item->statusLabel() }}</span>
            </p>
            <p class="text-muted mb-2">{{ $item->email }} · {{ $item->phone }} · {{ $item->company_role }}</p>
            <p class="mb-1"><strong>Reference:</strong> <code>{{ $item->reference_number }}</code></p>
            <p class="mb-1"><strong>Period:</strong>
                {{ $item->from_at ? $item->from_at->format('D d M Y H:i') : '—' }}
                → {{ $item->to_at ? $item->to_at->format('D d M Y H:i') : '—' }}
            </p>
            <p class="mb-0"><strong>Reason:</strong> {{ $item->reason ?: '—' }}</p>
            @if($item->letter_id)
                <p class="mt-2 mb-0 small">Letter #{{ $item->letter_id }}
                    @if($item->letter)
                        — {{ $item->letter->reference }}
                    @endif
                </p>
            @endif
        </div>

        @if($item->isPending() && $canManage)
            <form method="POST" action="{{ route('permissions.update', $item->id) }}" class="card card-body">
                @csrf
                <div class="form-group">
                    <label for="instructions">Instructions <span class="text-muted">(printed on the letter)</span></label>
                    <textarea name="instructions" id="instructions" rows="4" class="form-control" placeholder="Conditions, return time, or notes for the applicant…">{{ old('instructions', $item->instructions) }}</textarea>
                </div>
                <div class="form-group">
                    <label for="letter_footer">Footer information <span class="text-danger">*</span> on approve</label>
                    <textarea name="letter_footer" id="letter_footer" rows="4" class="form-control" placeholder="Sr. Engr. Tefu R. Mbole&#10;CEO / Director&#10;Alpha Bridge Technologies.">{{ old('letter_footer', $item->letter_footer ?: $defaultFooter) }}</textarea>
                    <small class="form-text text-muted">Printed under the signature so the letter shows who approved or denied this request. Signatures are taken from your user account (Edit, Approve, Sign).</small>
                </div>
                <div class="form-group mb-3">
                    <label for="admin_note">Internal note <span class="text-muted">(not printed)</span></label>
                    <input type="text" name="admin_note" id="admin_note" class="form-control" value="{{ old('admin_note', $item->admin_note) }}" placeholder="Optional admin-only note">
                </div>
                <div class="d-flex flex-wrap" style="gap:10px;">
                    <button type="submit" name="status" value="approved" class="btn btn-success">Approve &amp; send letter</button>
                    <button type="submit" name="status" value="rejected" class="btn btn-outline-danger">Deny &amp; send letter</button>
                </div>
            </form>
        @else
            <div class="card card-body">
                @if($item->instructions)
                    <p><strong>Instructions:</strong><br>{!! nl2br(e($item->instructions)) !!}</p>
                @endif
                @if($item->letter_footer)
                    <p class="mb-0"><strong>Footer:</strong><br>{!! nl2br(e($item->letter_footer)) !!}</p>
                @endif
                @if($item->reviewed_by)
                    <p class="text-muted small mt-2 mb-0">Reviewed by {{ optional($item->reviewer)->name }}
                        {{ $item->reviewed_at ? ' on '.$item->reviewed_at->format('D d M Y H:i') : '' }}</p>
                @endif
            </div>
        @endif
    </div>
</section>
@endsection
