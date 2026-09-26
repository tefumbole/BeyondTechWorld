@extends('beyond.layout')

@section('title', 'Shareholders Agreement')
@section('meta_description', 'Read and accept the Shareholder Agreement to invest in Beyond Enterprise.')

@section('content')
<div class="bg-brand-blue text-white flex flex-col" style="min-height: calc(100vh - 5rem);">

    <div class="bg-brand-dark/80 border-b border-white/10 py-5 sm:py-6 sticky top-20 z-30">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h1 class="text-xl md:text-2xl font-bold text-white flex items-center justify-center gap-2">
                <i data-lucide="shield-check" class="w-6 h-6 text-brand-gold"></i>
                Shareholder Agreement
            </h1>
            <p class="text-blue-100 mt-1 text-sm">Please read the following terms carefully before proceeding.</p>
        </div>
    </div>

    @if (session('warning'))
        <div class="max-w-4xl mx-auto px-4 pt-4 w-full">
            <div class="bg-brand-gold/15 border border-brand-gold/40 text-brand-gold rounded-lg px-4 py-3 text-sm">{{ session('warning') }}</div>
        </div>
    @endif

    <div class="flex-1 max-w-4xl mx-auto px-4 py-6 w-full">
        <div class="bg-brand-dark rounded-2xl p-6 md:p-10 border border-white/10 shadow-xl overflow-y-auto max-h-[65vh]">

            <div class="mb-6 p-5 bg-white/5 rounded-lg border-l-4 border-brand-gold">
                <h2 class="text-lg font-bold text-white mb-2">Terms &amp; Conditions of Investment</h2>
                <p class="text-blue-100 text-sm leading-relaxed">
                    This document serves as a binding understanding between Beyond Enterprise (the "Company") and you (the "Investor").
                    By clicking "I Agree" below, you acknowledge that you have read, understood, and accepted these terms.
                </p>
            </div>

            @php
                $sections = [
                    ['n' => '1', 'icon' => 'info', 'title' => 'About the Company', 'body' => '<p><strong>Beyond Enterprise</strong> is a private limited company registered in Rwanda. We specialize in IT consultancy, networking, security systems, and AV engineering.</p>'],
                    ['n' => '2', 'icon' => 'dollar-sign', 'title' => 'Share Price', 'body' => '<p>The value of one (1) share is currently set at <strong class="text-brand-gold">'.$priceLabel.'</strong>. This price is subject to change based on future valuations and board approval.</p>'],
                    ['n' => '3', 'icon' => 'pie-chart', 'title' => 'Share Issuance', 'body' => '<ul class="list-disc pl-5 space-y-2 marker:text-brand-gold"><li>Shares will be officially issued and allocated to the Investor after a vesting period of <strong class="text-white">24 months (2 years)</strong> from the date of investment receipt.</li><li>During this 24-month period, your investment is treated as <em>Convertible Equity</em>—securing your future ownership stake.</li></ul>'],
                    ['n' => '4', 'icon' => 'users', 'title' => 'Share Ownership', 'body' => '<p>Investors who purchase shares become partial owners of the company. Ownership percentage is calculated based on the number of shares held relative to the total authorized shares of the company.</p>'],
                    ['n' => '5', 'icon' => 'scale', 'title' => 'Share Value', 'body' => '<p>The value of shares can fluctuate. While we aim for growth, the value may go up or down based on market conditions and company performance.</p>'],
                    ['n' => '6', 'icon' => 'dollar-sign', 'title' => 'Dividends (Profit Sharing)', 'body' => '<ul class="list-disc pl-5 space-y-2 marker:text-brand-gold"><li>Dividends are payments made from company profits to shareholders.</li><li>Dividends are <strong>not guaranteed</strong>. They are declared only when the company is profitable and the Board of Directors recommends a distribution.</li><li>Reinvestment for growth may sometimes take priority over immediate dividend payouts.</li></ul>'],
                    ['n' => '7', 'icon' => 'users', 'title' => 'Management & Voting', 'body' => '<p>Day-to-day operations are managed by the Board of Directors and Executive Team. Shareholders execute their power by voting on critical matters such as:</p><ul class="list-disc pl-5 mt-2 space-y-1 marker:text-brand-gold text-sm"><li>Election of Directors</li><li>Approval of financial statements</li><li>Mergers, acquisitions, or sale of assets</li><li>Changes to the company constitution</li></ul>'],
                    ['n' => '8', 'icon' => 'refresh-cw', 'title' => 'Share Transfer & Exit', 'body' => '<p>Shares are not freely tradable on a public stock exchange.</p><ul class="list-disc pl-5 mt-2 space-y-2 marker:text-brand-gold"><li><strong class="text-white">Right of First Refusal:</strong> If you wish to sell your shares, existing shareholders and the Company have the first right to buy them at fair market value.</li><li><strong class="text-white">Transfer Approval:</strong> Transfers to third parties require Board approval to ensure alignment with company values.</li></ul>'],
                ];
            @endphp

            @foreach ($sections as $section)
                <div class="bg-white/5 border border-white/10 rounded-xl p-5 mb-4">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-9 h-9 bg-brand-gold/15 rounded-lg flex items-center justify-center text-brand-gold font-bold border border-brand-gold/30">
                            {{ $section['n'] }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-base md:text-lg font-bold text-brand-gold mb-2 flex items-center gap-2">
                                <i data-lucide="{{ $section['icon'] }}" class="w-4 h-4 text-brand-gold/80"></i>
                                {{ $section['title'] }}
                            </h3>
                            <div class="text-blue-100 leading-relaxed text-sm space-y-2">
                                {!! $section['body'] !!}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <div class="mt-6 p-5 bg-brand-gold/10 rounded-lg border border-brand-gold/35">
                <h3 class="text-brand-gold font-bold mb-2 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i> Risk Disclosure
                </h3>
                <p class="text-blue-100 text-sm">
                    Investing in startups and growing companies involves risk, including potential loss of capital.
                    Past performance does not guarantee future results.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-brand-dark border-t border-white/10 py-5 sticky bottom-0 z-[55]">
        <div class="max-w-4xl mx-auto px-4 pr-24 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm text-blue-100 text-center sm:text-left">
                Do you accept the terms outlined in the Shareholders Agreement?
            </p>
            <div class="flex gap-3 w-full sm:w-auto">
                <button type="button" onclick="window.scrollTo({top:0,behavior:'smooth'})"
                        class="flex-1 sm:flex-none px-6 py-2.5 rounded-md border border-white/30 text-white hover:bg-white/10 font-medium">
                    I Disagree
                </button>
                <form method="POST" action="{{ route('shareholders.accept') }}" class="flex-1 sm:flex-none">
                    @csrf
                    <button type="submit"
                            class="w-full bg-brand-gold text-brand-blue hover:bg-[#b5952f] font-bold px-8 py-2.5 rounded-md flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> I Agree
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
