@extends('beyond.layout')

@section('title', 'Shareholders Agreement')
@section('meta_description', 'Read and accept the Shareholder Agreement to invest in Beyond Enterprise.')

@section('content')
<div class="bg-brand-dark text-white flex flex-col" style="min-height: calc(100vh - 5rem);">

    <div class="border-b border-white/10 py-4 sm:py-5 sticky top-20 z-30 bg-brand-dark">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h1 class="text-xl md:text-2xl font-bold text-white m-0">Shareholder Agreement</h1>
            <p class="text-white/70 mt-1 text-sm m-0">Please read the terms carefully before proceeding.</p>
        </div>
    </div>

    @if (session('warning'))
        <div class="max-w-3xl mx-auto px-4 pt-4 w-full">
            <div class="bg-white/10 border border-white/20 text-white rounded-lg px-4 py-3 text-sm">{{ session('warning') }}</div>
        </div>
    @endif

    <div class="flex-1 max-w-3xl mx-auto px-4 py-5 w-full">
        <div class="bg-white text-slate-800 rounded-2xl p-5 md:p-8 shadow-xl overflow-y-auto max-h-[65vh]">

            <div class="mb-5 pb-4 border-b border-slate-200">
                <h2 class="text-lg font-bold text-brand-blue m-0 mb-2">Terms &amp; Conditions of Investment</h2>
                <p class="text-slate-600 text-sm leading-relaxed m-0">
                    This document serves as a binding understanding between Beyond Enterprise (the "Company") and you (the "Investor").
                    By clicking "I Agree" below, you acknowledge that you have read, understood, and accepted these terms.
                </p>
            </div>

            @php
                $sections = [
                    ['n' => '1', 'title' => 'About the Company', 'body' => '<p><strong>Beyond Enterprise</strong> is a private limited company registered in Rwanda. We specialize in IT consultancy, networking, security systems, and AV engineering.</p>'],
                    ['n' => '2', 'title' => 'Share Price', 'body' => '<p>The value of one (1) share is currently set at <strong>'.$priceLabel.'</strong>. This price is subject to change based on future valuations and board approval.</p>'],
                    ['n' => '3', 'title' => 'Share Issuance', 'body' => '<ul class="list-disc pl-5 space-y-2"><li>Shares will be officially issued and allocated to the Investor after a vesting period of <strong>24 months (2 years)</strong> from the date of investment receipt.</li><li>During this 24-month period, your investment is treated as <em>Convertible Equity</em>—securing your future ownership stake.</li></ul>'],
                    ['n' => '4', 'title' => 'Share Ownership', 'body' => '<p>Investors who purchase shares become partial owners of the company. Ownership percentage is calculated based on the number of shares held relative to the total authorized shares of the company.</p>'],
                    ['n' => '5', 'title' => 'Share Value', 'body' => '<p>The value of shares can fluctuate. While we aim for growth, the value may go up or down based on market conditions and company performance.</p>'],
                    ['n' => '6', 'title' => 'Dividends (Profit Sharing)', 'body' => '<ul class="list-disc pl-5 space-y-2"><li>Dividends are payments made from company profits to shareholders.</li><li>Dividends are <strong>not guaranteed</strong>. They are declared only when the company is profitable and the Board of Directors recommends a distribution.</li><li>Reinvestment for growth may sometimes take priority over immediate dividend payouts.</li></ul>'],
                    ['n' => '7', 'title' => 'Management & Voting', 'body' => '<p>Day-to-day operations are managed by the Board of Directors and Executive Team. Shareholders execute their power by voting on critical matters such as:</p><ul class="list-disc pl-5 mt-2 space-y-1 text-sm"><li>Election of Directors</li><li>Approval of financial statements</li><li>Mergers, acquisitions, or sale of assets</li><li>Changes to the company constitution</li></ul>'],
                    ['n' => '8', 'title' => 'Share Transfer & Exit', 'body' => '<p>Shares are not freely tradable on a public stock exchange.</p><ul class="list-disc pl-5 mt-2 space-y-2"><li><strong>Right of First Refusal:</strong> If you wish to sell your shares, existing shareholders and the Company have the first right to buy them at fair market value.</li><li><strong>Transfer Approval:</strong> Transfers to third parties require Board approval to ensure alignment with company values.</li></ul>'],
                ];
            @endphp

            <div class="space-y-5">
                @foreach ($sections as $section)
                    <div class="flex gap-3 sm:gap-4">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-brand-blue text-white text-sm font-bold flex items-center justify-center">
                            {{ $section['n'] }}
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <h3 class="text-base font-bold text-brand-blue m-0 mb-1.5">{{ $section['title'] }}</h3>
                            <div class="text-slate-600 leading-relaxed text-sm space-y-2">
                                {!! $section['body'] !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 pt-5 border-t border-slate-200">
                <h3 class="text-brand-blue font-bold mb-1.5 text-sm m-0">Risk disclosure</h3>
                <p class="text-slate-600 text-sm m-0">
                    Investing in startups and growing companies involves risk, including potential loss of capital.
                    Past performance does not guarantee future results.
                </p>
            </div>
        </div>
    </div>

    <div class="bg-brand-dark border-t border-white/10 py-4 sticky bottom-0 z-[55]">
        <div class="max-w-3xl mx-auto px-4 pr-24 flex flex-col sm:flex-row items-center justify-between gap-3">
            <p class="text-sm text-white/80 text-center sm:text-left m-0">
                Do you accept the Shareholders Agreement?
            </p>
            <div class="flex gap-2 w-full sm:w-auto">
                <button type="button" onclick="window.scrollTo({top:0,behavior:'smooth'})"
                        class="flex-1 sm:flex-none px-5 py-2.5 rounded-lg border border-white/25 text-white hover:bg-white/10 font-medium text-sm">
                    I Disagree
                </button>
                <form method="POST" action="{{ route('shareholders.accept') }}" class="flex-1 sm:flex-none">
                    @csrf
                    <button type="submit"
                            class="w-full bg-brand-gold text-brand-blue hover:bg-[#b5952f] font-bold px-7 py-2.5 rounded-lg text-sm flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> I Agree
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
