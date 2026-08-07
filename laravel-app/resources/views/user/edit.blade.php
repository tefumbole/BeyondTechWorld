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
                                    <div class="form-group" id="sign">
                                        <label><strong>@if($lims_user_data->role_id == 12) Image @else {{trans('file.Sign')}} @endif  </strong></label>
                                        <input type="file" class="form-control" name="sign" accept="image/*" @if($lims_user_data->sign) data-current="{{ url('public/images/user/'.$lims_user_data->sign) }}" @endif>
                                        @if($lims_user_data->sign)
                                            <img src="{{url('public/images/user',$lims_user_data->sign)}}" height="50vw" style="float:none;margin-top:8px;display:block;">
                                        @else
                                            <span class="text-muted d-block mt-1">No sign found</span>
                                        @endif

                                        @unless($lims_user_data->role_id == 12)
                                        <div class="user-sign-actions">
                                            <button type="button" class="btn btn-info btn-sm" id="btn-add-signature">
                                                <i class="dripicons-pencil"></i> Add Signature
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm" id="btn-sign-pad" style="display:none;">
                                                Sign on this device
                                            </button>
                                            <form method="POST" action="{{ route('user.signature.request', $lims_user_data->id) }}" class="d-inline" id="form-sign-request" style="display:none;">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-primary btn-sm" onclick="return confirm('Send a WhatsApp signature link to {{ \App\Support\WhatsAppPhone::display($lims_user_data->phone ?: $lims_user_data->additional_phone) ?: 'this user' }}?');">
                                                    <i class="fa fa-whatsapp"></i> Request link (WhatsApp)
                                                </button>
                                            </form>
                                            @if(!empty($lims_user_data->sign_request_token) && $lims_user_data->sign_request_expires_at && $lims_user_data->sign_request_expires_at->isFuture())
                                                <div class="alert alert-light border mt-2 mb-0 p-2 small">
                                                    Pending request link:
                                                    <a href="{{ url('/user-sign/'.$lims_user_data->sign_request_token) }}" target="_blank" rel="noopener">
                                                        {{ url('/user-sign/'.$lims_user_data->sign_request_token) }}
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                        <p class="text-muted small mb-0" id="add-signature-hint" style="display:none;">
                                            Choose how to add the signature: draw it here, or WhatsApp a link so the user can sign on their phone.
                                        </p>

                                        <div class="user-sign-pad-wrap" id="user-sign-pad-wrap">
                                            <p class="small text-muted mb-2">Draw the signature below, then click Save signature.</p>
                                            <canvas id="user-sign-pad" width="500" height="140"></canvas>
                                            <form method="POST" action="{{ route('user.signature.pad', $lims_user_data->id) }}" id="user-sign-pad-form" class="mt-2">
                                                @csrf
                                                <input type="hidden" name="signature_image" id="user_signature_image">
                                                <button type="button" class="btn btn-secondary btn-sm" id="clear-user-sign-pad">Clear</button>
                                                <button type="submit" class="btn btn-primary btn-sm">Save signature</button>
                                            </form>
                                        </div>
                                        @endunless
                                    </div>
                                    <div class="form-group" id="stemp">
                                        <label><strong>@if($lims_user_data->role_id == 12) Logo @else {{trans('file.Stemp')}}@endif </strong></label>
                                        <input type="file" class="form-control" name="stemp">
                                        @if($lims_user_data->stemp)
                                            <img src="{{url('public/images/user',$lims_user_data->stemp)}}" height="50vw">
                                        @else
                                            <span>No Comment found</span>
                                        @endif
                                    </div>
                                    <div class="form-group" id="approve">
                                        <label><strong>{{trans('file.Approve')}} </strong></label>
                                        <input type="file" class="form-control" name="approve">
                                        @if($lims_user_data->approve)
                                            <img src="{{url('public/images/user',$lims_user_data->approve)}}" height="50vw">
                                        @else
                                            <span>No Approve found</span>
                                        @endif
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
        }
        else{
            $('select[name="warehouse_id"]').prop('required',false);
            $('select[name="biller_id"]').prop('required',false);
            $('#biller-id').hide();
            $('#warehouseId').hide();
            $('#sign').hide();
            $('#stemp').hide();
        }
    });

    $('#genbutton').on("click", function(){
      $.get('../genpass', function(data){
        $("input[name='password']").val(data);
      });
    });

    // Add Signature → reveal pad + WhatsApp request options
    $('#btn-add-signature').on('click', function () {
        $('#btn-sign-pad, #form-sign-request, #add-signature-hint').show();
        $('#user-sign-pad-wrap').addClass('open');
        if (window.__userSignPad) {
            // keep pad ready
        }
    });
    $('#btn-sign-pad').on('click', function () {
        $('#user-sign-pad-wrap').addClass('open');
        var canvas = document.getElementById('user-sign-pad');
        if (canvas) canvas.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('user-sign-pad');
    if (!canvas || typeof SignaturePad === 'undefined') return;
    var pad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(0,0,0,0)',
        penColor: 'rgb(11, 63, 144)'
    });
    window.__userSignPad = pad;
    var clearBtn = document.getElementById('clear-user-sign-pad');
    if (clearBtn) clearBtn.addEventListener('click', function () { pad.clear(); });
    var form = document.getElementById('user-sign-pad-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (pad.isEmpty()) {
                e.preventDefault();
                alert('Please draw a signature first.');
                return false;
            }
            document.getElementById('user_signature_image').value = pad.toDataURL('image/png');
        });
    }
})();
</script>
@endsection
