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
                <td><a href="{{$url}}" class="btn btn-info"><i class="fa fa-arrow-left"></i> {{trans('file.Back')}}</a> </td>
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
                <div><h1 style="font-size: 22px" class="underline">Sale Certificate</h1></div>
        </div>
        <br>
        <p class="detail">
            I, the undersined __<span class="underline">{{ $data->saller_title }}. {{ $data->saller_name }}</span>__,
            Holder of Nationality Card No, ___<span class="underline" style="color: red">{{ $data->saller_id }}</span>___
            issued at ___<span class="underline">{{ $data->saller_id_date }}</span>__ On __<span class="underline">{{ $data->saller_to }}</span>__
            Address ___<span class="underline">{{ $data->saller_address }}</span>__ Email __<span class="underline">{{ $data->saller_email }}</span>__
            Phone __<span class="underline">{{ $data->saller_number }}</span>__
            Have agreed by this Certificate as having sold<br><br>

            @foreach($data->saleDetails as $asset)
                @php
                    $asset_info = \App\Asset::find($asset->asset_id);
                @endphp
            Name. ___<span class="underline">{{ @$asset_info->name }}</span>___ Barcode ___<span class="underline">{{ @$asset_info->serial_no }}</span>__

                @if(@$asset_info->asset_type == 'vehicle')
                    Chesis No. ___<span class="underline">{{ @$asset_info->chasis_no }}</span>___
                @endif
                @if(@$asset_info->asset_type == 'computer')
                    RAM _<span class="underline">{{ @$asset_info->ram }} GB</span>_ Storage _<span class="underline">{{ @$asset_info->hard_drive }}</span>_ Processor _<span class="underline">{{ $asset_info->processor }}</span>_
                @endif
                @if(@$asset_info->asset_type == 'tvs')
                    Tv Size ___<span class="underline">{{ @$asset_info->tv_size }}</span>___
                @endif
                @if(@$asset_info->asset_type == 'software')
                    Source Code Owner ___<span class="underline">{{ @$asset_info->source_code_owner }}</span>___
                @endif
                @if(@$asset_info->asset_type == 'land')
                    House In Land ___<span class="underline">{{ @$asset_info->house_in_land }}</span>___
                @endif
                @if(@$asset_info->asset_type == 'house')
                    No. of Rooms ___<span class="underline">{{ @$asset_info->number_of_Room }}</span>___
                @endif

{{--            Price ___<span class="underline">{{ @$asset_info->price }}</span>____--}}
                <br>

            @endforeach
            <br>

            To __<span class="underline">{{ $data->buyer_title }}. {{ $data->buyer_name }}</span>___
            Holder of Nationality Card No, ___<span class="underline" style="color: red">{{ $data->buyer_id }}</span>___
            issued at ___<span class="underline">{{ $data->buyer_id_date }}</span>__ On __<span class="underline">{{ $data->buyer_to }}</span>__
            Address ___<span class="underline">{{ $data->buyer_address }}</span>__ Email __<span class="underline">{{ $data->buyer_email }}</span>__ Phone __<span class="underline">{{ $data->buyer_number }}</span>__
            The Said (__________) is sold at (figures): ___<span class="underline">{{ number_format($data->buyer_total_amount, 2) }}</span>___ <br>
            (Words): <span class="underline">{{ $words }}
        </p>
        <br><br><br>
        <div class="centered">
                <img style="margin-top:10px;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAXoAAAAeAQMAAAAYfEcrAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAEdJREFUOI3ty7EJwDAMBECDWoNXEag1aHXBt4ZfxaBWkGSLNGoPbuQUx9RH85KJRboiQDjPLsvCko9FreK6xejQoUOHDv+HF1r8IFaUe7FaAAAAAElFTkSuQmCC" width="300" alt="barcode">                    <br>
                <img style="margin-top:10px;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAD8AAAA/AQMAAABtkYKcAAAABlBMVEX///8AAABVwtN+AAAAAXRSTlMAQObYZgAAAAlwSFlzAAAOxAAADsQBlSsOGwAAAM1JREFUKJGd0bENAjEMBdCPKNLBBscikbJWruGK2wNWYQJYge7aSGlSnPxxElAIUOHqVf5fNsg1JkfBT7AgwAosdg2Rlsny+j+AT4R9hkYoalZF7nN7FfsCmaFzSKhTIfv1kMxZMoAeE2cuCngz8769NITJRd6ZoNFh/IATRc4yCjk2UMuwwm5vCD0il1gahmmZLw1aXqZFNysGb8R3kJG6MB9hQ9k15EON1BoZ3s09xLtzwQA7HHt4J0+YgA6ioSgRMKc3lDvz9HzBioYHSMg5A6js0wQAAAAASUVORK5CYII=" alt="barcode">                    </td>
        </div>
        <br><br><br>
        <div>
            <div style="float: left"> ____________________________ <br> Seller's Signature</div>
            <div style="float: right"> ____________________________ <br> Buyer's Signature</div>
        </div>
        <br><br><br>
        <div>
            <div style="float: left"> ____________________________ <br> Seller's Witness</div>
            <div style="float: right"> ____________________________ <br> Buyer's Witness</div>
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
