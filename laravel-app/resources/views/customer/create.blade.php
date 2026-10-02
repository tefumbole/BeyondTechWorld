@extends('layout.main') @section('content')
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<section class="forms customer-modern">
    <style>
        .customer-modern .card { border: 0; border-radius: 16px; box-shadow: 0 10px 30px rgba(15, 35, 80, 0.08); overflow: hidden; }
        .customer-modern .card-header { background: #fff; border-bottom: 0; padding: 22px 28px 0; }
        .customer-modern .card-header h4 { font-weight: 700; color: #1b2a4a; margin: 0; }
        .customer-modern .card-body { padding: 8px 28px 28px; }
        .customer-modern .hint { color: #667085; margin: 6px 0 18px; }
        .customer-modern .customer-section { background: #f8fafc; border: 1px solid #e4e7ec; border-radius: 14px; padding: 16px 16px 4px; margin-bottom: 16px; }
        .customer-modern .customer-section h5 { font-size: 12px; letter-spacing: .06em; text-transform: uppercase; color: #667085; font-weight: 700; margin: 0 0 14px; }
        .customer-modern label { font-size: 13px; font-weight: 600; color: #24324a; margin-bottom: 6px; }
        .customer-modern .form-control,
        .customer-modern .bootstrap-select > .dropdown-toggle {
            border: 1px solid #d0d5dd !important;
            border-radius: 10px !important;
            background: #fff !important;
            min-height: 44px;
            box-shadow: none;
            color: #1f2937;
        }
        .customer-modern .bootstrap-select.form-control { border: 0 !important; background: transparent !important; padding: 0; min-height: 0; }
        .customer-modern .form-control:focus,
        .customer-modern .bootstrap-select > .dropdown-toggle:focus { border-color: #1d4ed8 !important; box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.16); }
        .customer-modern .btn-primary { border-radius: 10px; padding: 10px 22px; background: #1d4ed8; border-color: #1d4ed8; font-weight: 600; }
        .customer-modern .user-toggle { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #d0d5dd; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; }
        .customer-modern .user-toggle label { margin: 0; }
    </style>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Add Customer')}}</h4>
                    </div>
                    <div class="card-body">
                        <p class="hint"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'customer.store', 'method' => 'post', 'files' => true]) !!}
                        <div class="customer-section">
                            <h5>Contact</h5>
                            <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Phone Number')}} *</label>
                                    <input type="text" name="phone_number" required class="form-control js-phone-holder" value="237" autofocus>
                                    @if($errors->has('phone_number'))
                                   <span>
                                       <strong>{{ $errors->first('phone_number') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.name')}} *</label>
                                    <input type="text" id="name" name="customer_name" required class="form-control" onkeyup='saveValue(this);'>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Customer Group')}} *</label>
                                    <select required class="form-control selectpicker" id="customer-group-id" name="customer_group_id" onchange='saveValue(this);'>
                                        @foreach($lims_customer_group_all as $customer_group)
                                            <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Email')}}</label>
                                    <input type="email" name="email" placeholder="example@example.com" class="form-control">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="customer-section">
                            <h5>Company</h5>
                            <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Company Name')}}</label>
                                    <input type="text" name="company_name" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Tax Number')}}</label>
                                    <input type="text" name="tax_no" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Credit Limit')}}</label>
                                    <input type="text" name="credit_limit" class="form-control">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="customer-section">
                            <h5>Address</h5>
                            <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Address')}}</label>
                                    <input type="text" name="address" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.City')}}</label>
                                    <input type="text" name="city" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.State')}}</label>
                                    <input type="text" name="state" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Postal Code')}}</label>
                                    <input type="text" name="postal_code" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Country')}}</label>
                                    <input type="text" name="country" class="form-control">
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="user-toggle">
                            <input type="checkbox" name="user" value="1" id="add-user-toggle" />
                            <label for="add-user-toggle">{{trans('file.Add User')}}</label>
                        </div>
                        <div class="row user-input">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.UserName')}} *</label>
                                    <input type="text" name="name" class="form-control">
                                    @if($errors->has('name'))
                                   <span>
                                       <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Password')}} *</label>
                                    <input type="password" name="password" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="hidden" name="pos" value="0">
                            <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
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
    $("ul#people #customer-create-menu").addClass("active");

    $(".user-input").hide();

    $('input[name="user"]').on('change', function() {
        if ($(this).is(':checked')) {
            $('.user-input').show(300);
            $('input[name="name"]').prop('required',true);
            $('input[name="password"]').prop('required',true);
        }
        else{
            $('.user-input').hide(300);
            $('input[name="name"]').prop('required',false);
            $('input[name="password"]').prop('required',false);
        }
    });

    //$("#name").val(getSavedValue("name"));
    //$("#customer-group-id").val(getSavedValue("customer-group-id"));

    function saveValue(e) {
        var id = e.id;  // get the sender's id to save it.
        var val = e.value; // get the value.
        localStorage.setItem(id, val);// Every time user writing something, the localStorage's value will override.
    }
    //get the saved value function - return the value of "v" from localStorage.
    function getSavedValue  (v){
        if (!localStorage.getItem(v)) {
            return "";// You can change this to your defualt value.
        }
        return localStorage.getItem(v);
    }
</script>
@endsection
