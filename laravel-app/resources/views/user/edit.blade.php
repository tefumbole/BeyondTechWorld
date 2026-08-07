@extends('layout.main') @section('content')

@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
@if(session()->has('message2'))
  <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message2') }}</div>
@endif
@if(session()->has('signature_request_link'))
  <div class="alert alert-info alert-dismissible">
    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <strong>Signature link</strong> (valid 3 days):
    <div class="mt-2 d-flex flex-wrap align-items-center" style="gap:8px;">
      <input type="text" class="form-control" id="signature-request-link-input" readonly value="{{ session('signature_request_link') }}" style="max-width:520px;">
      <button type="button" class="btn btn-sm btn-primary" id="copy-signature-link">Copy link</button>
      <a class="btn btn-sm btn-outline-primary" href="{{ session('signature_request_link') }}" target="_blank" rel="noopener">Open link</a>
    </div>
  </div>
  <script>
    (function () {
      var btn = document.getElementById('copy-signature-link');
      var input = document.getElementById('signature-request-link-input');
      if (!btn || !input) return;
      btn.addEventListener('click', function () {
        input.select();
        input.setSelectionRange(0, 99999);
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(input.value);
        } else {
          document.execCommand('copy');
        }
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = 'Copy link'; }, 1500);
      });
    })();
  </script>
@endif
<style>
    img{
        float: right;
    }
    .user-sign-actions { display:flex; flex-wrap:wrap; gap:8px; margin:8px 0 10px; }
    .user-sign-pad-wrap {
        display:none; margin-top:10px; border:2px dashed #0b3f90; border-radius:12px;
        background:#f8fbff; padding:12px; max-width:520px;
    }
    .user-sign-pad-wrap.open { display:block; }
    #user-sign-pad { width:100%; max-width:500px; height:140px; background:transparent; border-radius:8px; touch-action:none; }
</style>
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Update User')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => ['user.update', $lims_user_data->id], 'method' => 'put', 'files' => true]) !!}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>{{trans('file.UserName')}} *</strong> </label>
                                        <input type="text" name="name" required class="form-control" value="{{$lims_user_data->name}}">
                                        @if($errors->has('name'))
                                       <span>
                                           <strong>{{ $errors->first('name') }}</strong>
                                        </span>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <label><strong>{{trans('file.Change Password')}}</strong> </label>
                                        <div class="input-group">
                                            <input type="password" name="password" class="form-control">
                                            <div class="input-group-append">
                                                <button id="genbutton" type="button" class="btn btn-default">{{trans('file.Generate')}}</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group mt-3">
                                        <label><strong>{{trans('file.Email')}} *</strong></label>
                                        <input type="email" name="email" placeholder="example@example.com" required class="form-control" value="{{$lims_user_data->email}}">
                                        @if($errors->has('email'))
                                       <span>
                                           <strong>{{ $errors->first('email') }}</strong>
                                        </span>
                                        @endif
                                    </div>
                                    <div class="form-group mt-3">
                                        <label><strong>{{trans('file.Phone Number')}} *</strong></label>
                                        <input type="text" name="phone" required class="form-control" value="{{$lims_user_data->phone}}">
                                    </div>
                                    <div class="form-group mt-3">
                                        <label><strong>{{trans('file.Additional Phone Number')}}</strong></label>
                                        <input type="text" name="additional_phone" class="form-control" value="{{$lims_user_data->additional_phone}}">
                                    </div>
                                    <div class="form-group">
                                        @if($lims_user_data->is_active)
                                        <input class="mt-2" type="checkbox" name="is_active" value="1" checked>
                                        @else
                                        <input class="mt-2" type="checkbox" name="is_active" value="1">
                                        @endif
                                        <label class="mt-2"><strong>{{trans('file.Active')}}</strong></label>
                                    </div>
                                    <div class="form-group">
                                        <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><strong>{{trans('file.Company Name')}}</strong></label>
                                        <input type="text" name="company_name" class="form-control" value="{{$lims_user_data->company_name}}">
                                    </div>
                                    <div class="form-group">
                                        <label><strong>{{trans('file.Role')}} *</strong></label>
                                        <input type="hidden" name="role_id_hidden" value="{{$lims_user_data->role_id}}">
                                        <select name="role_id" required class="selectpicker form-control" data-live-search="true"   title="Select Role...">
                                          @foreach($lims_role_list as $role)
                                              <option value="{{$role->id}}">{{$role->name}}</option>
                                          @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group" id="biller-id">
                                        <label><strong>{{trans('file.Biller')}} *</strong></label>
                                        <input type="hidden" name="biller_id_hidden" value="{{$lims_user_data->biller_id}}">
                                        <select name="biller_id" class="selectpicker form-control" data-live-search="true"   title="Select Biller...">
                                          @foreach($lims_biller_list as $biller)
                                              <option value="{{$biller->id}}">{{$biller->name}}</option>
                                          @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group" id="warehouseId">
                                        <label><strong>{{trans('file.Warehouse')}} *</strong></label>
                                        <input type="hidden" name="warehouse_id_hidden" value="{{$lims_user_data->warehouse_id}}">
                                        <select name="warehouse_id" class="selectpicker form-control" data-live-search="true"   title="Select Warehouse...">
                                          @foreach($lims_warehouse_list as $warehouse)
                                              <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                          @endforeach
                                        </select>
                                    </div>
                                    <div id="sign">
                                        @if($lims_user_data->role_id == 12)
                                            <div class="form-group">
                                                <label><strong>Image</strong></label>
                                                <input type="file" class="form-control" name="sign" accept="image/*">
                                                @if($lims_user_data->sign)
                                                    <img src="{{url('public/images/user',$lims_user_data->sign)}}" height="50vw" style="float:none;margin-top:8px;display:block;">
                                                @endif
                                            </div>
                                        @else
                                            @include('user.partials.signature_field', [
                                                'type' => 'sign',
                                                'label' => trans('file.Sign'),
                                                'user' => $lims_user_data,
                                                'fileField' => $lims_user_data->sign,
                                                'inputName' => 'sign',
                                            ])
                                        @endif
                                    </div>
                                    <div id="stemp">
                                        @if($lims_user_data->role_id == 12)
                                            <div class="form-group">
                                                <label><strong>Logo</strong></label>
                                                <input type="file" class="form-control" name="stemp" accept="image/*">
                                                @if($lims_user_data->stemp)
                                                    <img src="{{url('public/images/user',$lims_user_data->stemp)}}" height="50vw" style="float:none;margin-top:8px;display:block;">
                                                @endif
                                            </div>
                                        @else
                                            @include('user.partials.signature_field', [
                                                'type' => 'stemp',
                                                'label' => 'Comment',
                                                'user' => $lims_user_data,
                                                'fileField' => $lims_user_data->stemp,
                                                'inputName' => 'stemp',
                                            ])
                                        @endif
                                    </div>
                                    <div id="approve">
                                        @include('user.partials.signature_field', [
                                            'type' => 'approve',
                                            'label' => trans('file.Approve'),
                                            'user' => $lims_user_data,
                                            'fileField' => $lims_user_data->approve,
                                            'inputName' => 'approve',
                                        ])
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
    $("ul#people").siblings('a').attr('aria-expanded','true');
    $("ul#people").addClass("show");
    $('#biller-id').hide();
    $('#warehouseId').hide();


    $('select[name=role_id]').val($("input[name='role_id_hidden']").val());
    if($('select[name=role_id]').val() > 2 && $('select[name=role_id]').val() < 8){
        $('#warehouseId').show();
        $('select[name=warehouse_id]').val($("input[name='warehouse_id_hidden']").val());
        $('#biller-id').show();
        $('select[name=biller_id]').val($("input[name='biller_id_hidden']").val());
    } else if($('select[name=role_id]').val() > 9 || $('select[name=role_id]').val() > 1) {
        $('select[name="warehouse_id"]').prop('required',false);
        $('select[name="biller_id"]').prop('required',false);
        $('#biller-id').hide();
        $('#warehouseId').hide();
        $('#sign').show();
        $('#stemp').show();
    }
    $('.selectpicker').selectpicker('refresh');

    $('select[name="role_id"]').on('change', function() {
        if($(this).val() > 2 && $('select[name=role_id]').val() < 8){
            $('select[name="warehouse_id"]').prop('required',true);
            $('select[name="biller_id"]').prop('required',true);
            $('#biller-id').show();
            $('#warehouseId').show();
            $('#sign').hide(300);
            $('#stemp').hide(300);
        }
        else if($(this).val() > 8 || $(this).val() == 1) {
            $('select[name="warehouse_id"]').prop('required',false);
            $('select[name="biller_id"]').prop('required',false);
            $('#biller-id').hide();
            $('#warehouseId').hide();
            $('#sign').show(300);
            $('#stemp').show(300);
            $('#approve').show(300);
        }
        else{
            $('select[name="warehouse_id"]').prop('required',false);
            $('select[name="biller_id"]').prop('required',false);
            $('#biller-id').hide();
            $('#warehouseId').hide();
            $('#sign').hide();
            $('#stemp').hide();
            $('#approve').hide();
        }
    });

    $('#genbutton').on("click", function(){
      $.get('../genpass', function(data){
        $("input[name='password']").val(data);
      });
    });

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    if (typeof SignaturePad === 'undefined') return;
    var pads = {};

    function getPad(type) {
        if (pads[type]) return pads[type];
        var canvas = document.querySelector('.sig-canvas[data-type="'+type+'"]');
        if (!canvas) return null;
        pads[type] = new SignaturePad(canvas, {
            backgroundColor: 'rgba(0,0,0,0)',
            penColor: 'rgb(11, 63, 144)'
        });
        return pads[type];
    }

    $(document).on('click', '.btn-sig-add', function () {
        var type = $(this).data('type');
        $('.btn-sig-pad[data-type="'+type+'"], .btn-sig-whatsapp[data-type="'+type+'"], .sig-hint[data-type="'+type+'"]').show();
        $('.sig-pad-wrap[data-type="'+type+'"]').addClass('open');
        getPad(type);
    });

    $(document).on('click', '.btn-sig-pad', function () {
        var type = $(this).data('type');
        var wrap = document.querySelector('.sig-pad-wrap[data-type="'+type+'"]');
        if (wrap) {
            $(wrap).addClass('open');
            wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        getPad(type);
    });

    $(document).on('click', '.btn-sig-clear', function () {
        var pad = getPad($(this).data('type'));
        if (pad) pad.clear();
    });

    $(document).on('click', '.btn-sig-whatsapp', function () {
        var btn = $(this);
        var type = btn.data('type');
        var phone = btn.data('phone') || 'this user';
        var url = btn.data('url');
        if (!confirm('Send a WhatsApp request link to ' + phone + '?')) return;
        btn.prop('disabled', true).text('Sending…');
        $.ajax({
            method: 'POST',
            url: url,
            data: { _token: csrfToken(), type: type },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).done(function (res) {
            var link = (res && res.link) ? res.link : '';
            var msg = (res && res.message) ? res.message : 'Request processed.';
            var html = '<div class="alert ' + (res && res.success ? 'alert-success' : 'alert-warning') + ' mb-0"><div>' + msg + '</div>';
            if (link) {
                html += '<div class="mt-2 d-flex flex-wrap align-items-center" style="gap:8px;">'
                    + '<input type="text" class="form-control form-control-sm" readonly value="' + link + '" style="max-width:420px;">'
                    + '<a class="btn btn-sm btn-primary" href="' + link + '" target="_blank" rel="noopener">Open link</a></div>';
            }
            html += '</div>';
            $('.sig-request-result[data-type="'+type+'"]').html(html).show();
        }).fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Could not send WhatsApp request.';
            var link = (xhr.responseJSON && xhr.responseJSON.link) ? xhr.responseJSON.link : '';
            var html = '<div class="alert alert-danger mb-0"><div>'+msg+'</div>';
            if (link) {
                html += '<div class="mt-2"><a href="'+link+'" target="_blank" rel="noopener">'+link+'</a></div>';
            }
            html += '</div>';
            $('.sig-request-result[data-type="'+type+'"]').html(html).show();
        }).always(function () {
            btn.prop('disabled', false).html('<i class="fa fa-whatsapp"></i> Request link (WhatsApp)');
        });
    });

    $(document).on('click', '.btn-sig-delete', function () {
        var btn = $(this);
        var type = btn.data('type');
        if (!confirm('Delete this image?')) return;
        btn.prop('disabled', true);
        $.ajax({
            method: 'POST',
            url: btn.data('url'),
            data: { _token: csrfToken(), type: type },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).done(function () {
            window.location.reload();
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Delete failed.');
            btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.btn-sig-save', function () {
        var btn = this;
        var type = btn.getAttribute('data-type');
        var pad = getPad(type);
        var status = document.querySelector('.sig-pad-status[data-type="'+type+'"]');
        if (!pad || pad.isEmpty()) {
            alert('Please draw first.');
            return;
        }
        btn.disabled = true;
        if (status) { status.textContent = 'Saving…'; status.style.color = '#334155'; }
        var body = new FormData();
        body.append('_token', csrfToken());
        body.append('type', type);
        body.append('signature_image', pad.toDataURL('image/png'));
        fetch(btn.getAttribute('data-url'), {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
            if (status) {
                status.textContent = (res.j && res.j.message) ? res.j.message : (res.ok ? 'Saved.' : 'Save failed.');
                status.style.color = res.ok ? '#157347' : '#b02a37';
            }
            if (res.ok) setTimeout(function () { window.location.reload(); }, 700);
        }).catch(function () {
            if (status) { status.textContent = 'Save failed.'; status.style.color = '#b02a37'; }
        }).finally(function () { btn.disabled = false; });
    });
})();
</script>
@endsection
