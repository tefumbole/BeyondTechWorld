<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
    <title>{{$general_setting->site_title}}</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="all,follow">

    <style type="text/css">
        .float-right {
            float: right;
        }
        .p-5{
            padding: 2%;
        }
        .underline{
            text-decoration: underline;
            font-style: oblique;
            color: red;
        }
        .waterm-mark {
            width: 25%;
            position: absolute;
            top: 35%;
            right: 330px;
            opacity: 0.2;
        }
        * {
            font-size: 24px;
            line-height: 24px;
            font-family: 'Ubuntu', sans-serif;
            text-transform: capitalize;
        }
        .btn {
            padding: 7px 10px;
            text-decoration: none;
            border: none;
            display: block;
            text-align: center;
            margin: 7px;
            cursor:pointer;
        }

        .btn-info {
            background-color: #999;
            color: #FFF;
        }

        .btn-primary {
            background-color: #6449e7;
            color: #FFF;
            width: 100%;
        }
        td,
        th,
        tr,
        table {
            border-collapse: collapse;
        }
        tr {border-bottom: 1px dotted #ddd;}
        .sale td,th {padding: 7px 0;width: 50%;}
        .sale-detail td,th {
            padding: 7px 0;
            width: 15%;
            text-align: left;
        }

        table {width: 100%;}
        tfoot tr th:first-child {text-align: left;}

        .centered {
            text-align: center;
            align-content: center;
        }
        small{font-size:20px;}

        @media print {
            * {
                font-size:16px;
                line-height: 20px;
            }
            .lastPage {
                position: fixed;
                bottom: 0;
            }
            .detail {
                line-height: 30px;
            }
            td,th {padding: 5px 0;}
            .hidden-print {
                display: none !important;
            }
            @page { margin: 0; } body { margin: 0.5cm; margin-bottom:1.6cm; }
            #print-footer {
                bottom: 0;
            }
        }
    </style>
</head>
<body>
@if($general_setting->invoice_format == 'beyond_a4')
    <style>
        .btn {
            width: 25% !important;
        }
        .btn-info {
            float: right;
        }
    </style>
    <img src="{{url('public/logo', $header)}}" style=" width: 100%;">
    <img src="{{url('public/logo', $water_mark)}}" class="waterm-mark">
    <div style="max-width:800px;margin:0 auto; ">
        @else
            <div style="max-width:600px;margin:0 auto; ">
                @endif

                @if(preg_match('~[0-9]~', url()->previous()))
                    @php $url = '../../pos'; @endphp
                @else
                    @php $url = url()->previous(); @endphp
                @endif
                <div class="centered">
                    @if($general_setting->site_logo && $general_setting->invoice_format != 'beyond_a4')
                        <img src="{{url('public/logo', $general_setting->site_logo)}}"  width="150px" style="margin:0 50px 0 0;filter: brightness(0);">
                    @endif
                </div>
                <div class="hidden-print">
                    <table>
                        <tr>
                            <td><a onclick="window.history.back()" class="btn btn-info"><i class="fa fa-arrow-left"></i> {{trans('file.Back')}}</a> </td>
                            <td><button onclick="window.print();" class="btn btn-primary"><i class="dripicons-print"></i> {{trans('file.Print')}}</button></td>
                        </tr>
                    </table>
                    <br>
                </div>
                <div id="receipt-data">
                    <div class="centered">
                        @php
                            if($general_setting != null) {
                                $curency = \App\Currency::where('id', $general_setting->currency)->select('code')->first()->code;
                            } else {
                                $curency = 'USD';
                            }
                        @endphp
                        <div><h1 style="font-size: 22px" class="underline">Transfer Certificate</h1></div>
                    </div>
                    <br>
                    <p class="detail">This certificate attest to a transfer of</p>
                    <h3>Asset Information</h3>
                    <p class="detail">
                        Name __<span class="underline">{{ $asset->assets->name }}</span>__
                        Barcode __<span class="underline">{{ $asset->assets->serial_no }}</span>__

                        @if($asset->assets->asset_type == 'vehicle')
                            Chesis No. ___<span class="underline">{{ @$asset->assets->chasis_no }}</span>___
                        @endif
                        @if($asset->assets->asset_type == 'computer')
                            RAM _<span class="underline">{{ @$asset->assets->ram }} GB</span>_ Storage _<span class="underline">{{ @$asset->assets->hard_drive }}</span>_ Processor _<span class="underline">{{ $asset->assets->processor }}</span>_
                        @endif
                        @if($asset->assets->asset_type == 'tvs')
                            Tv Size ___<span class="underline">{{ @$asset->assets->tv_size }}</span>___
                        @endif
                        @if($asset->assets->asset_type == 'software')
                            Source Code Owner ___<span class="underline">{{ @$asset->assets->source_code_owner }}</span>___
                        @endif
                        @if($asset->assets->asset_type == 'land')
                            House In Land ___<span class="underline">{{ @$asset->assets->house_in_land }}</span>___
                        @endif
                        @if($asset->assets->asset_type == 'house')
                            No. of Rooms ___<span class="underline">{{ @$asset->assets->number_of_Room }}</span>___
                        @endif
                    </p>
                    <h3>From: </h3>
                    <p class="detail">
                        Department __<span class="underline">{{ $asset->fromDepartment->name }}</span>__
                        Department No. __<span class="underline">{{ $asset->fromDepartment->code }}</span>__
                        Region __<span class="underline">{{ @$asset->assets->region->name }}</span>__
                        Station __<span class="underline">{{ @$asset->assets->station->name }}</span>__
                    </p>
                    <h3>To: </h3>
                    <p class="detail">
                        Department __<span class="underline">{{ $asset->toDepartment->name }}</span>__
                        Department No. __<span class="underline">{{ $asset->toDepartment->code }}</span>__
                        Region __<span class="underline">{{ @$asset->assets->region->name }}</span>__
                        Station __<span class="underline">{{ @$asset->assets->station->name }}</span>__
                    </p>

                    <br><br><br>
                    <div class="centered">
                        <img style="margin-top:10px;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAXoAAAAeAQMAAAAYfEcrAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAEdJREFUOI3ty7EJwDAMBECDWoNXEag1aHXBt4ZfxaBWkGSLNGoPbuQUx9RH85KJRboiQDjPLsvCko9FreK6xejQoUOHDv+HF1r8IFaUe7FaAAAAAElFTkSuQmCC" width="300" alt="barcode">                    <br>
                        <img style="margin-top:10px;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAD8AAAA/AQMAAABtkYKcAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAM1JREFUKJGd0bENAjEMBdCPKNLBBscikbJWruGK2wNWYQJYge7aSGlSnPxxElAIUOHqVf5fNsg1JkfBT7AgwAosdg2Rlsny+j+AT4R9hkYoalZF7nN7FfsCmaFzSKhTIfv1kMxZMoAeE2cuCngz8769NITJRd6ZoNFh/IATRc4yCjk2UMuwwm5vCD0il1gahmmZLw1aXqZFNysGb8R3kJG6MB9hQ9k15EON1BoZ3s09xLtzwQA7HHt4J0+YgA6ioSgRMKc3lDvz9HzBioYHSMg5A6js0wQAAAAASUVORK5CYII=" alt="barcode">
                    </div>

                    <br><br><br>
                    <br><br><br>
                    <div>
                        <div style="float: left"> ____________________________ <br> Prepared By</div>
                        <div style="float: right"> ____________________________ <br> Approved By</div>
                    </div>
                </div>
            </div>
            @if($general_setting->invoice_format == 'beyond_a4')
                <div class="lastPage" >
                    <img id="print-footer" src="{{url('public/logo', $footer)}}" style=" width: 100%;">
                </div>
            @endif
            <script type="text/javascript">
                localStorage.clear();
                function auto_print() {
                    window.print()
                }
                setTimeout(auto_print, 1000);
            </script>

</body>
</html>
