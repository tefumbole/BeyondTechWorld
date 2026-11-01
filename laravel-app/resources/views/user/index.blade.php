@extends('layout.main') @section('content')
@if(session()->has('message1'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{!! session()->get('message1') !!}</div>
@endif
@if(session()->has('message2'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message2') }}</div>
@endif
@if(session()->has('message3'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message3') }}</div>
@endif
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif

<style>
    .users-shell { --u-navy: #0b3f90; --u-ink: #0f172a; --u-line: #e2e8f0; }
    .users-shell .users-hero { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .users-shell .users-hero h3 { margin: 0; font-weight: 800; letter-spacing: -.03em; color: var(--u-ink); }
    .users-shell .users-hero p { margin: 4px 0 0; color: #64748b; }
    .users-shell .users-card { background: #fff; border: 1px solid var(--u-line); border-radius: 18px; box-shadow: 0 10px 30px rgba(15, 23, 42, .05); padding: 8px 8px 4px; }
    .users-shell #user-table thead th { background: var(--u-navy); color: #fff; border: 0; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
    .users-shell #user-table td { vertical-align: middle; border-color: #eef2f7; }
    .users-shell .user-name { font-weight: 700; color: var(--u-ink); }
    .users-shell .user-field { width: 100%; min-width: 120px; min-height: 36px; border: 1px solid var(--u-line); border-radius: 10px; padding: 6px 10px; background: #fff; color: var(--u-ink); }
    .users-shell .user-field:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(11, 63, 144, .12); }
    .users-shell .user-field.is-saved { border-color: #86efac; }
    .users-shell .user-field.is-error { border-color: #dc3545; }
    .users-shell .dataTables_filter input,
    .users-shell .dataTables_length select { border: 1px solid var(--u-line); border-radius: 10px; min-height: 36px; padding: 4px 10px; }
</style>
<section class="users-shell">
    <div class="users-hero">
        <div>
            <h3>Users</h3>
            <p>Usernames, phone numbers, and roles save as you leave the field.</p>
        </div>
    </div>
    <div class="users-card table-responsive">
        <table id="user-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.name')}}</th>
                    <th>Username</th>
                    <th>{{trans('file.Phone Number')}}</th>
                    <th>{{trans('file.Email')}}</th>
                    <th>{{trans('file.Company Name')}}</th>
                    <th>{{trans('file.Additional Phone Number')}}</th>
                    <th>{{trans('file.Role')}}</th>
                    <th>{{trans('file.Status')}}</th>
                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_user_list as $key=>$user)
                <tr data-id="{{$user->id}}" @if($canEditUsers) data-edit-url="{{ route('user.edit', $user->id) }}" style="cursor:pointer;" @endif>
                    <td>{{$key}}</td>
                    <td><span class="user-name">{{ $user->name }}</span></td>
                    <td>
                        @if($canEditUsers)
                            <input type="text" class="user-field" data-field="username" value="{{ $user->username }}" placeholder="Username" autocomplete="off">
                        @else
                            {{ $user->username ?: '—' }}
                        @endif
                    </td>
                    <td>
                        @if($canEditUsers)
                            <input type="text" class="user-field" data-field="phone" value="{{ $user->phone }}" placeholder="Phone" autocomplete="off">
                        @else
                            {{ $user->phone }}
                        @endif
                    </td>
                    <td>{{ $user->email}}</td>
                    <td>{{ $user->company_name}}</td>
                    <td>
                        @if($canEditUsers)
                            <input type="text" class="user-field" data-field="additional_phone" value="{{ $user->additional_phone }}" placeholder="Additional phone" autocomplete="off">
                        @else
                            {{ $user->additional_phone }}
                        @endif
                    </td>
                    <td>
                        @if($canEditUsers)
                            <select class="user-field" data-field="role_id">
                                @foreach($assignableRoles as $roleOption)
                                    <option value="{{ $roleOption->id }}" {{ (int) $user->role_id === (int) $roleOption->id ? 'selected' : '' }}>{{ $roleOption->name }}</option>
                                @endforeach
                                @if($user->role_id && ! $assignableRoles->contains('id', $user->role_id))
                                    <option value="{{ $user->role_id }}" selected>{{ $rolesById[$user->role_id] ?? 'Role' }}</option>
                                @endif
                            </select>
                        @else
                            {{ $rolesById[$user->role_id] ?? '—' }}
                        @endif
                    </td>
                    @if($user->is_active)
                    <td><div class="badge badge-success">Active</div></td>
                    @else
                    <td><div class="badge badge-danger">Inactive</div></td>
                    @endif
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{trans('file.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                @if(in_array("users-edit", $all_permission))
                                <li>
                                	<a href="{{ route('user.edit', $user->id) }}" class="btn btn-link"><i class="dripicons-document-edit"></i> {{trans('file.edit')}}</a>
                                </li>
                                <li class="divider"></li>
                                <li>
                                    <a href="javascript:void(0)" class="btn btn-link btn-request-sig"
                                       data-url="{{ route('user.signature.request', $user->id) }}"
                                       data-type="approve"
                                       data-name="{{ $user->name }}">
                                        <i class="dripicons-user"></i> Request Approver
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0)" class="btn btn-link btn-request-sig"
                                       data-url="{{ route('user.signature.request', $user->id) }}"
                                       data-type="stemp"
                                       data-name="{{ $user->name }}">
                                        <i class="dripicons-message"></i> Request Comment
                                    </a>
                                </li>
                                <li>
                                    <a href="javascript:void(0)" class="btn btn-link btn-request-sig"
                                       data-url="{{ route('user.signature.request', $user->id) }}"
                                       data-type="sign"
                                       data-name="{{ $user->name }}">
                                        <i class="dripicons-pencil"></i> Request Signature
                                    </a>
                                </li>
                                @endif
                                @if(in_array("users-delete", $all_permission))
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['user.destroy', $user->id], 'method' => 'DELETE'] ) }}
                                <li>
                                    <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="dripicons-trash"></i> {{trans('file.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<script type="text/javascript">

    $("ul#people").siblings('a').attr('aria-expanded','true');
    $("ul#people").addClass("show");
    $("ul#people #user-list-menu").addClass("active");

    var user_id = [];
    var user_verified = <?php echo json_encode(env('USER_VERIFIED')) ?>;
    var all_permission = <?php echo json_encode($all_permission) ?>;

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

	function confirmDelete() {
	    if (confirm("Permanently delete this person from the system? If they are an intern, their login, placement, submissions and timesheets are removed. This cannot be undone.")) {
	        return true;
	    }
	    return false;
	}

    $('#user-table').DataTable( {
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
                'targets': [0, 8, 9]
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
                        user_id.length = 0;
                        $(':checkbox:checked').each(function(i){
                            if(i){
                                user_id[i-1] = $(this).closest('tr').data('id');
                            }
                        });
                        if(user_id.length && confirm("Permanently delete the selected people from the system? Interns lose their login, placement, submissions and timesheets. This cannot be undone.")) {
                            $.ajax({
                                type:'POST',
                                url:'user/deletebyselection',
                                data:{
                                    userIdArray: user_id
                                },
                                success:function(data){
                                    alert(data);
                                }
                            });
                            dt.rows({ page: 'current', selected: true }).remove().draw(false);
                        }
                        else if(!user_id.length)
                            alert('No user is selected!');
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

    if(all_permission.indexOf("users-delete") == -1)
        $('.buttons-delete').addClass('d-none');

    $('#user-table tbody').on('click', 'tr', function (e) {
        if ($(e.target).closest('td:first-child, .btn-group, a, button, input, select, label, .dropdown-menu, .checkbox, .user-field').length) {
            return;
        }
        var url = $(this).data('edit-url');
        if (url) {
            window.location = url;
        }
    });

    var inlineUrl = @json(url('user'));
    function saveUserRow($field) {
        var $row = $field.closest('tr');
        var id = $row.data('id');
        var payload = {
            username: $row.find('[data-field="username"]').val() || '',
            phone: $row.find('[data-field="phone"]').val() || '',
            additional_phone: $row.find('[data-field="additional_phone"]').val() || '',
            role_id: $row.find('[data-field="role_id"]').val() || ''
        };
        var signature = JSON.stringify(payload);
        if ($row.data('saved') === signature) return;
        $row.find('.user-field').removeClass('is-saved is-error');
        $.ajax({
            type: 'POST',
            url: inlineUrl + '/' + id + '/inline',
            data: payload,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).done(function () {
            $row.data('saved', signature);
            $field.addClass('is-saved');
        }).fail(function (xhr) {
            $field.addClass('is-error');
            var res = xhr.responseJSON || {};
            var msg = res.message;
            if (!msg && res.errors) {
                msg = Object.keys(res.errors).map(function (key) { return res.errors[key][0]; }).join(' ');
            }
            alert(msg || 'That change was not saved.');
        });
    }
    $(document).on('change', '#user-table .user-field', function () {
        saveUserRow($(this));
    });
    $(document).on('keydown', '#user-table .user-field', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $(this).blur();
        }
    });

    var requestLabels = { approve: 'Approver', stemp: 'Comment', sign: 'Signature' };
    $(document).on('click', '.btn-request-sig', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $btn = $(this);
        var type = $btn.data('type');
        var label = requestLabels[type] || type;
        var name = $btn.data('name') || 'this user';
        if (!confirm('Send a WhatsApp link so ' + name + ' can draw their ' + label + '?')) {
            return;
        }
        $btn.addClass('disabled').css('opacity', 0.6);
        $.ajax({
            type: 'POST',
            url: $btn.data('url'),
            data: { type: type },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).done(function (res) {
            var msg = (res && res.message) ? res.message : (label + ' request sent.');
            if (res && res.link) {
                var copy = prompt(msg + '\n\nLink (copy if WhatsApp failed):', res.link);
                if (copy === null && res.link) {
                    window.open(res.link, '_blank');
                }
            } else {
                alert(msg);
            }
        }).fail(function (xhr) {
            var res = xhr.responseJSON || {};
            var msg = res.message || ('Could not send ' + label + ' request.');
            if (res.link) {
                prompt(msg + '\n\nOpen / copy this link:', res.link);
            } else {
                alert(msg);
            }
        }).always(function () {
            $btn.removeClass('disabled').css('opacity', 1);
        });
    });
</script>

{{-- Result toast area --}}
<div id="sig-request-toast" class="alert alert-info" style="display:none;position:fixed;bottom:20px;right:20px;z-index:9999;max-width:420px;"></div>
@endsection
