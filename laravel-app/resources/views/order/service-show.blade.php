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
            @if($errors->has('name'))
                <div class="alert alert-danger alert-dismissible text-center">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
            @endif
            @if(session()->has('message'))
                <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
            @endif
            @if(session()->has('not_permitted'))
                <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
            @endif
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('services.list')}}" class="btn btn-info"><i class="dripicons-list"></i>
                        Service List</a>
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
                            <h3>Reference: {{ $data->reference }} </h3>
                            <h3>Customer Name: {{ $data->name }} </h3>
                            <h3>Customer Phone: {{ $data->phone }} </h3>
                            <h3>Customer MTN Phone: {{ $data->mtn_phone }} </h3>
                            <h3>Customer Email: {{ $data->email }} </h3>
                            <h3>Customer Address: {{ $data->city }}, {{ $data->state }}, {{ $data->address }} </h3><br>
                            <h3>Service Date: {{ $data->created_at->format('d M, Y H:i:s') }} </h3>
                            <h3>Service Payment Status :
                                @if($data->payment_status == 0)
                                    <span class="badge badge-warning">Pending</span>
                                @elseif($data->payment_status == 1)
                                    <span class="badge badge-success">Complete</span>
                                @elseif($data->payment_status == 2)
                                    <span class="badge badge-danger">Rejected</span>
                                @endif
                            </h3>
                            <h3>Service Status :
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
                                @php $total = 0; @endphp
                                @foreach($data->orderProducts as $orderProduct)
                                    @php
                                        $product_image = explode(",", $orderProduct->product->image);
                                        $product_image = htmlspecialchars($product_image[0]);
                                    @endphp
                                    <tr>
                                        <th>Image</th>
                                        <td><img src="{{ url('public/images/product', $product_image) }}" alt="product image" width="100px"></td>
                                    </tr>
                                    <tr>
                                        <th>Name</th>
                                        <td>{{ $orderProduct->product->name }}</td>
                                    </tr>
                                    @if($data->subject)
                                        <tr>
                                            <th>Subject</th>
                                            <td>{{ $data->subject }}</td>
                                        </tr>
                                    @endif
                                    @if($data->project_title)
                                        <tr>
                                            <th>Project Title</th>
                                            <td>{{ $data->project_title }}</td>
                                        </tr>
                                    @endif
                                    @if($data->project_guide_lines)
                                        <tr>
                                            <th>Project Guide Lines</th>
                                            <td>{{ $data->project_guide_lines }}</td>
                                        </tr>
                                    @endif
                                    @if($data->citation_sytle)
                                        <tr>
                                            <th>Citation Sytle</th>
                                            <td>{{ $data->citation_sytle }}</td>
                                        </tr>
                                    @endif
                                    @if($data->font_style)
                                        <tr>
                                            <th>Font Style</th>
                                            <td>{{ $data->font_style }}</td>
                                        </tr>
                                    @endif
                                    @if($data->language)
                                        <tr>
                                            <th>Language</th>
                                            <td>{{ $data->language }}</td>
                                        </tr>
                                    @endif
                                    @if($data->references)
                                        <tr>
                                            <th>References</th>
                                            <td>{{ $data->references }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Price</th>
                                        <td>{{ number_format($data->grand_total, 2) }} {{ $currency->code }}</td>
                                    </tr>
                                    @if($data->academic_year)
                                        <tr>
                                            <th>Academic Year</th>
                                            <td>{{ $data->academic_year }}</td>
                                        </tr>
                                    @endif
                                    @if($data->variant_id)
                                        <tr>
                                            <th>Deadline</th>
                                            <td>{{ $data->variant_id }}</td>
                                        </tr>
                                    @endif
                                    @if($data->number_of_pages)
                                        <tr>
                                            <th>Number of Pages</th>
                                            <td>{{ $data->number_of_pages }}</td>
                                        </tr>
                                    @endif
                                    @if($data->word_count)
                                        <tr>
                                            <th>Word Count</th>
                                            <td>{{ $data->word_count }}</td>
                                        </tr>
                                    @endif
                                    @if($data->spacing)
                                        <tr>
                                            <th>Spacing</th>
                                            <td>{{ $data->spacing }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <th>Documents</th>
                                        <td>
                                            <ul>
                                                @if($data->customer_doc)
                                                    <li>
                                                        <a class="label-delivery label-delivered" href="{{url('public/images/customer/docs/', $data->customer_doc)}}" target="_blank">View Customer Doc </a>
                                                    </li>
                                                @endif
                                                @if($data->sample_doc)
                                                    <li>
                                                        <a class="label-delivery label-delivered" href="{{url('public/images/customer/docs/', $data->sample_doc)}}" target="_blank">View Sample Doc </a>
                                                    </li>
                                                @endif
                                                @if($data->result_doc)
                                                    <li>
                                                        <a class="label-delivery label-delivered" href="{{url('public/images/customer/docs/', $data->result_doc)}}" target="_blank">View Delivered Doc </a>
                                                    </li>
                                                @endif
                                            </ul>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Addons</th>
                                        <td>
                                            <ul class="list-disc">
                                                @if($data->quality_double_checker)<li> Quality Double Checker</li>@endif
                                                @if($data->abstract_page)<li> Abstract Page</li>@endif
                                                @if($data->one_page_summary)<li> One Page Summary</li>@endif
                                                @if($data->grammar_checker)<li> Grammar Checker</li>@endif
                                                @if($data->preferred_expert)<li> Preferred Expert</li>@endif

                                            </ul>
                                        </td>
                                    </tr>
                                    {{--                            <tr>--}}
                                    {{--                                <th>Quantity</th>--}}
                                    {{--                                <td>{{ $orderProduct->quantity }}</td>--}}
                                    {{--                            </tr>--}}
                                    <tr>
                                        <th>Total</th>
                                        <td>{{ number_format($data->grand_total, 2) }} {{ $currency->code }}</td>
                                    </tr>
                                @endforeach
                            </table>
                    </div>
                    @if(in_array("services-edit", $all_permission))
                        <div class="card">
                            <div class="card-body align-items-center">
                                <h3>Update</h3>
                                <form action="{{ route('service.update') }}" class="row" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Order Status <strong>*</strong> </label>
                                            <select name="order_status" class="form-control order-status">
                                                <option value="0" {{ $data->order_status == 0 ? 'selected' : '' }}>Pending</option>
                                                <option value="1" {{ $data->order_status == 1 ? 'selected' : '' }}>Complete</option>
                                                <option value="3" {{ $data->order_status == 3 ? 'selected' : '' }}>Ready For Delivery</option>
                                                <option value="2" {{ $data->order_status == 2 ? 'selected' : '' }}>Rejected</option>
                                            </select>
                                            <input type="hidden" name="id" value="{{ $data->id }}">
                                        </div>
                                    </div>
                                    <div class="col-md-6 delivery-date">
                                        <div class="form-group">
                                            <label>Delivery Expected Date <strong>*</strong> </label>
                                            <input type="date" name="delivery_date" value="{{ $data->delivery_date }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Payment Status <strong>*</strong> </label>
                                            <select name="payment_status" class="form-control order-status">
                                                <option value="0" {{ $data->payment_status == 0 ? 'selected' : '' }}>Pending</option>
                                                <option value="1" {{ $data->payment_status == 1 ? 'selected' : '' }}>Complete</option>
                                                <option value="2" {{ $data->payment_status == 2 ? 'selected' : '' }}>Rejected</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group mt-4">
                                            <label>Is Approve </label>
                                            <input name="is_approve" value="1" type="checkbox" {{ $data->is_approve == 1 ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Result Doc </label>
                                            <input class="form-control" name="result_doc" type="file">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Old Doc </label><br>
                                            @if($data->result_doc)
                                                <a class="label-delivery label-delivered" href="{{url('public/images/customer/docs/', $data->result_doc)}}" target="_blank">View Delivered Doc </a><br>
                                                <a class="text-danger" onclick="return confirm('Are you sure you want to delete this item?');" href="{{ route('order.delete.doc', $data->id) }}">Delete Old Doc </a>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <input class="btn btn-success" value="Update" type="submit">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endif
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
        $("ul#order #service-list-menu").addClass("active");

        if($('.order-status').val() != 3) {
            $('.delivery-date').hide();
        }

        $('.order-status').on('change', function() {
            var status = $(this).val();
            if(status == 3) {
                $('.delivery-date').show(300);
            } else {
                $('.delivery-date').hide(300);
            }
        });

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
