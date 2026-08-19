@extends('beyond.layout')

@section('title', 'Upload Documents')
@section('meta_description', 'Upload missing application documents.')

@section('content')
<div class="min-h-screen bg-gray-50 py-10 px-4">
    <div class="max-w-xl mx-auto space-y-6">
        <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-brand-blue to-[#0a3578] text-white px-5 py-5">
                <p class="text-brand-gold text-xs font-bold uppercase tracking-wider m-0">Document update</p>
                <h1 class="text-xl font-extrabold m-0 mt-1">Upload missing files</h1>
                <p class="text-blue-100 text-sm m-0 mt-1">
                    {{ $application->full_name }} · {{ $application->reference_number }}
                </p>
            </div>

            <div class="p-5 space-y-4">
                @if(session('message'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('message') }}</div>
                @endif
                @if($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <ul class="list-disc pl-5 mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                    </div>
                @endif

                @if($complete)
                    <div class="text-center py-6">
                        <p class="text-lg font-bold text-brand-blue mb-2">All requested documents are on file</p>
                        <p class="text-sm text-gray-600 mb-0">Thank you. You can close this page.</p>
                    </div>
                @else
                    @if($application->documents_request_note)
                        <p class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2.5 mb-0">
                            <strong>Note from admin:</strong> {{ $application->documents_request_note }}
                        </p>
                    @endif
                    <p class="text-sm text-gray-600 mb-0">Please upload:</p>
                    <ul class="text-sm text-gray-800 list-disc pl-5 mb-0">
                        @foreach($missing as $key)
                            <li>{{ $labels[$key] ?? $key }}</li>
                        @endforeach
                    </ul>

                    <form method="POST" action="{{ route('apply.documents.store', $application->documents_update_token) }}" enctype="multipart/form-data" class="space-y-4 pt-2">
                        @csrf
                        @foreach($missing as $key)
                            <div>
                                <label class="text-sm font-semibold text-gray-700">{{ $labels[$key] ?? $key }}</label>
                                <input type="file" name="{{ $key }}" accept="{{ $key === 'selfie' ? 'image/*' : 'image/*,.pdf' }}"
                                       class="w-full mt-1 text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-brand-blue file:text-white file:font-semibold">
                            </div>
                        @endforeach
                        <button type="submit" class="w-full min-h-[2.75rem] rounded-md bg-brand-blue text-white font-bold">
                            Submit documents
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
