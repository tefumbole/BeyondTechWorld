@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('asset.sale.list')}}" class="btn btn-info"><i class="dripicons-list"></i> Asset Sale List </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Sale Asset Detail</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-5">
                                    <h2>Buyer Info</h2><hr>
                                    <table class="table table-striped p-5">
                                        <tbody>
                                            <tr>
                                                <th>Name</th>
                                                <td>{{ $data->buyer_name }}</td>
                                            </tr>
                                            <tr>
                                                <th>Phone</th>
                                                <td>{{ $data->buyer_number }}</td>
                                            </tr>
                                            <tr>
                                                <th>Eamil</th>
                                                <td>{{ $data->buyer_email }}</td>
                                            </tr>
                                            <tr>
                                                <th>ID Card Number</th>
                                                <td>{{ $data->buyer_id }}</td>
                                            </tr>
                                            <tr>
                                                <th>ID Card Issue Date</th>
                                                <td>{{ $data->buyer_id_date }}</td>
                                            </tr>
                                            <tr>
                                                <th>Remarks</th>
                                                <td>{{ $data->buyer_remark }}</td>
                                            </tr>
                                            <tr>
                                                <th>Total</th>
                                                <td>{{ $data->buyer_total_amount }}</td>
                                            </tr>
                                        </tbody>
                                        <tfoot>

                                        </tfoot>
                                    </table>
                                </div>
                                <div class="col-md-7">
                                    <h2>Asset Sale Detail</h2><hr>
                                    <table class="table table-bordered table-striped p-5">
                                        <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Asset Name</th>
                                            <th>Department Name</th>
                                            <th>Asset Price</th>
                                            <th>Asset Usefull Life</th>
                                            <th>Remarks</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($data->saleDetails as $asset)
                                            @php
                                                $asset_info = \App\Asset::with('department')->find($asset->asset_id);
                                            @endphp
                                            <tr>
                                                <td>{{ $asset_info->serial_no }}</td>
                                                <td>{{ $asset->asset_name }}</td>
                                                <td>{{ $asset_info->department->name }}</td>
                                                <td>{{ $asset->price }}</td>
                                                <td>{{ $asset->life_span }}</td>
                                                <td>{{ $asset->remark }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">

        // menu open
        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-sale-menu").addClass("active");
    </script>
@endsection
