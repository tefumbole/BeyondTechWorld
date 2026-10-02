@extends('layout.main') @section('content')
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
@endif
@if(session()->has('create_message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('create_message') !!}</div>
@endif
@if(session()->has('edit_message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('edit_message') }}</div>
@endif
@if(session()->has('import_message'))
    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('import_message') !!}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first() }}</div>
@endif
<style>
    @media (min-width: 576px) {
        .modal-dialog {
            max-width: 1000px;
        }
    }
</style>
<div class="container-fluid">
    <div class="row mt-4 mb-3">
        <div class="col-md-12">
            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#deposit-modal"><i class="dripicons-plus"></i> Make a deposit</button>
        </div>
    </div>
</div>

<div id="deposit-modal" tabindex="-1" role="dialog" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Deposit to an account</h5>
                <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('deposit.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Account</label>
                        <select name="account_id" class="form-control" required>
                            <option value="">Select account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ (string) old('account_id') === (string) $account->id ? 'selected' : '' }}>{{ $account->name }} / {{ $account->account_no }} — {{ number_format((float) $account->total_balance, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="number" name="amount" class="form-control" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Payment method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="1">Cash</option>
                            <option value="2">JE Method</option>
                            <option value="3">Momo/Orange</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note" class="form-control" rows="2">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save deposit</button>
                </form>
            </div>
        </div>
    </div>
</div>
<section>
    <div class="table-responsive">
        <table id="customer-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>Account</th>
                    <th>{{trans('file.customer')}}</th>
                    <th>{{trans('file.date')}}</th>
                    <th>{{trans('file.reference')}}</th>
                    <th>{{trans('file.Amount')}}</th>
                    <th>Payment Method</th>
                    <th>{{trans('file.Status')}}</th>
                    <th>Depositor</th>
                    <th>{{trans('file.Note')}}</th>
                    <th>{{trans('file.Created By')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deposits as $key=>$deposit)
                <tr data-id="{{$deposit->id}}">
                    <td>{{$key}}</td>
                    <td>{{ $deposit->account ? $deposit->account->name : '—' }}</td>
                    <td>{{ @$deposit->customer->name }}</td>
                    <td>{{ $deposit->created_at }}</td>
                    <td>{{ $deposit->payment_reference }}</td>
                    <td>{{ $deposit->amount }}</td>
                    @if($deposit->payment_method == 1)
                        <td><span class="badge badge-info">Cash</span></td>
                    @elseif($deposit->payment_method == 2)
                        <td><span class="badge badge-success">JE Method</span></td>
                    @elseif($deposit->payment_method == 3)
                        <td><span class="badge badge-warning">Momo/Orange</span></td>
                    @else
                        <td><span class="badge badge-warning">unknown</span></td>
                    @endif
                    @if($deposit->status == 0)
                        <td><span class="badge badge-warning">Pending</span></td>
                    @else
                        <td><span class="badge badge-success">Paid</span></td>
                    @endif
                    <td>{{ optional($deposit->depositor)->name ?: 'NAN' }}</td>
                    <td>{{ $deposit->note }}</td>
                    <td>{{ optional($deposit->user)->name }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<script type="text/javascript">
    $("ul#payments").siblings('a').attr('aria-expanded','true');
    $("ul#payments").addClass("show");
    $("ul#payments #desposit-index-menu").addClass("active");
    @if($errors->any())
        $('#deposit-modal').modal('show');
    @endif

       var table = $('#customer-table').DataTable( {
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
                'targets': [0, 10]
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
                        customer_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                customer_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(customer_id.length && confirm("Are you sure want to delete?")) {
                            $.ajax({
                                type:'POST',
                                url:'customer/deletebyselection',
                                data:{
                                    customerIdArray: customer_id
                                },
                                success:function(data){
                                    alert(data);
                                }
                            });
                            dt.rows({ page: 'current', selected: true }).remove().draw(false);
                        }
                        else if(!customer_id.length)
                            alert('No customer is selected!');
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

  if(all_permission.indexOf("customers-delete") == -1)
        $('.buttons-delete').addClass('d-none');

    $("#export").on("click", function(e){
        e.preventDefault();
        var customer = [];
        $(':checkbox:checked').each(function(i){
          customer[i] = $(this).val();
        });
        $.ajax({
           type:'POST',
           url:'/exportcustomer',
           data:{
                customerArray: customer
            },
           success:function(data){
             alert('Exported to CSV file successfully! Click Ok to download file');
             window.location.href = data;
           }
        });
    });
</script>
@endsection
