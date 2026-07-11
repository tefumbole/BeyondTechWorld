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
                    <a href="{{route('order.index')}}" class="btn btn-info"><i class="dripicons-list"></i>
                        {{trans('file.Order List')}} </a>

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
                            <h3>Customer Name: {{ $data->name }} </h3>
                            <h3>Customer Phone: {{ $data->phone }} </h3>
                            <h3>Customer MTN Phone: {{ $data->mtn_phone }} </h3>
                            <h3>Customer Email: {{ $data->email }} </h3>
                            <h3>Customer Address: {{ $data->city }}, {{ $data->state }}, {{ $data->address }} </h3><br>
                            <h3>Order Date: {{ $data->created_at->format('d M, Y') }} </h3>

                            <h3>Order Status :
                            @if($data->order_status == 0)
                                 <span class="badge badge-warning">Pending</span>
                            @elseif($data->order_status == 1)
                                <span class="badge badge-success">Complete</span>
                            @elseif($data->order_status == 2)
                                <span class="badge badge-danger">Rejected</span>
                            @elseif($data->order_status == 3)
                                <span class="badge badge-primary">Ready For Delivery</span>
                            @endif
                            </h3>
                        <br>
                        <table class="table table-striped table-bordered">
                            <tr>
                                <th>Image</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Sub Total</th>
                            </tr>
                            @php $total = 0; @endphp
                            @foreach($data->orderProducts as $orderProduct)
                                @php
                                    $product_image = explode(",", $orderProduct->product->image);
                                    $product_image = htmlspecialchars($product_image[0]);
                                @endphp
                                <tr>
                                    <td><img src="{{ url('public/images/product', $product_image) }}" alt="product image" width="100px"></td>
                                    <td>{{ $orderProduct->product->name }}</td>
                                    <td>{{ number_format($orderProduct->price, 2) }} {{ $currency->code }}</td>
                                    <td>{{ $orderProduct->quantity }}</td>
                                    <td>{{ number_format($orderProduct->price*$orderProduct->quantity, 2) }} {{ $currency->code }}</td>
                                    @php $total += $orderProduct->price*$orderProduct->quantity; @endphp
                                </tr>
                            @endforeach
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td>Grand Total</td>
                                <td>{{ number_format($total, 2) }} {{ $currency->code }}</td>
                            </tr>
                        </table>
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


        $("ul#order").siblings('a').attr('aria-expanded', 'true');
        $("ul#order").addClass("show");
        $("ul#order").addClass("active");

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
