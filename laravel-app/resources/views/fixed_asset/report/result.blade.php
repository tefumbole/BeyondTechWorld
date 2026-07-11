<div class="table-responsive">
    @if(isset($data))
        <table id="product-report-table" class="table table-hover" style="width: 100%">
            <thead>
            <tr>
                <th>Barcode</th>
                <th>{{trans('file.name')}}</th>
                <th>Depatrtment / No</th>
                <th>{{trans('file.Category')}}</th>
                <th>{{trans('file.Donor')}}</th>
                <th>{{trans('file.Region')}}</th>
                <th>{{trans('file.Station')}}</th>
                <th>{{trans('file.Physical Location')}}</th>
                <th>Price</th>
                <th>Useful Life</th>
                <th>Scrap Value</th>
                <th>Depreciation Value</th>
                <th>Consume (d)</th>
                <th>Book_Value</th>
                <th>{{trans('file.Purchase Date')}}</th>
                <th>Service Date</th>
            </tr>
            </thead>
            <tbody>
            @php
                $setting = \App\GeneralSetting::select('currency')->latest()->first();
                $curency = '';
                if($setting) {
                    $curency = \App\Currency::where('id', $setting->currency)->select('code')->first()->code;
                }
                $initial_value = 0;
                $current_value = 0;
                $depreciation_value = 0;
                $count = 0;
            @endphp
            @foreach($data as $key=>$item)
                <tr data-id="{{$item->id}}">
                    <td>{{$item->serial_no}}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ @$item->department->name}} / {{ @$item->department->code}}</td>
                    <td>{{ @$item->category->name}}</td>
                    <td>{{ @$item->donor->name}}</td>
                    <td>{{ @$item->region->name}}</td>
                    <td>{{ @$item->station->name}}</td>
                    <td>{{ $item->physical_location}}</td>
                    @php
                        $depreciation = 0;
                        $book_value = 0;
                        $d1 = new DateTime($item->service_date);
                        if ($item->is_active == 3) {
                            $d2 = new DateTime($item->transfer_at);
                        } else {
                            $d2 = new DateTime();
                        }
                        $interval = $d1->diff($d2);
                        $consume = $interval->days;
                        $d3 = new DateTime($start_date);
                        $d4 = new DateTime($end_date);
                        $date_range = $d3->diff($d4)->days;
                        $total_life_span = $item->life_span * 365;
                        if($total_life_span != 0) {
                            if ($consume > $date_range) {
                                $depreciation = ($date_range/$total_life_span) * $item->price;
                            } else {
                                $depreciation = ($consume/$total_life_span) * $item->price;
                            }
                            $available = $total_life_span - $consume;
                            $book_value = ($available/$total_life_span) * $item->price;
                        }

                        if($item->asset_type == 'land') {
                            $apprication_increase_percentage = ($consume / 365) * $item->appreciation;
                            $apprication_increase_value = ($apprication_increase_percentage/100) * $item->price;
                            $book_value = $apprication_increase_value + $item->price;
                            $depreciation = -$apprication_increase_value ;
                        }

                        $initial_value += $item->price;
                        $depreciation_value += $depreciation;
                        if($item->price != 0) {$current_value += $book_value;}
                        $count++;
                    @endphp
                    <td>{{ number_format($item->price, 2) }}</td>
                    <td>{{ $item->life_span}} Y</td>
                    <td>{{ $item->scrap}}</td>
                    <td>{{ number_format($depreciation, 2) }}</td>
                    <td>{{ round($consume, 2) }} d</td>
                    <td>{{ number_format($book_value, 2) }}</td>
                    <td>{{ $item->purchase_date}}</td>
                    <td>{{ $item->service_date}}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
            <tr style="padding-top:10px ">
                <td>Prepared By:</td>
                <td>_____________</td>
                <td></td>
                <td>Total: </td>
                <td>{{ $count }}</td>
                <td></td>
                <td></td>
                <td>Initial:</td>
                <td>{{ number_format($initial_value, 2) }} {{$curency}}</td>
                <td></td>
                <td>Deprication:</td>
                <td>{{ number_format($depreciation_value, 2) }} {{$curency}}</td>
                <td>Book Value:</td>
                <td>{{ number_format($current_value, 2) }} {{$curency}}</td>
                <td>Checked By:</td>
                <td>____________</td>
            </tr>
            </tfoot>
        </table>
    @endif
</div>
