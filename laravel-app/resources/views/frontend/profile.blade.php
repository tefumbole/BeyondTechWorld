@extends('frontend.layout.main')
@section('content')

    <main class="main">
        <section class="section-box shop-template mt-60">
            <div class="container">
                @if($errors->has('name'))
                    <div class="alert alert-danger alert-dismissible text-center">
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
                @endif
                @if(session()->has('success1'))
                    <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('success1') }}</div>
                @endif
                <div class="row mb-100">
                    <form method="POST" action="{{ route('frontend.user.account.update') }}" id="login-form">
                        @csrf
                        <div class="row">
                            <h2>Update Account</h2>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.UserName')}} *</strong> </label>
                                    <input type="hidden" name="id" value="{{$lims_user_data->id}}">
                                    <input type="text" name="name" value="{{$lims_user_data->name}}" required class="form-control" />
                                    @if($errors->has('name'))
                                        <span>
                                       <strong>{{ $errors->first('name') }}</strong>
                                    </span>
                                    @endif
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.Email')}} *</strong> </label>
                                    <input type="email" name="email" value="{{$lims_user_data->email}}" required class="form-control">
                                    @if($errors->has('email'))
                                        <span>
                                       <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                    @endif
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.Phone Number')}} *</strong> </label>
                                    <input type="text" name="phone" value="{{$lims_user_data->phone}}" required class="form-control" />
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.Company Name')}}</strong> </label>
                                    <input type="text" name="company_name" value="{{$lims_user_data->company_name}}" class="form-control" />
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>{{trans('file.Address')}}</label>
                                    <input type="text" name="address" value="{{@$lims_user_data->customer->address}}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.City')}}</label>
                                    <input type="text" name="city" value="{{@$lims_user_data->customer->city}}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.State')}}</label>
                                    <input type="text" name="state" value="{{@$lims_user_data->customer->state}}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.Postal Code')}}</label>
                                    <input type="text" name="postal_code" value="{{@$lims_user_data->customer->postal_code}}" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>{{trans('file.Country')}}</label>
                                    <input type="text" name="country" value="{{@$lims_user_data->customer->country}}" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="submit" value="Update" class="btn btn-primary">
                            </div>
                        </div>
                    </form>

                    <h2>Change password</h2>
                    <form method="POST" action="{{ route('frontend.user.password.update') }}" id="login-form">
                        @csrf
                        <div class="row">
                            @if($errors->has('name'))
                                <div class="alert alert-danger alert-dismissible text-center">
                                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
                            @endif
                            @if(session()->has('not_permitted'))
                                <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
                            @endif
                            @if(session()->has('message'))
                                <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
                            @endif
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Old Password</label>
                                    <input type="hidden" name="id" value="{{$lims_user_data->id}}">
                                    <input type="password" required name="current_pass" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" required name="new_pass" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" required name="confirm_pass" class="form-control">
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="submit" value="Update" class="btn btn-primary">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </main>
@endsection
