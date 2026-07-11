@extends('layout.main') @section('content')
    <style>
        @media print {
            .align-items-center-logo {
                text-align: center;
                display: inline;
                margin-left: 40%;
            }

            .card {
                width: 60vw;
                margin-left: 15%;
                padding: 20px;
            }
            table {
                width: 100%;
            }

            tfoot tr th:first-child {
                text-align: left;
            }
        }
        .align-items-center-logo {
            text-align: center;
            display: inline;
            margin-left: 40%;
        }

        .card {
            width: 60vw;
            margin-left: 15%;
            padding: 20px;
        }
        table {
            width: 100%;
        }

        tfoot tr th:first-child {
            text-align: left;
        }
    </style>
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('shop.index')}}" class="btn btn-info"><i class="dripicons-list"></i>
                        Shop Listing </a>

                    {{-- <a href="{{ route('letter.send.whatsapp', $data->id) }}" class="btn btn-success"><i
                            class="fa fa-whatsapp"></i> Send Whatsapp PDF</a>--}}
                    {{-- <a href="{{ route('letter.send.mail', $data->id) }}" class="btn btn-primary"><i
                            class="dripicons-mail"></i> Send Mail</a>--}}
                    {{-- <a href="{{ route('letter.send.download', $data->id) }}" class="btn btn-warning"><i
                            class="dripicons-download"></i> Download PDF</a>--}}
                    <button id="print-btn" class="btn btn-info"><i class="dripicons-print"></i> Print</button>

                    <div class="card" id="order-body">
                        <div class="card-body align-items-center" id="letter-body">
                            <div class="align-items-center-logo">
                                @if($general_setting->site_logo)
                                    <img src="{{url('public/logo/', $general_setting->site_logo)}}" height="150" width="150"
                                         style="margin:10px 0;filter: brightness(0);">
                                @endif
                            </div>
                        </div>
                        <br>
                        <h3>
                            <h3>Shop Name: {{ $data->company_name }} </h3>
                            <h3>Vendor Name: {{ $data->name }} </h3>
                            <h3>Vendor Phone: {{ $data->phone }} </h3>
                            <h3>Vendor Email: {{ $data->email }} </h3>
                            <h3>Vendor Address: {{ @$data->customer->city }}, {{ @$data->customer->state }}, {{ @$data->customer->address }} </h3><br>
                            <h3>Created At: {{ $data->created_at->format('d M, Y') }} </h3>

                            <h3>Status :
                                <span class="badge badge-{{$data->is_active == 0 ? 'warning' : 'success'}}">{{$data->is_active == 0 ? 'In Active' : 'Active'}}</span>
                            </h3>
                            <h3>Can Donation :
                                <span class="badge badge-{{$data->can_donation == 0 ? 'warning' : 'success'}}">{{$data->can_donation == 0 ? 'No' : 'Yes'}}</span>
                            </h3>
                            <h3>Can Service :
                                <span class="badge badge-{{$data->can_service == 0 ? 'warning' : 'success'}}">{{$data->can_service == 0 ? 'No' : 'Yes'}}</span>
                            </h3>
                            <h3>Can Booking :
                                <span class="badge badge-{{$data->can_booking == 0 ? 'warning' : 'success'}}">{{$data->can_booking == 0 ? 'No' : 'Yes'}}</span>
                            </h3>
                            <hr>
                            <h3>Products Count: {{ $products }}</h3>
                            <h3>Order Count: {{ $orders }}</h3>
                            <h3>Payments Count: {{ $payments }}</h3>
                            <hr>

                            <h3>Pending Amount: {{ $pending_dues }} {{ $currency->code }}</h3>
                            <h3>Withdraw Amount: {{ $earning }} {{ $currency->code }}</h3>
                            <h3>Total Amount: {{ $pending_dues + $earning }} {{ $currency->code }}</h3>
                    </div>
                </div>
            </div>
{{--            <div class="pull-right">--}}
{{--                <a href="{{ route('letter.prev', ['id' => $data->id]) }}" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Prev</a>--}}
{{--                <a href="{{ route('letter.next', ['id' => $data->id]) }}" class="btn btn-primary">Next <i class="fa fa-arrow-right"></i></a>--}}
{{--            </div>--}}
        </div>
    </section>

    <script type="text/javascript">

        $("#print-btn").on("click", function () {
            var divToPrint = document.getElementById('order-body');
            var newWin = window.open('', 'Print-Window');
            newWin.document.open();
            newWin.document.write('<link rel="stylesheet" href="<?php echo asset('public / vendor / bootstrap / css / bootstrap.min.css') ?>" type="text/css"><style type="text/css">@media print {.modal-dialog { max-width: 1000px;} }</style><body onload="window.print()">' + divToPrint.innerHTML + '</body>');
            newWin.document.close();
            setTimeout(function () { newWin.close(); }, 30);
        });


        $("ul#shop").siblings('a').attr('aria-expanded', 'true');
        $("ul#shop").addClass("show");
        $("ul#shop").addClass("active");

        tinymce.init({
            selector: 'textarea',
            height: 130,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor textcolor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table contextmenu paste code wordcount'
            ],
            toolbar: 'insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
            branding: false
        });

    </script>
@endsection
