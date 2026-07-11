@extends('layout.main') @section('content')
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <a href="{{route('asset.transfer.list')}}" class="btn btn-info"><i class="dripicons-list"></i> Transfer Assets List </a>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>Transfer Asset</h4>
                        </div>
                        <div class="card-body">
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            {!! Form::open(['route' => ['asset.transfer.update'], 'method' => 'post', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-12">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Asset Name</th>
                                                <th>Transfer To</th>
                                                <th>Price</th>
                                                <th>Useful Life in Years</th>
                                                <th>Transfer Date</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>
                                                    <input type="hidden" name="id" value="{{ $data->id }}">
                                                    {{ $data->assets->name }} - {{ $data->assets->serial_no }}
                                                </td>
                                                <td>
                                                    <select name="to" class="form-control selectpicker" data-live-search="true" required>
                                                        <option value="">  -- choose -- </option>
                                                        @foreach($department as $item)
                                                            <option value="{{ $item->id }}" {{ $item->id == $data->to ? 'selected' : ''}}> {{ $item->name }} - {{ $item->code }}  </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td><input type="number" name="price" class="form-control price-0" required step="any" value="{{ $data->price }}"></td>
                                                <td><input type="number" name="life_span" class="form-control life_span-0" step="any" placeholder="Useful Life" value="{{ $data->life_span }}"></td>
                                                <td><input type="date" name="date" class="form-control" required value="{{ $data->date }}"></td>
                                                <td><input type="text" name="remarks" class="form-control" placeholder="Remark" value="{{ $data->remarks }}"></td>

                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Prepared By</label>
                                        <input type="text" name="prepared_by" class="form-control" placeholder="Prepared by"  value="{{ $data->prepared_by }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Checked by</label>
                                        <input type="text" name="checked_by" class="form-control" placeholder="Checked by"  value="{{ $data->checked_by }}">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group mt-4">
                                        <input type="submit" value="Transfer" class="btn btn-primary" >
                                    </div>
                                </div>
                            </div>
                            {!! Form::close() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">

        // new row
        {{--let lineNo = 1;--}}
        {{--$(document).ready(function () {--}}
        {{--    $(".add-row").click(function () {--}}
        {{--        var newRow = $("<tr>");--}}
        {{--        var cols = '';--}}

        {{--        cols += '<td><input type="checkbox" name="record"></td>';--}}
        {{--        cols += '<td><select name="asset_id[]" class="form-control select-asset selectpicker" data-live-search="true" required onchange="selectAsset(this, '+lineNo+')"> <option value="">  -- choose -- </option> @foreach($assets as $item) <option value="{{ $item->id }}"> {{ $item->name }} - {{ $item->serial_no }} - {{$item->department->code }} </option> @endforeach </select></td>';--}}
        {{--        cols += '<td><select name="to[]" class="form-control selectpicker" data-live-search="true" required> <option value="">  -- choose -- </option> @foreach($department as $item) <option value="{{ $item->id }}"> {{ $item->name }} - {{ $item->code }}  </option> @endforeach </select></td>';--}}
        {{--        cols += '<td><input type="number" name="price[]" class="form-control price-'+lineNo+'" step="any" value="0"></td>';--}}
        {{--        cols += '<td><input type="number" name="life_span[]" class="form-control life_span-'+lineNo+'" step="any" placeholder="Useful Life"></td>';--}}
        {{--        cols += '<td><input type="date" name="date[]" class="form-control" required></td>';--}}
        {{--        cols += '<td><input type="text" name="remarks[]" class="form-control" placeholder="Remark"></td>';--}}

        {{--        newRow.append(cols);--}}
        {{--        $("table tbody").prepend(newRow);--}}
        {{--        lineNo++;--}}

        {{--        $('.selectpicker').selectpicker({--}}
        {{--            style: 'btn-link',--}}
        {{--        });--}}
        {{--    });--}}
        {{--});--}}

        // Find and remove selected table rows
        $(".remove").click(function(){
            $("table tbody").find('input[name="record"]').each(function(){
                if($(this).is(":checked")){
                    $(this).parents("tr").remove();
                }
            });
        });

        // ajax
        function selectAsset(selectObject, id) {
            var value = selectObject.value;
            var url = "{{ route('asset.transfer.search', ":id") }}";
            url = url.replace(':id', value);
            $.ajax({
                type:'get',
                url: url,
                success: function(data) {
                    $('.price-'+id).val(data['book_value']);
                    $('.life_span-'+id).val(data['available_in_year']);
                }
            });
        }
        {{--$('.select-asset').on('change', function(){--}}
        {{--    var value = $(this).val();--}}
        {{--    var url = "{{ route('asset.transfer.search', ":id") }}";--}}
        {{--    url = url.replace(':id', value);--}}
        {{--    $.ajax({--}}
        {{--        type:'get',--}}
        {{--        url: url,--}}
        {{--        success: function(data) {--}}
        {{--            console.log(data);--}}
        {{--            $('.price').val(data['book_value']);--}}
        {{--        }--}}
        {{--    });--}}
        {{--});--}}


        // menu open
        $("ul#assets").siblings('a').attr('aria-expanded','true');
        $("ul#assets").addClass("show");
        $("ul#assets #assets-transfer-menu").addClass("active");
    </script>
@endsection
