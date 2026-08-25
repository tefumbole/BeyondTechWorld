<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>
        @if($kind === 'internship') Internship Report
        @elseif($kind === 'detailed') Detailed Timesheet
        @else Timesheet Summary
        @endif
        — Beyond Enterprise
    </title>
    <style>
        @page { margin: 12mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            color: #1f2a44;
            font-size: 13px;
            line-height: 1.45;
            background: #e8edf5;
        }
        .sheet {
            max-width: 820px;
            margin: 16px auto;
            background: #fff;
            padding: 0 0 28px;
            box-shadow: 0 8px 28px rgba(15, 35, 80, .12);
        }
        .letter-header img, .letter-footer img { width: 100%; display: block; }
        .letter-body { padding: 18px 36px 8px; }
        .doc-kicker {
            color: #8a7424;
            letter-spacing: .14em;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0 0 6px;
        }
        .doc-title {
            color: #0b3f90;
            font-size: 22px;
            margin: 0 0 4px;
            font-weight: 800;
            font-family: Georgia, "Times New Roman", serif;
        }
        .doc-period { color: #64748b; margin: 0 0 16px; }
        .identity {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid #e6edf7;
            background: linear-gradient(180deg, #f8fbff, #fff);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 16px;
        }
        .person-name { font-size: 20px; color: #0b3f90; font-weight: 800; margin: 0; }
        .matricule {
            display: inline-block;
            margin-top: 6px;
            background: #f8efc8;
            color: #8a7424;
            font-weight: 700;
            letter-spacing: .05em;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 12px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e8eef6; padding: 7px 6px; text-align: left; vertical-align: top; }
        th { color: #0b3f90; font-size: 12px; }
        .num { text-align: right; white-space: nowrap; }
        .stats { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 16px; }
        .stat {
            flex: 1 1 140px;
            border: 1px solid #e6edf7;
            border-radius: 8px;
            padding: 10px 12px;
        }
        .stat b { display: block; color: #0b3f90; font-size: 18px; }
        .stat span { color: #64748b; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .sign-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .sign-block { min-width: 220px; }
        .sign-block img { height: 52px; width: auto; max-width: 200px; display: block; }
        .sign-line { border-top: 1px solid #94a3b8; margin-top: 8px; padding-top: 6px; font-size: 12px; }
        .qr-block { text-align: center; }
        .qr-block img { width: 96px; height: 96px; }
        .qr-block small { display: block; color: #64748b; font-size: 10px; max-width: 140px; }
        .no-print { text-align: center; padding: 12px; }
        .no-print button {
            background: #0b3f90; color: #fff; border: 0; border-radius: 8px;
            padding: 10px 18px; font-weight: 700; cursor: pointer;
        }
        .page-break { page-break-after: always; }
        @media print {
            body { background: #fff; }
            .sheet { box-shadow: none; margin: 0; max-width: none; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="no-print">
    <button type="button" onclick="window.print()">Print / Save as PDF</button>
</div>

@php
    $pages = $report['people'] ?? [];
    if ($kind === 'internship') {
        $pages = !empty($report['interns']) ? $report['interns'] : ($report['scope'] === 'one' ? $report['people'] : []);
    }
    $headerUrl = $quotationHeaderUrl ?? ($letterhead['header_url'] ?? null);
    $footerUrl = $quotationFooterUrl ?? ($letterhead['footer_url'] ?? null);
@endphp

@if($kind !== 'internship')
    <div class="sheet">
        @if($headerUrl)
            <div class="letter-header"><img src="{{ $headerUrl }}" alt="Beyond Enterprise"></div>
        @endif
        <div class="letter-body">
            <p class="doc-kicker">Beyond Enterprise</p>
            <h1 class="doc-title">{{ $kind === 'detailed' ? 'Detailed Timesheet Report' : 'Timesheet Summary Report' }}</h1>
            <p class="doc-period">
                Period {{ \Carbon\Carbon::parse($from)->format('j F Y') }} – {{ \Carbon\Carbon::parse($to)->format('j F Y') }}
                · {{ $report['people_count'] }} {{ \Illuminate\Support\Str::plural('person', $report['people_count']) }}
            </p>

            <div class="stats">
                <div class="stat"><span>Hours logged</span><b>{{ number_format($report['total_logged'], 1) }}</b></div>
                <div class="stat"><span>Hours expected</span><b>{{ number_format($report['total_expected'], 1) }}</b></div>
                <div class="stat"><span>Activities</span><b>{{ $report['total_activities'] }}</b></div>
                <div class="stat"><span>Entries</span><b>{{ $report['total_entries'] }}</b></div>
            </div>

            <h3 style="color:#0b3f90;margin:0 0 8px;">People</h3>
            <table>
                <thead>
                    <tr>
                        <th>Full name</th>
                        <th>Matricule</th>
                        <th class="num">Logged</th>
                        <th class="num">Expected</th>
                        <th class="num">Activities</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pages as $person)
                        <tr>
                            <td>{{ $person['name'] }}</td>
                            <td>{{ $person['matricule'] }}</td>
                            <td class="num">{{ number_format($person['logged'], 2) }}</td>
                            <td class="num">{{ number_format($person['expected'], 2) }}</td>
                            <td class="num">{{ $person['activity_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if($kind === 'summary')
                <h3 style="color:#0b3f90;margin:18px 0 8px;">Activities carried out</h3>
                <table>
                    <thead><tr><th>Activity</th><th class="num">Hours</th></tr></thead>
                    <tbody>
                        @forelse($report['by_activity'] as $act => $hrs)
                            <tr>
                                <td>{{ $act }}</td>
                                <td class="num">{{ number_format($hrs, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No activities in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <h3 style="color:#0b3f90;margin:18px 0 8px;">Detailed log</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Activity</th>
                            <th>Task</th>
                            <th class="num">Hours</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['rows'] as $row)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($row->entry_date)->format('j M Y') }}</td>
                                <td>{{ $row->employee_name ?: '—' }}</td>
                                <td>{{ $row->activity_name ?: '—' }}</td>
                                <td>
                                    @if($row->assignment)
                                        Day {{ $row->assignment->progression_day }}
                                        @if($row->assignment->task) — {{ $row->assignment->task->title }} @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="num">{{ number_format((float) $row->hours, 2) }}</td>
                                <td>{{ str_replace('_', ' ', $row->status) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="sign-row">
                <div class="sign-block">
                    @if(!empty($sign['src']))
                        <img src="{{ $sign['src'] }}" alt="Admin signature">
                    @endif
                    <div class="sign-line">
                        <strong>{{ $adminName ?: 'Administrator' }}</strong><br>
                        Authorized signature · {{ now()->format('j F Y') }}
                    </div>
                </div>
            </div>
        </div>
        @if($footerUrl)
            <div class="letter-footer"><img src="{{ $footerUrl }}" alt=""></div>
        @endif
    </div>
@else
    @forelse($pages as $index => $person)
        <div class="sheet {{ $index < count($pages) - 1 ? 'page-break' : '' }}">
            @if($headerUrl)
                <div class="letter-header"><img src="{{ $headerUrl }}" alt="Beyond Enterprise"></div>
            @endif
            <div class="letter-body">
                <p class="doc-kicker">Beyond Letter · Internship</p>
                <h1 class="doc-title">Internship Timesheet Report</h1>
                <p class="doc-period">
                    Report period {{ \Carbon\Carbon::parse($from)->format('j F Y') }} – {{ \Carbon\Carbon::parse($to)->format('j F Y') }}
                </p>

                <div class="identity">
                    <div>
                        <h2 class="person-name">{{ $person['name'] }}</h2>
                        <span class="matricule">Matricule {{ $person['matricule'] }}</span>
                        @if($person['program'])
                            <div style="margin-top:8px;">Programme: <strong>{{ $person['program'] }}</strong></div>
                        @endif
                        @if(!empty($person['duration']['label']))
                            <div>Duration of internship: <strong>{{ $person['duration']['label'] }}</strong></div>
                        @endif
                    </div>
                    <div class="qr-block">
                        <img src="data:image/png;base64,{{ DNS2D::getBarcodePNG($person['verify_url'], 'QRCODE') }}" alt="Verification QR">
                        <small>Scan to verify name, duration, and status</small>
                    </div>
                </div>

                <div class="stats">
                    <div class="stat"><span>Activities carried out</span><b>{{ $person['activity_count'] }}</b></div>
                    <div class="stat"><span>Hours put in</span><b>{{ number_format($person['logged'], 1) }}</b></div>
                    <div class="stat"><span>Expected hours</span><b>{{ number_format($person['expected'], 1) }}</b></div>
                    <div class="stat"><span>Entries</span><b>{{ $person['entry_count'] }}</b></div>
                </div>

                <h3 style="color:#0b3f90;margin:0 0 8px;">Summary of activities</h3>
                <table>
                    <thead><tr><th>Activity</th><th class="num">Hours</th></tr></thead>
                    <tbody>
                        @forelse($person['by_activity'] as $act => $hrs)
                            <tr>
                                <td>{{ $act }}</td>
                                <td class="num">{{ number_format($hrs, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No activities recorded in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if($person['rows']->count())
                    <h3 style="color:#0b3f90;margin:18px 0 8px;">Detailed work log</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Activity</th>
                                <th>Task</th>
                                <th class="num">Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($person['rows'] as $row)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($row->entry_date)->format('j M Y') }}</td>
                                    <td>{{ $row->activity_name ?: '—' }}</td>
                                    <td>
                                        @if($row->assignment)
                                            Day {{ $row->assignment->progression_day }}
                                            @if($row->assignment->task) — {{ $row->assignment->task->title }} @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="num">{{ number_format((float) $row->hours, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="sign-row">
                    <div class="sign-block">
                        @if(!empty($sign['src']))
                            <img src="{{ $sign['src'] }}" alt="Admin signature">
                        @endif
                        <div class="sign-line">
                            <strong>{{ $adminName ?: 'Administrator' }}</strong><br>
                            Admin signature · {{ now()->format('j F Y') }}
                        </div>
                    </div>
                    <div class="qr-block">
                        <div style="font-size:11px;color:#047857;font-weight:700;margin-bottom:4px;">STATUS VALID</div>
                        <small>QR confirms the student’s name and internship duration.</small>
                    </div>
                </div>
            </div>
            @if($footerUrl)
                <div class="letter-footer"><img src="{{ $footerUrl }}" alt=""></div>
            @endif
        </div>
    @empty
        <div class="sheet">
            <div class="letter-body">
                <h1 class="doc-title">No internship report</h1>
                <p>There is no internship student in this selection for the chosen period.</p>
            </div>
        </div>
    @endforelse
@endif
</body>
</html>
