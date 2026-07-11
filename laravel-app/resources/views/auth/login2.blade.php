<?php $general_setting = DB::table('general_settings')->find(1); ?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>{{$general_setting->site_title}}</title>
  <meta name="description" content="">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="all,follow">
  <link rel="manifest" href="{{url('manifest.json')}}">
  <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
  <!-- Bootstrap CSS-->
  <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
  <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap-datepicker.min.css') ?>" type="text/css">
  <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap-select.min.css') ?>" type="text/css">
  <!-- Font Awesome CSS-->
  <link rel="stylesheet" href="<?php echo asset('public/vendor/font-awesome/css/font-awesome.min.css') ?>" type="text/css">
  <!-- Google fonts - Roboto -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700">
  <!-- jQuery Circle-->
  <link rel="stylesheet" href="<?php echo asset('public/css/grasp_mobile_progress_circle-1.0.0.min.css') ?>" type="text/css">
  <!-- Custom Scrollbar-->
  <link rel="stylesheet" href="<?php echo asset('public/vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.css') ?>" type="text/css">
  <!-- theme stylesheet-->
  <link rel="stylesheet" href="<?php echo asset('public/css/style.default.css') ?>" id="theme-stylesheet" type="text/css">
  <!-- Custom stylesheet - for your changes-->
  <link rel="stylesheet" href="<?php echo asset('public/css/custom-' . $general_setting->theme) ?>" type="text/css">
  <!-- Tweaks for older IEs-->
  <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script><![endif]-->

  <style>
    .form-inner {
      background: #c9bce3 !important;
      border: 11px solid #7c5cc4;
      border-radius: 117px !important;
      text-align: center;
    }

    .form-putter {
      background: #c99e9e !important;
      border: 11px solid #a30000;
      border-radius: 117px !important;
      text-align: center;
      margin-top: 20px;
    }

    .form-puter {
      background: #a5c99e !important;
      border: 11px solid #00a341;
      border-radius: 117px !important;
      text-align: center;
    }

    input {
      border-radius: 20px;
      background: white !important;
      border: 3px solid #7c5cc4 !important;
    }

    .login-page .form-outer,
    .register-page .form-outer {
      min-height: 0;
    }
  </style>


  <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/jquery.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/jquery-ui.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/bootstrap-datepicker.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/popper.js/umd/popper.min.js') ?>">
  </script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/bootstrap/js/bootstrap.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/bootstrap/js/bootstrap-select.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/js/grasp_mobile_progress_circle-1.0.0.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/jquery.cookie/jquery.cookie.js') ?>">
  </script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/jquery-validation/jquery.validate.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js') ?>"></script>
  <script type="text/javascript" src="<?php echo asset('public/js/front.js') ?>"></script>
</head>

<body>
  <div class="page login-page">
    <div class="container">
      <div class="row">
        <div class="col-md-6">
          <div class="form-puter">
            <H1>Admin Login <br> ============= <br> Username : admin <br> password : 12345678</H1>
          </div>
        </div>
        <div class="col-md-6">
          <div class="form-puter">
            <H1>User Login <br> ============= <br> Username : user <br> password : 12345678</H1>
          </div>
        </div>
        <div class="col-md-12">
          <div class="form-putter">
            <H1>Personal information <br> ============= <br>Whatsapp Number : 923410060960 <br> gmail : rehanfaby36@gmail.com</H1>
          </div>
        </div>
      </div>

      <div class="form-outer text-center d-flex align-items-center">

        <div class="form-inner">
          <div class="logo">
            @if($general_setting->site_logo)
            <img src="{{url('public/logo', $general_setting->site_logo)}}" width="110">
            @else
            <span>{{$general_setting->site_title}}</span>
            @endif
          </div>
          @if(session()->has('delete_message'))
          <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('delete_message') }}</div>
          @endif
          <form method="POST" action="{{ route('login') }}" id="login-form">
            @csrf
            <div class="form-group-material">
              <input id="login-username" type="text" name="name" required class="input-material" value="">
              <label for="login-username" class="label-material">{{trans('file.UserName')}}</label>
              @if ($errors->has('name'))
              <p>
                <strong>{{ $errors->first('name') }}</strong>
              </p>
              @endif
            </div>

            <div class="form-group-material">
              <input id="login-password" type="password" name="password" required class="input-material" value="">
              <label for="login-password" class="label-material">{{trans('file.Password')}}</label>
              @if ($errors->has('password'))
              <p>
                <strong>{{ $errors->first('password') }}</strong>
              </p>
              @endif
            </div>
            <button type="submit" class="btn btn-primary btn-block">{{trans('file.LogIn')}}</button>
          </form>
          <!-- This three button for demo only-->
          <!-- <button type="submit" class="btn btn-success admin-btn">LogIn as Admin</button>
            <button type="submit" class="btn btn-info staff-btn">LogIn as Staff</button>
            <button type="submit" class="btn btn-dark customer-btn">LogIn as Customer</button> -->
          <br><br>
          <a href="{{ route('password.request') }}" class="forgot-pass">{{trans('file.Forgot Password?')}}</a>
          <!-- <p>{{trans('file.Do not have an account?')}}</p><a href="{{url('register')}}" class="signup">{{trans('file.Register')}}</a> -->
        </div>
        <div class="copyrights text-center">
          <p>{{trans('file.Developed By')}} <span class="external">{{$general_setting->developed_by}}</span></p>
        </div>
      </div>
    </div>
  </div>
</body>

</html>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('/saleproposmajed/service-worker.js').then(function(registration) {
        // Registration was successful
        console.log('ServiceWorker registration successful with scope: ', registration.scope);
      }, function(err) {
        // registration failed :(
        console.log('ServiceWorker registration failed: ', err);
      });
    });
  }
</script>
<script type="text/javascript">
  $('.admin-btn').on('click', function() {
    $("input[name='name']").focus().val('admin');
    $("input[name='password']").focus().val('admin');
  });

  $('.staff-btn').on('click', function() {
    $("input[name='name']").focus().val('staff');
    $("input[name='password']").focus().val('staff');
  });

  $('.customer-btn').on('click', function() {
    $("input[name='name']").focus().val('shakalaka');
    $("input[name='password']").focus().val('shakalaka');
  });
  // ------------------------------------------------------- //
  // Material Inputs
  // ------------------------------------------------------ //

  var materialInputs = $('input.input-material');

  // activate labels for prefilled values
  materialInputs.filter(function() {
    return $(this).val() !== "";
  }).siblings('.label-material').addClass('active');

  // move label on focus
  materialInputs.on('focus', function() {
    $(this).siblings('.label-material').addClass('active');
  });

  // remove/keep label on blur
  materialInputs.on('blur', function() {
    $(this).siblings('.label-material').removeClass('active');

    if ($(this).val() !== '') {
      $(this).siblings('.label-material').addClass('active');
    } else {
      $(this).siblings('.label-material').removeClass('active');
    }
  });
</script>