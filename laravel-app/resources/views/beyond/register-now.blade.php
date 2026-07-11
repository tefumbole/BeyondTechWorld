@extends('beyond.layout')

@section('title', 'Register Now')
@section('meta_description', 'Register for Beyond Enterprise training programs and courses.')

@section('content')

<div class="min-h-screen bg-gray-50 flex flex-col">
    <div class="relative h-[300px] w-full bg-brand-blue overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-40 mix-blend-overlay" style="background-image:url('https://images.unsplash.com/photo-1693045181224-9fc2f954f054');"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-brand-blue via-transparent to-transparent"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex flex-col justify-center text-white z-10">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-2">Register Now</h1>
            <p class="text-lg md:text-xl text-blue-100 max-w-2xl">
                Join Beyond Enterprise and elevate your skills with our premium courses. Select your courses below to get started.
            </p>
        </div>
    </div>

    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20 z-20 pb-16 w-full" x-data="registerForm()">
        <div class="bg-white rounded-xl shadow-xl border p-6 md:p-8">

            @if ($errors->any())
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('training.register') }}" @submit="onSubmit" class="flex flex-col lg:flex-row gap-8">
                @csrf
                <div class="flex-1 space-y-4">
                    <h2 class="text-xl font-bold text-brand-blue">Select Courses</h2>
                    <input type="search" x-model="search" placeholder="Search courses..."
                           class="w-full rounded-md border border-gray-200 px-3 py-2 focus:border-brand-blue outline-none">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[520px] overflow-y-auto pr-1">
                        @foreach ($courses as $course)
                            <label x-show="matches(@js($course->name))"
                                   class="flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all"
                                   :class="selected.includes(@js($course->id)) ? 'border-brand-gold bg-amber-50 shadow-md' : 'border-gray-100 hover:border-blue-200'">
                                <input type="checkbox" name="course_ids[]" class="mt-1" value="{{ $course->id }}"
                                       @change="toggle(@js($course->id))"
                                       :checked="selected.includes(@js($course->id))">
                                <div>
                                    <p class="font-bold text-brand-blue">{{ $course->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $course->duration }} · {{ $course->delivery_mode }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="w-full lg:w-[380px] space-y-4">
                    <h2 class="text-xl font-bold text-brand-blue">Your Details</h2>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Full Name *</label>
                        <input required name="client_name" value="{{ old('client_name') }}" type="text" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Email *</label>
                        <input required name="client_email" value="{{ old('client_email') }}" type="email" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Phone *</label>
                        <input required name="client_phone" value="{{ old('client_phone') }}" type="tel" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Company (optional)</label>
                        <input name="company_name" value="{{ old('company_name') }}" type="text" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <p class="text-sm text-gray-600" x-show="selected.length > 0">
                        <span class="font-semibold" x-text="selected.length"></span> course(s) selected
                    </p>
                    <p class="text-sm text-red-600" x-show="error" x-text="error"></p>
                    <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md flex items-center justify-center gap-2">
                        <i data-lucide="send" class="w-5 h-5"></i> Submit Registration
                    </button>
                    <p class="text-xs text-gray-400 text-center">You'll receive a WhatsApp confirmation with your reference number.</p>
                </div>
            </form>
        </div>
    </main>
</div>

@endsection

@push('scripts')
<script>
function registerForm() {
    const params = new URLSearchParams(window.location.search);
    const preselectName = params.get('module');
    const courseMap = @json($courses->pluck('name', 'id'));
    let initial = [];
    if (preselectName) {
        for (const [id, name] of Object.entries(courseMap)) {
            if (name === preselectName) initial.push(id);
        }
    }
    return {
        search: '',
        selected: initial,
        error: '',
        matches(name) {
            if (!this.search) return true;
            return name.toLowerCase().includes(this.search.toLowerCase());
        },
        toggle(id) {
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(x => x !== id);
            } else {
                this.selected.push(id);
            }
        },
        onSubmit(e) {
            this.error = '';
            if (this.selected.length === 0) {
                e.preventDefault();
                this.error = 'Please select at least one course.';
            }
        }
    };
}
</script>
@endpush
