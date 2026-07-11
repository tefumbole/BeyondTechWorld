@extends('layout.main')
@section('content')
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

    <section>
        <div class="container-fluid">
            <a href="#" data-toggle="modal" data-target="#createModal" class="btn btn-info"><i class="dripicons-plus"></i> {{trans('file.Add Customer Group')}}</a>
            <a href="#" data-toggle="modal" data-target="#importcustomer_group" class="btn btn-primary"><i class="dripicons-copy"></i> {{trans('file.Import Customer Group')}}</a>
        </div>
        <div class="table-responsive">
            <table id="customer-grp-table" class="table">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.name')}}</th>
                    <th>{{trans('file.Credit Limit')}}</th>
                    <th>{{trans('file.Percentage')}}</th>
                    {{--                    <th>Awaiting Payment</th>--}}
                    {{--                    <th>Patient Deposit</th>--}}
                    <th>Group Deposit</th>
                    <th>{{trans('file.Owing')}}</th>
                    {{--                    <th>Total Payable</th>--}}
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lims_customer_group_all as $key=>$customer_group)
                    <tr data-id="{{$customer_group->id}}">
                        <td>{{$key}}</td>
                        <td>{{ $customer_group->name }}</td>
                        <td>{{ $customer_group->credit_limit ?? 'N/A' }}</td>
                        <td>{{ $customer_group->percentage}}</td>
                        {{--                    <td>{{ number_format($customer_group->Remaining, 2) }}</td>--}}
                        {{--                    <td>{{ number_format($customer_group->balance, 2) }}</td>--}}
                        <td> @if($customer_group->deposit > 0) {{ number_format($customer_group->deposit, 2) }} @else {{ number_format(0, 2) }} @endif</td>
                        <td> @if($customer_group->deposit < 0) {{ number_format(abs($customer_group->deposit), 2) }} @else {{ number_format(0, 2) }} @endif</td>
                        {{--                    <td>@if($customer_group->deposit - $customer_group->remaining < 0 ) {{ number_format(abs($customer_group->deposit - $customer_group->remaining), 2) }} @else 0 @endif</td>--}}

                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                    <span class="caret"></span>
                                    <span class="sr-only">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                    <li>
                                        <a type="button" href="{{ route('customer_group.customers', ['id' => $customer_group->id]) }}" class="open-EditCustomerGroupDialog btn btn-link"><i class="fa fa-user-md"></i> Customers</a>
                                    </li>
                                    <li>
                                        <button type="button" data-id="{{$customer_group->id}}" class="deposit btn btn-link" data-toggle="modal" data-target="#depositModal" ><i class="dripicons-plus"></i> {{trans('file.Add Payment')}}</button>
                                    </li>
                                    <li>
                                        <a href="{{ route('customer_group.deposits', ['id' => $customer_group->id]) }}" class="getDeposit btn btn-link"><i class="fa fa-money"></i> View Deposits</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('customer_group.payments', ['id' => $customer_group->id]) }}" class="getDeposit btn btn-link"><i class="fa fa-money"></i> View Payments</a>
                                    </li>
                                    <li>
                                        <button type="button" data-id="{{$customer_group->id}}" class="open-EditCustomerGroupDialog btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}
                                        </button>
                                    </li>
                                    <li class="divider"></li>
                                    {{ Form::open(['route' => ['customer_group.destroy', $customer_group->id], 'method' => 'DELETE'] ) }}
                                    <li>
                                        <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                    </li>
                                    {{ Form::close() }}
                                </ul>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div id="depositModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'customer_group.addDeposit', 'method' => 'post']) !!}
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Payment')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <input type="hidden" name="customer_group_id">
                                <label>{{trans('file.Amount')}} *</label>
                                <input type="number" name="amount" step="any" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Payment Method')}} <strong>*</strong> </label>
                                <select class="form-control selectpicker" name="payment_method" onchange='saveValue(this);'>
                                    <option value="1">Cash</option>
                                    <option value="3" disabled>Momo/Orange</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Depositor </label>
                                <select class="form-control selectpicker" name="depositor_id">
                                    <option value=""> --Choose Any --</option>
                                    @foreach($depositors as $depositor)
                                        <option value="{{ $depositor->id }}">{{ $depositor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.MTN Momo Number')}}</label>
                                <input type="number" name="mtn_number" step="any" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Note')}}</label>
                                <textarea name="note" rows="4" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>
                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary" id="submit-button">
                </div>
                {!! Form::close() !!}
            </div>
        </div>
    </div>

    <div id="createModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'customer_group.store', 'method' => 'post']) !!}
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Customer Group')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    <form>
                        <div class="form-group">
                            <label>{{trans('file.name')}} *</label>
                            <input type="text" name="name" required="required" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Percentage')}}(%) *</label>
                            <input type="text" name="percentage" required="required" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Credit Limit')}}</label>
                            <input type="number" name="credit_limit"  class="form-control">
                        </div>
                        <div class="form-group">
                            <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                        </div>
                    </form>
                </div>

                {{ Form::close() }}
            </div>
        </div>
    </div>

    <div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => ['customer_group.update',1], 'method' => 'put']) !!}
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Update Customer Group')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    <div class="form-group">
                        <input type="hidden" name="customer_group_id">
                        <label>{{trans('file.name')}} *</label>
                        <input type="text" name="name" required="required" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>{{trans('file.Percentage')}}(%) *</label>
                        <input type="text" name="percentage" required="required" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>{{trans('file.Credit Limit')}}</label>
                        <input type="number" name="credit_limit"  class="form-control">
                    </div>
                    <div class="form-group">
                        <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                    </div>
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>

    <div id="importcustomer_group" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                {!! Form::open(['route' => 'customer_group.import', 'method' => 'post', 'files' => true]) !!}
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title"> {{trans('file.Import Customer Group')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                    <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    <p>{{trans('file.The correct column order is')}} (name*, percentage*) {{trans('file.and you must follow this')}}.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>{{trans('file.Upload CSV File')}} *</label>
                                {{Form::file('file', array('class' => 'form-control','required'))}}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label> {{trans('file.Sample File')}}</label>
                                <a href="public/sample_file/sample_customer_group.csv" class="btn btn-info btn-block btn-md"><i class="dripicons-download"></i>  {{trans('file.Download')}}</a>
                            </div>
                        </div>
                    </div>

                    <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                </div>
                {{ Form::close() }}
            </div>
        </div>
    </div>

    <script type="text/javascript">
        $("ul#setting").siblings('a').attr('aria-expanded','true');
        $("ul#setting").addClass("show");
        $("ul#setting #customer-group-menu").addClass("active");

        $(".deposit").on("click", function() {
            var id = $(this).data('id').toString();
            $("#depositModal input[name='customer_group_id']").val(id);
        });

        var customer_group_id = [];
        var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        function confirmDelete() {
            if (confirm("Are you sure want to delete?")) {
                return true;
            }
            return false;
        }
        $(document).ready(function() {

            $(document).on('click', '.open-EditCustomerGroupDialog', function() {
                var url = "customer_group/"
                var id = $(this).data('id').toString();
                url = url.concat(id).concat("/edit");

                $.get(url, function(data) {
                    $("input[name='name']").val(data['name']);
                    $("input[name='percentage']").val(data['percentage']);
                    $("input[name='customer_group_id']").val(data['id']);
                    $("input[name='credit_limit']").val(data['credit_limit']);
                });
            });
        });

        $('#customer-grp-table').DataTable( {
            "order": [],
            'language': {
                'lengthMenu': '_MENU_ {{trans("file.records per page")}}',
                "info":      '<small>{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
                "search":  '{{trans("file.Search")}}',
                'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
                }
            },
            'columnDefs': [
                {
                    "orderable": false,
                    'targets': [0, 3]
                },
                {
                    'render': function(data, type, row, meta){
                        if(type === 'display'){
                            data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                        }

                        return data;
                    },
                    'checkboxes': {
                        'selectRow': true,
                        'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                    },
                    'targets': [0]
                }
            ],
            'select': { style: 'multi',  selector: 'td:first-child'},
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
            dom: '<"row"lfB>rtip',
            buttons: [
                {
                    extend: 'pdf',
                    text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    text: '<i title="delete" class="dripicons-cross"></i>',
                    className: 'buttons-delete',
                    action: function ( e, dt, node, config ) {
                        if(user_verified == '1') {
                            customer_group_id.length = 0;
                            $(':checkbox:checked').each(function(i){
                                if(i){
                                    customer_group_id[i-1] = $(this).closest('tr').data('id');
                                }
                            });
                            if(customer_group_id.length && confirm("Are you sure want to delete?")) {
                                $.ajax({
                                    type:'POST',
                                    url:'customer_group/deletebyselection',
                                    data:{
                                        customer_groupIdArray: customer_group_id
                                    },
                                    success:function(data){
                                        alert(data);
                                    }
                                });
                                dt.rows({ page: 'current', selected: true }).remove().draw(false);
                            }
                            else if(!customer_group_id.length)
                                alert('No customer group is selected!');
                        }
                        else
                            alert('This feature is disable for demo!');
                    }
                },
                {
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
        } );

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $( "#select_all" ).on( "change", function() {
            if ($(this).is(':checked')) {
                $("tbody input[type='checkbox']").prop('checked', true);
            }
            else {
                $("tbody input[type='checkbox']").prop('checked', false);
            }
        });

        $("#export").on("click", function(e){
            e.preventDefault();
            var customer_group = [];
            $(':checkbox:checked').each(function(i){
                customer_group[i] = $(this).val();
            });
            $.ajax({
                type:'POST',
                url:'/exportcustomer_group',
                data:{
                    customer_groupArray: customer_group
                },
                success:function(data){
                    alert('Exported to CSV file successfully! Click Ok to download file');
                    window.location.href = data;
                }
            });
        });
    </script>

@endsection
