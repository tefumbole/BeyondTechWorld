@extends('beyond.layout')

@section('title', 'Contact Us')
@section('meta_description', 'Contact Beyond Enterprise for IT consultancy, networking, security, and audio-visual solutions in Kigali and beyond.')

@section('content')

<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <div class="text-center mb-16">
            <h1 class="text-4xl md:text-5xl font-extrabold text-brand-blue mb-4">Get in Touch</h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Have a question, need assistance, or want to explore partnership opportunities? We're here to help. Reach out to the Beyond Enterprise team today.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

            <div class="lg:col-span-4 space-y-6">
                <div class="bg-white rounded-xl border-t-4 border-t-brand-blue shadow-md hover:shadow-lg transition-all p-6">
                    <h3 class="text-xl font-bold text-brand-blue mb-4 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5"></i> Office Location
                    </h3>
                    <div class="space-y-3 text-gray-600">
                        <p class="font-semibold text-gray-800">Beyond Enterprise.</p>
                        <p>Norrsken House Kigali</p>
                        <p>Kigali, Rwanda</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl border-t-4 border-t-brand-gold shadow-md hover:shadow-lg transition-all p-6">
                    <h3 class="text-xl font-bold text-brand-blue mb-4 flex items-center gap-2">
                        <i data-lucide="user" class="w-5 h-5 text-brand-gold"></i> Contact Person
                    </h3>
                    <div class="space-y-4 text-gray-600">
                        <div>
                            <p class="font-bold text-gray-800">Nasrah Umwela</p>
                            <p class="text-sm text-gray-500">Lead Technical Director</p>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <div class="bg-blue-100 p-2 rounded-full text-brand-blue"><i data-lucide="phone" class="w-4 h-4"></i></div>
                            <div>
                                <p class="font-medium">+237 675 321 739</p>
                                <a href="https://wa.me/237675321739" target="_blank" rel="noopener" class="text-brand-gold hover:text-brand-blue text-xs font-semibold inline-flex items-center gap-1">
                                    <i data-lucide="message-circle" class="w-3 h-3"></i> Chat on WhatsApp
                                </a>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <div class="bg-yellow-100 p-2 rounded-full text-brand-gold"><i data-lucide="mail" class="w-4 h-4"></i></div>
                            <a href="mailto:info@beyondtechworld.com" class="font-medium hover:text-brand-blue">info@beyondtechworld.com</a>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <div class="bg-gray-200 p-2 rounded-full text-gray-700"><i data-lucide="globe" class="w-4 h-4"></i></div>
                            <a href="https://beyondtechworld.com" class="font-medium hover:text-brand-blue">www.beyondtechworld.com</a>
                        </div>
                    </div>
                </div>

                <div class="bg-brand-blue text-white shadow-md rounded-xl overflow-hidden relative p-6">
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
                    <div class="relative z-10 flex items-start gap-4">
                        <div class="bg-white/20 p-3 rounded-full shrink-0"><i data-lucide="clock" class="w-6 h-6"></i></div>
                        <div>
                            <h3 class="font-bold text-lg mb-2 text-brand-gold">Business Hours</h3>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between"><span class="text-blue-100">Mon - Fri:</span><span class="font-medium">9:00 AM - 6:00 PM</span></div>
                                <div class="flex justify-between"><span class="text-blue-100">Sat & Sun:</span><span class="font-medium opacity-80">Closed</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="shadow-xl border-0 h-full rounded-xl overflow-hidden bg-white">
                    <div class="bg-gradient-to-r from-brand-dark to-brand-blue p-6 text-white">
                        <h2 class="text-2xl font-bold flex items-center gap-2">
                            <i data-lucide="send" class="w-6 h-6 text-brand-gold"></i> Send us a Direct Message
                        </h2>
                        <p class="text-blue-100 mt-1">Fill out the form below and we'll instantly receive it via WhatsApp.</p>
                    </div>
                    <div class="p-8 md:p-10">
                        <form id="contact-form" class="space-y-6" onsubmit="return submitContact(event)">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="text-sm font-semibold text-gray-700">Full Name <span class="text-red-500">*</span></label>
                                    <input required name="name" type="text" placeholder="e.g. John Doe"
                                           class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 focus:bg-white focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20 outline-none">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-sm font-semibold text-gray-700">Email Address <span class="text-red-500">*</span></label>
                                    <input required name="email" type="email" placeholder="e.g. john@example.com"
                                           class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 focus:bg-white focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20 outline-none">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-gray-700">Subject <span class="text-red-500">*</span></label>
                                <input required name="subject" type="text" placeholder="What is this regarding?"
                                       class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 focus:bg-white focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20 outline-none">
                            </div>
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-gray-700">Your Message <span class="text-red-500">*</span></label>
                                <textarea required name="message" rows="6" placeholder="Please provide details about your inquiry..."
                                          class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 resize-none focus:bg-white focus:border-brand-blue focus:ring-2 focus:ring-brand-blue/20 outline-none"></textarea>
                            </div>
                            <div id="contact-success" class="hidden rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"></div>
                            <div class="pt-4 flex flex-col sm:flex-row gap-4">
                                <button type="submit"
                                        class="w-full sm:w-auto px-8 bg-brand-blue hover:bg-[#002a5a] text-white font-bold h-12 text-lg rounded-md shadow-md inline-flex items-center justify-center gap-2">
                                    <i data-lucide="send" class="w-5 h-5"></i> Send Message
                                </button>
                                <a href="https://wa.me/237675321739?text={{ urlencode('Hello Beyond Enterprise, I would like to inquire about...') }}"
                                   target="_blank" rel="noopener"
                                   class="w-full sm:w-auto px-8 border-2 border-[#25D366] text-[#25D366] hover:bg-[#25D366] hover:text-white font-bold h-12 text-lg rounded-md inline-flex items-center justify-center gap-2 transition-colors">
                                    <i data-lucide="message-circle" class="w-5 h-5"></i> Open WhatsApp
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
function submitContact(e) {
    e.preventDefault();
    const f = e.target;
    const name = f.name.value.trim();
    const email = f.email.value.trim();
    const subject = f.subject.value.trim();
    const message = f.message.value.trim();
    const text = `*New Contact Form Submission*\n\n*Name:* ${name}\n*Email:* ${email}\n*Subject:* ${subject}\n\n*Message:*\n${message}`;
    window.open('https://wa.me/237675321739?text=' + encodeURIComponent(text), '_blank');
    const el = document.getElementById('contact-success');
    el.textContent = 'Thank you! Your message was opened in WhatsApp. We will get back to you shortly.';
    el.classList.remove('hidden');
    f.reset();
    return false;
}
</script>
@endpush
