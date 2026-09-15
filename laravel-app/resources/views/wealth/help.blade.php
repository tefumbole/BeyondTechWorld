@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<style>
    .wm-help-hero { background:linear-gradient(135deg,#0b3f90,#1d4ed8); color:#fff; border-radius:14px; padding:22px 24px; margin-bottom:16px; }
    .wm-help-hero h1 { color:#fff; margin:0 0 6px; font-size:1.55rem; font-weight:800; }
    .wm-help-hero p { margin:0; opacity:.95; max-width:720px; }
    .wm-help-toc { display:flex; flex-wrap:wrap; gap:8px; margin:0 0 18px; }
    .wm-help-toc a { background:#fff; border:1px solid #dbe4f0; color:#0b3f90; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:700; text-decoration:none; }
    .wm-help-toc a:hover { background:#0b3f90; color:#fff; text-decoration:none; }
    .wm-help-section { scroll-margin-top:90px; }
    .wm-help-section h2 { color:#0b3f90; font-weight:800; font-size:1.2rem; margin:0 0 8px; }
    .wm-help-section h3 { color:#1e3a8a; font-weight:700; font-size:1rem; margin:16px 0 8px; }
    .wm-help-figure { margin:12px 0 16px; }
    .wm-help-figure img { width:100%; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 8px 24px rgba(15,23,42,.08); display:block; background:#fff; }
    .wm-help-figure figcaption { font-size:12px; color:#64748b; margin-top:6px; }
    .wm-help-steps { counter-reset:step; list-style:none; padding:0; margin:0 0 12px; }
    .wm-help-steps li { position:relative; padding:10px 12px 10px 48px; margin-bottom:8px; background:#f8fafc; border:1px solid #eef2f7; border-radius:10px; }
    .wm-help-steps li:before { counter-increment:step; content:counter(step); position:absolute; left:12px; top:10px; width:26px; height:26px; border-radius:50%; background:#0b3f90; color:#fff; font-weight:800; font-size:13px; display:flex; align-items:center; justify-content:center; }
    .wm-help-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin:12px 0; }
    .wm-help-pill { border-radius:12px; padding:14px; color:#fff; }
    .wm-help-pill strong { display:block; font-size:1.4rem; }
    .wm-help-pill span { font-size:13px; opacity:.95; }
    .wm-help-table { width:100%; font-size:13px; }
    .wm-help-table th { background:#f1f5f9; color:#334155; }
    .wm-help-table th, .wm-help-table td { padding:8px 10px; border-bottom:1px solid #eef2f7; vertical-align:top; }
    .wm-help-callout { border-left:4px solid #0b3f90; background:#eef4ff; padding:10px 14px; border-radius:0 10px 10px 0; margin:12px 0; }
    .wm-help-warn { border-left-color:#c2410c; background:#fff7ed; }
    @media print {
        .beyond-module-tabs, .wm-help-toc { display:none !important; }
        .wm-help-hero { background:#0b3f90 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    }
</style>
<section class="forms">
    <div class="container-fluid wm-shell">
        @include('wealth.partials.nav')

        <div class="wm-help-hero">
            <h1>Wealth Manager Help</h1>
            <p>A pictured guide to the 70 / 20 / 10 envelopes: what you can spend this month, what is still due for investment, and what is still due for giving.</p>
        </div>

        <nav class="wm-help-toc" aria-label="Help topics">
            <a href="#start">Getting started</a>
            <a href="#rule">70 / 20 / 10</a>
            <a href="#month">Month view</a>
            <a href="#overview">Overview</a>
            <a href="#health">Financial Health</a>
            <a href="#income">Income</a>
            <a href="#expenses">Expenses</a>
            <a href="#categories">Expense categories</a>
            <a href="#programs">Programs</a>
            <a href="#allocations">Allocations</a>
            <a href="#investments">Investments</a>
            <a href="#charity">Charity</a>
            <a href="#reports">Reports</a>
            <a href="#settings">Settings</a>
            <a href="#faq">FAQ</a>
        </nav>

        <div class="wm-card wm-help-section" id="start">
            <h2>1. Getting started</h2>
            <p>Open <strong>Wealth Manager</strong> from the left menu. The top tabs follow that same list. Work through these five steps once, then use the module every month.</p>
            <figure class="wm-help-figure">
                <img src="{{ asset('wealth/guide/wm-help-start.png') }}" alt="Getting started: five numbered steps from categories to Financial Health">
                <figcaption>Figure 1 — Set up categories and programs, then record income and expenses, then read Overview and Financial Health.</figcaption>
            </figure>
            <ol class="wm-help-steps">
                <li><strong>Create expense categories</strong> on the <a href="{{ route('expense_categories.index') }}">Expense Category</a> tab. Codes fill themselves (001, 002, 003…).</li>
                <li><strong>Create programs</strong> on <a href="{{ route('wealth.programs') }}">Programs</a> for projects that have their own income and spend.</li>
                <li><strong>Confirm income</strong> on <a href="{{ route('wealth.income') }}">Income</a>. Paid sales sync automatically; add other money as manual income.</li>
                <li><strong>Record expenses</strong> and always pick a 70 / 20 / 10 envelope (Operations, Investment, or Charity).</li>
                <li><strong>Read the month</strong> on <a href="{{ route('wealth.overview') }}">Overview</a> and <a href="{{ route('wealth.health') }}">Financial Health</a>.</li>
            </ol>
        </div>

        <div class="wm-card wm-help-section" id="rule">
            <h2>2. The 70 / 20 / 10 rule</h2>
            <p>Every month’s posted income is split into three envelopes. These are <strong>targets</strong>, not extra journal entries in accounting.</p>
            <figure class="wm-help-figure">
                <img src="{{ asset('wealth/guide/wm-help-rule.png') }}" alt="70 percent operations, 20 percent investment, 10 percent charity">
                <figcaption>Figure 2 — Income is split: 70% you may spend on operations, 20% should be invested, 10% should be given.</figcaption>
            </figure>
            <div class="wm-help-grid">
                <div class="wm-help-pill" style="background:#1d4ed8;"><strong>70%</strong><span>Operations — <em>You can spend</em> is what is left of this envelope.</span></div>
                <div class="wm-help-pill" style="background:#b45309;"><strong>20%</strong><span>Investment — <em>Due for investment</em> is what you still need to put aside.</span></div>
                <div class="wm-help-pill" style="background:#166534;"><strong>10%</strong><span>Charity — <em>Due for giving</em> is what you still need to give.</span></div>
            </div>
            <div class="wm-help-callout">Example: income this month is 1,000,000. You can spend up to 700,000 on operations. 200,000 is due for investment. 100,000 is due for giving.</div>
        </div>

        <div class="wm-card wm-help-section" id="month">
            <h2>3. Always start from the month</h2>
            <p>Wealth Manager opens on the <strong>current month</strong>. Change <em>Month</em> and <em>Year</em>, then click <strong>Show month</strong>. Health, income, spendable cash, investment due, and giving due all follow that month.</p>
            <figure class="wm-help-figure">
                <img src="{{ asset('wealth/guide/wm-help-month.png') }}" alt="Select a month and year, then show that month’s figures">
                <figcaption>Figure 3 — Pick January to see January only. The current month is the default.</figcaption>
            </figure>
            <p>You can also narrow by company, user, staff, or program. Leave those on <em>All</em> for the full picture.</p>
        </div>

        <div class="wm-card wm-help-section" id="overview">
            <h2>4. Overview</h2>
            <p>Overview is the home screen. It answers four questions for the selected month:</p>
            <ul>
                <li>How much income came in?</li>
                <li>How much can I still spend (70% remaining)?</li>
                <li>How much is still due for investment (20% remaining)?</li>
                <li>How much is still due for giving (10% remaining)?</li>
            </ul>
            <figure class="wm-help-figure">
                <img src="{{ asset('wealth/guide/wm-help-overview.png') }}" alt="Overview cards for income, you can spend, investment due, and giving due">
                <figcaption>Figure 4 — The four headline cards plus charts for income vs expenses and the 70 / 20 / 10 split.</figcaption>
            </figure>
            <p>The score strip at the top is a short Financial Health summary. Use <strong>View Full Financial Health</strong> for graphs and recommendations. Unclassified expenses show a yellow warning — assign them before you trust the envelopes.</p>
        </div>

        <div class="wm-card wm-help-section" id="health">
            <h2>5. Financial Health</h2>
            <p>This is an <strong>internal discipline score from 0 to 100</strong> for the selected month. It is not a bank or credit-bureau score.</p>
            <figure class="wm-help-figure">
                <img src="{{ asset('wealth/guide/wm-help-health.png') }}" alt="Financial Health gauge, 12-month history, and 70 20 10 bars">
                <figcaption>Figure 5 — Score this month, history for the last 12 months, and how well each envelope is being kept.</figcaption>
            </figure>
            <table class="wm-help-table">
                <thead><tr><th>Band</th><th>Typical range</th><th>Meaning</th></tr></thead>
                <tbody>
                    <tr><td>Excellent / Very good</td><td>75–100</td><td>Spending, savings, investment and giving are on track.</td></tr>
                    <tr><td>Fair</td><td>60–74</td><td>Usable month, but one envelope is slipping.</td></tr>
                    <tr><td>Needs attention</td><td>40–59</td><td>Overspending operations or under-funding investment/giving.</td></tr>
                    <tr><td>Critical</td><td>0–39</td><td>Fix unclassified expenses and overspent operations first.</td></tr>
                </tbody>
            </table>
            <p class="mb-0">The score mixes income vs expenses, leftover cash, operations discipline, investment fulfilment, charity fulfilment, savings, and program budgets. Weights can be changed in Settings.</p>
        </div>

        <div class="wm-card wm-help-section" id="income">
            <h2>6. Income</h2>
            <p>Income is the base of every envelope. Two kinds appear here:</p>
            <ul>
                <li><strong>Automatic</strong> — paid sales and payments. These sync on their own (and again about 40 minutes past each hour). Open a row and use <em>View source</em> to see the original sale or payment.</li>
                <li><strong>Manual</strong> — gifts, transfers, other inflows. Use <em>Add manual income</em>. Attach a receipt if you have one. You can tag a company, user, category, or program.</li>
            </ul>
            <div class="wm-help-callout">If Overview looks empty, check Income for the same month. Then click <strong>Sync paid sales</strong> at the bottom of Settings if a recent payment is missing.</div>
        </div>

        <div class="wm-card wm-help-section" id="expenses">
            <h2>7. Expenses</h2>
            <p>Record operating spend, investment purchases, and charity outflows here. Every expense should have:</p>
            <ul>
                <li>An <strong>expense category</strong> (created on the Expense Category tab)</li>
                <li>A <strong>70 / 20 / 10</strong> envelope — Operations, Investment, or Charity</li>
                <li>Optional subcategory, program, company, vendor, and receipt</li>
            </ul>
            <figure class="wm-help-figure">
                <img src="{{ asset('wealth/guide/wm-help-expenses.png') }}" alt="Expense form highlighting the 70 20 10 classification dropdown">
                <figcaption>Figure 6 — Always classify the envelope. Unclassified rows do not count toward You can spend / Due for investment / Due for giving.</figcaption>
            </figure>
            <p>Tick <em>Also create Investment register row</em> or <em>Also create Charity register row</em> when the same spend should appear on those registers.</p>
            <div class="wm-help-callout wm-help-warn">Yellow “Unclassified expenses” means older rows have no envelope. Open Expenses with the unclassified filter and assign each one.</div>
        </div>

        <div class="wm-card wm-help-section" id="categories">
            <h2>8. Expense categories</h2>
            <p>Open <a href="{{ route('expense_categories.index') }}">Expense Category</a> (last items in the Wealth Manager tabs, before Help). Click <strong>Add Expense Category</strong>. The code is generated for you: first <code>001</code>, then <code>002</code>, and so on. Type the name (for example Fuel, Rent, Donations) and Submit.</p>
            <p>These categories are the labels on expense lines. They are separate from the 70 / 20 / 10 envelope. Example: category = “Fuel”, envelope = Operations.</p>
        </div>

        <div class="wm-card wm-help-section" id="programs">
            <h2>9. Programs</h2>
            <p>A program is a named project (a campaign, a construction job, a ministry activity). Create it with a name, code, optional budget, expected income, dates, and manager.</p>
            <p>Open a program to see income in, expenses out, and how much of the budget is used. Tag income and expenses with that program so Overview can show a program card.</p>
        </div>

        <div class="wm-card wm-help-section" id="allocations">
            <h2>10. Allocations</h2>
            <p>Allocations shows the three envelopes for the selected month: expected amount, used amount, and remaining.</p>
            <ul>
                <li>Operations remaining = <strong>You can spend</strong></li>
                <li>Investment remaining = <strong>Due for investment</strong></li>
                <li>Charity remaining = <strong>Due for giving</strong></li>
            </ul>
            <p class="mb-0">If remaining is negative, that envelope is overspent for the month.</p>
        </div>

        <div class="wm-card wm-help-section" id="investments">
            <h2>11. Investments</h2>
            <p>The investment register tracks the 20% envelope in real assets: equipment, property, instruments, and similar. Record name, type, amount, date, status, and optional current value. Gain / loss is current value minus amount invested.</p>
            <p>The KPIs compare <em>Expected 20%</em> for the month with <em>Actually invested</em>.</p>
        </div>

        <div class="wm-card wm-help-section" id="charity">
            <h2>12. Charity</h2>
            <p>Record giving against the 10% envelope: beneficiary, amount, date, and optional category. The KPIs show expected 10%, already given, and remaining due for giving.</p>
        </div>

        <div class="wm-card wm-help-section" id="reports">
            <h2>13. Reports</h2>
            <p>Reports repeats the month’s income, expenses, balance, health score, and envelopes. Export:</p>
            <ul>
                <li><strong>PDF</strong> — printable month pack</li>
                <li><strong>CSV Income</strong> / <strong>CSV Expenses</strong> — spreadsheet lines</li>
                <li><strong>Print</strong> — browser print of the page</li>
            </ul>
            <p>The month filter on Reports is the same filter used everywhere else.</p>
        </div>

        <div class="wm-card wm-help-section" id="settings">
            <h2>14. Settings</h2>
            <p>Settings is for administrators.</p>
            <ul>
                <li><strong>Allocation rules</strong> — percentages must total 100%. Default is 70 / 20 / 10 on gross income.</li>
                <li><strong>Financial Health bands &amp; weights</strong> — where Excellent / Fair / Critical start, and how much each factor counts.</li>
                <li><strong>Income categories</strong> and <strong>expense subcategories</strong> — finer labels under each envelope.</li>
                <li><strong>Sync paid sales</strong> — pull recent paid invoices into Income now.</li>
            </ul>
        </div>

        <div class="wm-card wm-help-section" id="faq">
            <h2>15. FAQ</h2>
            <h3>Why is “You can spend” zero?</h3>
            <p>Either there is no income in that month, or operations expenses have already used the 70% envelope. Check Income, then Expenses classified as Operations.</p>
            <h3>Why does Financial Health look wrong?</h3>
            <p>Confirm the month at the top. Assign unclassified expenses. Make sure investment and charity spends are classified (or recorded on those registers), not left as Operations.</p>
            <h3>Where do I create categories and programs?</h3>
            <p>Categories: <a href="{{ route('expense_categories.index') }}">Expense Category</a> in the Wealth Manager tabs. Programs: <a href="{{ route('wealth.programs') }}">Programs</a>.</p>
            <h3>Does this replace accounting?</h3>
            <p>No. Wealth Manager sits on top of sales, payments, and expenses so leadership can see monthly envelopes and health. Accounting documents stay where they are.</p>
            <p class="mb-0"><a class="wm-btn" href="{{ route('wealth.overview') }}">Back to Overview</a>
                <a class="wm-btn wm-btn-out" href="{{ route('wealth.health') }}">Open Financial Health</a></p>
        </div>
    </div>
</section>
@endsection
