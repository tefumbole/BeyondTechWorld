@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">Reviews</h3>
                <p class="text-muted mb-0">A review is shown on the website from 6 out of 10 upward. A payment link skips the phone code.</p>
            </div>
            <a href="{{ route('reviews.index') }}" target="_blank" class="btn btn-outline-primary btn-sm">View on website</a>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 pl-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Reviewer</th>
                                <th>Country</th>
                                <th>Score</th>
                                <th>Review</th>
                                <th>Signature</th>
                                <th>On the website</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($reviews as $review)
                                <tr>
                                    <td style="min-width:180px;">
                                        <div class="d-flex align-items-center" style="gap:8px;">
                                            @if($review->portraitUrl())
                                                <img src="{{ $review->portraitUrl() }}" alt="" width="40" height="40" style="width:40px;height:40px;object-fit:cover;border-radius:50%;">
                                            @endif
                                            <div>
                                                <div class="font-weight-bold">{{ $review->reviewer_name }}</div>
                                                <div class="text-muted small">{{ $review->phone }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $review->countryLabel() }}</td>
                                    <td>
                                        <div class="font-weight-bold">{{ (int) $review->rating }}/10</div>
                                        @if((int) $review->rating_value >= 1 && (int) $review->rating_value <= 5)
                                            <div style="color:#e47911;letter-spacing:1px;">{{ str_repeat('★', (int) $review->rating_value) }}{{ str_repeat('☆', 5 - (int) $review->rating_value) }}</div>
                                        @endif
                                        @if($review->sectorLabel())
                                            <div class="small text-muted">{{ $review->sectorLabel() }}</div>
                                        @endif
                                    </td>
                                    <td style="max-width:280px;">{{ \Illuminate\Support\Str::limit($review->comment, 140) }}</td>
                                    <td>
                                        @if($review->signatureUrl())
                                            <img src="{{ $review->signatureUrl() }}" alt="" style="height:36px;">
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->published)
                                            <span class="badge badge-success">Published</span>
                                        @elseif(! $review->meetsPublishRule())
                                            <span class="badge badge-secondary">Below 6/10</span>
                                        @else
                                            <span class="badge badge-warning">Hidden</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->published)
                                            <form method="POST" action="{{ route('admin.reviews.visibility', $review->id) }}">
                                                @csrf
                                                <input type="hidden" name="published" value="0">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">Hide</button>
                                            </form>
                                        @elseif($review->meetsPublishRule())
                                            <form method="POST" action="{{ route('admin.reviews.visibility', $review->id) }}">
                                                @csrf
                                                <input type="hidden" name="published" value="1">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Show</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No reviews yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($reviews->hasPages())
                <div class="card-footer">{{ $reviews->links() }}</div>
            @endif
        </div>
    </div>
</section>
@endsection
