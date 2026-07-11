@extends('layout.main')
@section('content')
<section class="booking-calendar-page">
    <style>
        .booking-calendar-page { padding-bottom: 24px; }
        .calendar-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 18px;
        }
        .calendar-toolbar h4 {
            margin: 0;
            font-weight: 800;
            color: #0b3f90;
        }
        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .calendar-nav a {
            color: #0b3f90;
            font-weight: 700;
            text-decoration: none;
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        .calendar-weekday {
            text-align: center;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #6f7b91;
            padding: 8px 0;
        }
        .calendar-day,
        .calendar-empty {
            min-height: 110px;
            border-radius: 14px;
            border: 1px solid #e3e9f4;
            background: #fff;
            padding: 10px;
        }
        .calendar-empty { background: #f8fbff; }
        .calendar-day {
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            position: relative;
        }
        .calendar-day:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(11, 63, 144, 0.12);
        }
        .calendar-day.is-today {
            border-color: #c6ab47;
            box-shadow: inset 0 0 0 1px #c6ab47;
        }
        .calendar-day.is-booked {
            background: linear-gradient(180deg, #fff7e6 0%, #fff 100%);
            border-color: #c6ab47;
        }
        .calendar-day.is-free {
            background: #f8fbff;
        }
        .calendar-day-number {
            font-size: 18px;
            font-weight: 800;
            color: #0b3f90;
            margin-bottom: 8px;
        }
        .calendar-day-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .calendar-day-status.booked {
            background: #0b3f90;
            color: #fff;
        }
        .calendar-day-status.free {
            background: #edf2f9;
            color: #6f7b91;
        }
        .calendar-legend {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            font-size: 13px;
            color: #5f6776;
        }
        .calendar-legend span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .calendar-legend i {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            display: inline-block;
        }
        @media print {
            .no-print, .side-navbar, .header, .beyond-module-tabs, .calendar-toolbar form, .calendar-toolbar button { display: none !important; }
            .calendar-day, .calendar-empty { min-height: 72px; box-shadow: none !important; transform: none !important; }
            .calendar-day.is-booked .calendar-day-status.booked::after { content: 'Booked'; }
            .calendar-day.is-booked .calendar-day-status.booked { font-size: 14px; background: #0b3f90; color: #fff; }
            .calendar-day.is-free .calendar-day-status { visibility: hidden; }
            .calendar-day-number { font-size: 16px; }
            body, .page-content { background: #fff !important; }
        }
    </style>

    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="calendar-toolbar no-print">
                    <div>
                        <h4>Booking Calendar</h4>
                        <div class="calendar-nav mt-2">
                            <a href="{{ url('report/daily_booking/'.$prev_year.'/'.$prev_month) }}">&larr; Previous</a>
                            <strong>{{ date('F Y', strtotime($year.'-'.$month.'-01')) }}</strong>
                            <a href="{{ url('report/daily_booking/'.$next_year.'/'.$next_month) }}">Next &rarr;</a>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        {{ Form::open(['route' => ['report.dailyBookingByWarehouse', $year, $month], 'method' => 'post', 'id' => 'report-form', 'class' => 'd-flex align-items-center gap-2 mb-0']) }}
                            <input type="hidden" name="warehouse_id_hidden" value="{{ $warehouse_id }}">
                            <select class="selectpicker form-control" id="warehouse_id" name="warehouse_id" style="min-width: 220px;">
                                <option value="0">{{ trans('file.All Warehouse') }}</option>
                                @foreach($lims_warehouse_list as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        {{ Form::close() }}
                        <button type="button" class="btn btn-primary" onclick="printCalendar()"><i class="fa fa-print"></i> Print</button>
                    </div>
                </div>

                <div class="calendar-grid">
                    @foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $weekday)
                        <div class="calendar-weekday">{{ $weekday }}</div>
                    @endforeach

                    @php
                        $day = 1;
                        $flag = false;
                    @endphp

                    @for($row = 0; $row < 6 && $day <= $number_of_day; $row++)
                        @for($col = 1; $col <= 7; $col++)
                            @if(!$flag && $col < $start_day)
                                <div class="calendar-empty"></div>
                            @elseif($day <= $number_of_day)
                                @php
                                    $info = $calendar_days[$day];
                                    $flag = true;
                                @endphp
                                <div class="calendar-day {{ $info['booked'] ? 'is-booked' : 'is-free' }} {{ $info['is_today'] ? 'is-today' : '' }}"
                                     data-date="{{ $info['date'] }}"
                                     data-day="{{ $day }}"
                                     onclick="openBookingDayModal('{{ $info['date'] }}')">
                                    <div class="calendar-day-number">{{ $day }}</div>
                                    <span class="calendar-day-status {{ $info['booked'] ? 'booked' : 'free' }}">
                                        {{ $info['booked'] ? 'Booked' : 'Free' }}
                                    </span>
                                </div>
                                @php $day++; @endphp
                            @else
                                <div class="calendar-empty"></div>
                            @endif
                        @endfor
                    @endfor
                </div>

                <div class="calendar-legend no-print">
                    <span><i style="background:#0b3f90;"></i> Booked day — click for details</span>
                    <span><i style="background:#edf2f9;border:1px solid #e3e9f4;"></i> Free day</span>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="bookingDayModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookingDayModalTitle">Booking Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="bookingDayModalBody">
                <p class="text-muted mb-0">No bookings for this date.</p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var bookingCalendarDetails = @json($booking_details);

    function openBookingDayModal(date) {
        var items = bookingCalendarDetails[date] || [];
        var title = 'Bookings for ' + date;
        var html = '';

        if (!items.length) {
            html = '<p class="text-muted mb-0">This date is free.</p>';
        } else {
            html = '<div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>Reference</th><th>Customer</th><th>Product</th><th>Qty</th><th>Start</th><th>End</th></tr></thead><tbody>';
            items.forEach(function (item) {
                html += '<tr>';
                html += '<td>' + (item.reference || '-') + '</td>';
                html += '<td>' + (item.customer || '-') + '</td>';
                html += '<td>' + (item.product || '-') + '</td>';
                html += '<td>' + (item.qty || '-') + '</td>';
                html += '<td>' + (item.start || '-') + '</td>';
                html += '<td>' + (item.end || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
        }

        $('#bookingDayModalTitle').text(title);
        $('#bookingDayModalBody').html(html);
        $('#bookingDayModal').modal('show');
    }

    function printCalendar() {
        window.print();
    }

    $("ul#booking").siblings('a').attr('aria-expanded','true');
    $("ul#booking").addClass("show");
    $("ul#booking #booking-report-menu").addClass("active");

    $('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
    $('.selectpicker').selectpicker('refresh');
    $('#warehouse_id').on('change', function () {
        $('#report-form').submit();
    });
</script>
@endsection
