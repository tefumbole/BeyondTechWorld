@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-4" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">Reviews</h3>
                <p class="text-muted mb-0">Client reviews for Beyond Company Limited. A review below 5 stars is kept here and stays off the website.</p>
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

        <div class="card mb-4">
            <div class="card-header bg-white"><strong>Google sign-in</strong></div>
            <div class="card-body">
                <p class="mb-2">People confirm a review with their Google account. Create a Web client in Google Cloud, then paste the client ID here.</p>
                <ol class="pl-3 mb-3 text-muted">
                    <li>In Google Cloud, open APIs &amp; Services → Credentials → Create OAuth client ID → Web application.</li>
                    <li>Authorized JavaScript origin: <code>https://beyondtechworld.com</code></li>
                    <li>Paste the client ID below. It ends with <code>.apps.googleusercontent.com</code>.</li>
                </ol>
                <p class="mb-3">Status: <strong>{{ $googleReady ? 'Connected' : 'Not connected' }}</strong></p>
                <form method="POST" action="{{ route('admin.reviews.google') }}" class="form-inline" style="gap:8px;">
                    @csrf
                    <input type="text" name="google_client_id" class="form-control" style="min-width:320px;max-width:100%;" placeholder="000000000000-xxxx.apps.googleusercontent.com" autocomplete="off">
                    <button type="submit" class="btn btn-primary">Save Google client ID</button>
                    <label class="ml-2 mb-0"><input type="checkbox" name="clear_google" value="1"> Disconnect</label>
                </form>
                <p class="text-muted mt-2 mb-0">When a client pays an invoice or quotation, they receive a link to write a review.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Reviewer</th>
                                <th>Country</th>
                                <th>Stars</th>
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
                                                <div class="text-muted small">{{ $review->google_email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $review->countryLabel() }}</td>
                                    <td>{{ $review->rating }} / 5</td>
                                    <td style="max-width:280px;">{{ \Illuminate\Support\Str::limit($review->comment, 140) }}</td>
                                    <td>
                                        @if($review->signatureUrl())
                                            <img src="{{ $review->signatureUrl() }}" alt="" style="height:36px;">
                                        @endif
                                    </td>
                                    <td>
                                        @if($review->published)
                                            <span class="badge badge-success">Published</span>
                                        @elseif((int) $review->rating < 5)
                                            <span class="badge badge-secondary">Below 5 stars</span>
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
                                        @elseif((int) $review->rating === 5)
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
