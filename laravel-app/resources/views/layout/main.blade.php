    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link rel="icon" type="image/png" href="{{url('public/logo', $general_setting->site_logo)}}" />
          @if(!Route::is('report.customer'))
            <title>{{$general_setting->site_title}}</title>
          @else
              <title>Customer Name: {{ $customer_name }}</title>
          @endif
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="all,follow">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="manifest" href="{{url('manifest.json')}}">
        <!-- Bootstrap CSS-->
        <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap.min.css') ?>" type="text/css">
        <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap-toggle/css/bootstrap-toggle.min.css') ?>" type="text/css">
        <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap-datepicker.min.css') ?>" type="text/css">
        <link rel="stylesheet" href="<?php echo asset('public/vendor/jquery-timepicker/jquery.timepicker.min.css') ?>" type="text/css">
        <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/awesome-bootstrap-checkbox.css') ?>" type="text/css">
        <link rel="stylesheet" href="<?php echo asset('public/vendor/bootstrap/css/bootstrap-select.min.css') ?>" type="text/css">
        <!-- Font Awesome CSS-->
        <link rel="stylesheet" href="<?php echo asset('public/vendor/font-awesome/css/font-awesome.min.css') ?>" type="text/css">
        <!-- Drip icon font-->
        <link rel="stylesheet" href="<?php echo asset('public/vendor/dripicons/webfont.css') ?>" type="text/css">
        <!-- Google fonts - Roboto -->
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Nunito:400,500,700">
        <!-- jQuery Circle-->
        <link rel="stylesheet" href="<?php echo asset('public/css/grasp_mobile_progress_circle-1.0.0.min.css') ?>" type="text/css">
        <!-- Custom Scrollbar-->
        <link rel="stylesheet" href="<?php echo asset('public/vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.css') ?>" type="text/css">

        @if(Route::current()->getName() != '/')
        <!-- date range stylesheet-->
        <link rel="stylesheet" href="<?php echo asset('public/vendor/daterange/css/daterangepicker.min.css') ?>" type="text/css">
        <!-- table sorter stylesheet-->
        <link rel="stylesheet" type="text/css" href="<?php echo asset('public/vendor/datatable/dataTables.bootstrap4.min.css') ?>">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedheader/3.1.6/css/fixedHeader.bootstrap.min.css">
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.2.3/css/responsive.bootstrap.min.css">
        @endif

        <link rel="stylesheet" href="<?php echo asset('public/css/style.default.css') ?>" id="theme-stylesheet" type="text/css">
        <link rel="stylesheet" href="<?php echo asset('public/css/dropzone.css') ?>">
        <link rel="stylesheet" href="<?php echo asset('public/css/custom.css') ?>">


        <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/jquery.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/jquery-ui.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/bootstrap-datepicker.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/jquery/jquery.timepicker.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/popper.js/umd/popper.min.js') ?>">
        </script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/bootstrap/js/bootstrap.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/bootstrap-toggle/js/bootstrap-toggle.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/bootstrap/js/bootstrap-select.min.js') ?>"></script>

        <script type="text/javascript" src="<?php echo asset('public/js/grasp_mobile_progress_circle-1.0.0.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/jquery.cookie/jquery.cookie.js') ?>">
        </script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/chart.js/Chart.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/js/charts-custom.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/jquery-validation/jquery.validate.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/malihu-custom-scrollbar-plugin/jquery.mCustomScrollbar.concat.min.js')?>"></script>

        <script type="text/javascript" src="<?php echo asset('public/js/front.js') ?>"></script>

        @if(Route::current()->getName() != '/')
        <script type="text/javascript" src="<?php echo asset('public/vendor/daterange/js/moment.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/daterange/js/knockout-3.4.2.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/daterange/js/daterangepicker.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/tinymce/js/tinymce/tinymce.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/js/dropzone.js') ?>"></script>

        <!-- table sorter js-->
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/pdfmake.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/vfs_fonts.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/jquery.dataTables.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/dataTables.bootstrap4.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/dataTables.buttons.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/buttons.bootstrap4.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/buttons.colVis.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/buttons.html5.min.js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/buttons.print.min.js') ?>"></script>

        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/sum().js') ?>"></script>
        <script type="text/javascript" src="<?php echo asset('public/vendor/datatable/dataTables.checkboxes.min.js') ?>"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/fixedheader/3.1.6/js/dataTables.fixedHeader.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.3/js/dataTables.responsive.min.js"></script>
        <script type="text/javascript" src="https://cdn.datatables.net/responsive/2.2.3/js/responsive.bootstrap.min.js"></script>
        @endif

        <!-- Custom stylesheet - for your changes-->
        <link rel="stylesheet" href="<?php echo asset('public/css/custom-'.$general_setting->theme) ?>" type="text/css" id="custom-style">
        <style>
            :root {
                --beyond-primary: #0b3f90;
                --beyond-primary-dark: #072f6b;
                --beyond-accent: #c6ab47;
                --beyond-bg: #f3f6fb;
                --beyond-card: #ffffff;
                --beyond-text: #1f2a44;
                --beyond-muted: #6f7b91;
            }

            body {
                background: var(--beyond-bg);
                color: var(--beyond-text);
            }

            .side-navbar {
                background: var(--beyond-primary);
                box-shadow: 2px 0 14px rgba(5, 28, 64, 0.25);
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1030;
            }

            .side-navbar-wrapper {
                display: flex;
                flex-direction: column;
                height: 100vh;
                max-height: 100vh;
                overflow: hidden !important;
            }

            .sidebar-brand-block {
                flex-shrink: 0;
                padding: 16px 14px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            }

            .sidebar-brand-header {
                display: flex;
                align-items: flex-start;
                gap: 12px;
            }

            .sidebar-brand-toggle {
                flex-shrink: 0;
                width: 40px;
                height: 40px;
                border: 1px solid rgba(255, 255, 255, 0.28);
                border-radius: 8px;
                background: rgba(255, 255, 255, 0.06);
                color: #fff;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                margin-top: 2px;
            }

            .sidebar-brand-toggle:hover {
                background: rgba(255, 255, 255, 0.14);
            }

            .sidebar-brand-text {
                flex: 1;
                min-width: 0;
                text-align: left;
            }

            .sidebar-brand-block img {
                width: 52px;
                height: 52px;
                object-fit: contain;
                margin-bottom: 8px;
            }

            .sidebar-brand-title {
                color: var(--beyond-accent);
                font-size: 22px;
                font-weight: 800;
                line-height: 1.15;
                word-break: break-word;
            }

            .sidebar-brand-subtitle {
                margin-top: 4px;
                color: rgba(255, 255, 255, 0.82);
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
            }

            .side-navbar .main-menu {
                flex: 1 1 auto;
                min-height: 0;
            }

            .sidebar-user-panel {
                flex-shrink: 0;
                background: #072f6b;
                border-top: 1px solid rgba(255, 255, 255, 0.12);
                padding: 16px 14px;
            }

            .sidebar-user-meta {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
            }

            .sidebar-user-avatar {
                width: 42px;
                height: 42px;
                border-radius: 50%;
                background: var(--beyond-accent);
                color: var(--beyond-primary-dark);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 18px;
                flex-shrink: 0;
            }

            .sidebar-user-name {
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                line-height: 1.3;
            }

            .sidebar-user-role {
                display: inline-block;
                margin-top: 4px;
                padding: 2px 8px;
                border-radius: 999px;
                background: rgba(198, 171, 71, 0.18);
                color: #f4dd88;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .sidebar-user-link {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px 8px;
                border-radius: 8px;
                color: #dce7ff;
                text-decoration: none !important;
                font-size: 14px;
                font-weight: 600;
            }

            .sidebar-user-link i {
                color: var(--beyond-accent) !important;
                width: 18px;
                text-align: center;
            }

            .sidebar-user-link:hover {
                background: rgba(255, 255, 255, 0.08);
                color: #fff;
            }

            .sidebar-user-link.logout-link {
                color: #ffb4b4;
            }

            .sidebar-user-link.logout-link i {
                color: #ff8e8e !important;
            }

            .side-navbar .side-menu li a {
                color: #eef3ff;
                border-radius: 10px;
                margin: 4px 10px;
                padding: 13px 14px;
                font-size: 15px;
                font-weight: 600;
                line-height: 1.35;
            }

            .side-navbar .side-menu li a i,
            .side-navbar .side-menu li a [class*="dripicons-"],
            .side-navbar .side-menu li a [class*="fa-"],
            .side-navbar .side-menu li a i::before {
                color: var(--beyond-accent) !important;
                font-size: 18px;
                width: 22px;
                margin-right: 4px;
            }

            .side-navbar .side-menu > li > a:hover,
            .side-navbar .side-menu > li > a:focus {
                background: rgba(198, 171, 71, 0.18);
                color: #ffffff;
            }

            .side-navbar .side-menu > li > a.menu-parent-active,
            .side-navbar .side-menu > li > a[aria-expanded="true"],
            .side-navbar .side-menu > li.active > a {
                background: var(--beyond-accent) !important;
                color: var(--beyond-primary-dark) !important;
                font-weight: 700;
            }

            .side-navbar .side-menu > li > a.menu-parent-active i,
            .side-navbar .side-menu > li > a[aria-expanded="true"] i,
            .side-navbar .side-menu > li.active > a i,
            .side-navbar .side-menu > li > a.menu-parent-active [class*="dripicons-"],
            .side-navbar .side-menu > li > a.menu-parent-active [class*="fa-"] {
                color: var(--beyond-primary-dark) !important;
            }

            #side-main-menu ul.collapse,
            #side-main-menu ul.collapse.show,
            #side-main-menu ul.collapse.collapsing {
                display: none !important;
                height: 0 !important;
                max-height: 0 !important;
                overflow: hidden !important;
                visibility: hidden !important;
                opacity: 0 !important;
                pointer-events: none !important;
                padding: 0 !important;
                margin: 0 !important;
                border: 0 !important;
                background: transparent !important;
            }

            .side-navbar .side-menu > li > a[data-toggle="collapse"]::after,
            .side-navbar .side-menu > li > a[data-toggle="collapse"]::before,
            .side-navbar .side-menu li a[data-toggle="collapse"]::after,
            .side-navbar .side-menu li a[data-toggle="collapse"]::before {
                display: none !important;
                content: none !important;
            }

            .side-navbar .side-menu > li > a > span:empty,
            .side-navbar .side-menu li a > span:empty {
                display: none !important;
            }

            .side-navbar .side-menu li a .fa-angle-down,
            .side-navbar .side-menu li a .fa-angle-left,
            .side-navbar .side-menu li a .fa-angle-right,
            .side-navbar .side-menu li a .fa-chevron-down,
            .side-navbar .side-menu li a .fa-chevron-left,
            .side-navbar .side-menu li a .fa-chevron-right,
            .side-navbar .side-menu li a .dripicons-chevron-down {
                display: none !important;
            }

            .beyond-module-tabs {
                display: none;
                background: #fff;
                border: 0;
                border-bottom: 1px solid #e3e9f4;
                border-radius: 0;
                padding: 0;
                margin-bottom: 18px;
                box-shadow: none;
            }

            .beyond-module-tabs.is-visible {
                display: block;
            }

            .beyond-module-tabs-label {
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--beyond-muted);
                padding: 14px 18px 0;
                margin-bottom: 0;
            }

            .beyond-module-tabs-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                overflow: visible;
                padding: 12px 10px 10px;
            }

            .beyond-module-tab {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 14px;
                border-radius: 10px;
                border: 2px solid #d7e0ef;
                background: #fff;
                color: #4b5870;
                font-size: 13px;
                font-weight: 700;
                text-decoration: none !important;
                transition: all 0.15s ease;
                white-space: nowrap;
                margin: 0;
            }

            .beyond-module-tab i {
                font-size: 15px;
            }

            .beyond-module-tab:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(11, 63, 144, 0.12);
                text-decoration: none !important;
            }

            .beyond-module-tab.is-active {
                color: #fff !important;
                box-shadow: 0 6px 16px rgba(11, 63, 144, 0.18);
            }

            .beyond-module-tab.is-active i {
                color: #fff !important;
            }

            .beyond-module-tab.tone-blue { border-color: #0b3f90; color: #0b3f90; }
            .beyond-module-tab.tone-blue.is-active { background: #0b3f90 !important; border-color: #0b3f90 !important; }
            .beyond-module-tab.tone-blue i { color: #0b3f90 !important; }

            .beyond-module-tab.tone-gold { border-color: #c6ab47; color: #8a7424; }
            .beyond-module-tab.tone-gold.is-active { background: #c6ab47 !important; border-color: #c6ab47 !important; color: #10213d !important; }
            .beyond-module-tab.tone-gold i { color: #8a7424 !important; }
            .beyond-module-tab.tone-gold.is-active i { color: #10213d !important; }

            .beyond-module-tab.tone-purple { border-color: #7b61ff; color: #7b61ff; }
            .beyond-module-tab.tone-purple.is-active { background: #7b61ff !important; border-color: #7b61ff !important; }
            .beyond-module-tab.tone-purple i { color: #7b61ff !important; }

            .beyond-module-tab.tone-pink { border-color: #e91e8c; color: #e91e8c; }
            .beyond-module-tab.tone-pink.is-active { background: #e91e8c !important; border-color: #e91e8c !important; }
            .beyond-module-tab.tone-pink i { color: #e91e8c !important; }

            .beyond-module-tab.tone-green { border-color: #10b981; color: #10b981; }
            .beyond-module-tab.tone-green.is-active { background: #10b981 !important; border-color: #10b981 !important; }
            .beyond-module-tab.tone-green i { color: #10b981 !important; }

            .beyond-module-tab.tone-orange { border-color: #f59e0b; color: #c77708; }
            .beyond-module-tab.tone-orange.is-active { background: #f59e0b !important; border-color: #f59e0b !important; color: #10213d !important; }
            .beyond-module-tab.tone-orange i { color: #c77708 !important; }
            .beyond-module-tab.tone-orange.is-active i { color: #10213d !important; }

            .beyond-module-tab.tone-teal { border-color: #06b6d4; color: #0891b2; }
            .beyond-module-tab.tone-teal.is-active { background: #06b6d4 !important; border-color: #06b6d4 !important; }
            .beyond-module-tab.tone-teal i { color: #0891b2 !important; }

            .beyond-module-tab.tone-red { border-color: #ef4444; color: #ef4444; }
            .beyond-module-tab.tone-red.is-active { background: #ef4444 !important; border-color: #ef4444 !important; }
            .beyond-module-tab.tone-red i { color: #ef4444 !important; }

            .header .navbar {
                background: var(--beyond-primary) !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                box-shadow: 0 2px 12px rgba(5, 28, 64, 0.18);
            }

            .header .navbar,
            .header .navbar a,
            .header .nav-menu .nav-item > a,
            .header #toggle-btn,
            .header .menu-btn {
                color: #ffffff !important;
            }

            .header .brand-big {
                display: none;
            }

            .header .btn-pos {
                background: var(--beyond-accent) !important;
                border-color: var(--beyond-accent) !important;
                color: #10213d !important;
                border-radius: 8px;
            }

            .header .nav-menu > li.nav-item > a i,
            .header .nav-menu > li.nav-item > a i:before,
            .header .nav-menu > li.nav-item > a [class*="dripicons-"]:before {
                color: var(--beyond-accent) !important;
            }

            .header .nav-menu > li.nav-item > a:hover i,
            .header .nav-menu > li.nav-item > a:hover i:before {
                color: #fff !important;
            }

            .header .btn-pos i,
            .header .btn-pos i:before {
                color: #10213d !important;
            }

            .header .badge-danger {
                background: #dc3545 !important;
            }

            .header .right-sidebar {
                background: #fff;
            }

            .header .right-sidebar a {
                color: var(--beyond-primary) !important;
            }

            .btn-primary,
            .btn-pos,
            .btn-info {
                background: var(--beyond-primary) !important;
                border-color: var(--beyond-primary) !important;
            }

            .btn-primary:hover,
            .btn-primary:focus,
            .btn-pos:hover,
            .btn-pos:focus,
            .btn-info:hover,
            .btn-info:focus {
                background: var(--beyond-primary-dark) !important;
                border-color: var(--beyond-primary-dark) !important;
            }

            .btn-warning,
            .badge-warning {
                background: var(--beyond-accent) !important;
                border-color: var(--beyond-accent) !important;
                color: #10213d !important;
            }

            .badge-primary {
                background: var(--beyond-primary) !important;
            }

            .btn-link,
            a {
                color: var(--beyond-primary);
            }

            .card,
            .table,
            .modal-content {
                border-color: #e3e9f4;
            }

            .page-content,
            .content-inner {
                background: var(--beyond-bg);
            }

            .content-inner {
                display: flex;
                flex-direction: column;
                min-height: calc(100vh - 70px);
            }

            .content-inner > .page-content {
                flex: 1 1 auto;
                padding-bottom: 24px;
            }

            .main-footer {
                position: relative !important;
                z-index: 1;
                margin-top: auto;
                padding: 14px 0;
                background: #fff;
                border-top: 1px solid #e3e9f4;
            }

            .main-footer p {
                margin: 0;
                color: #5f6776;
                font-size: 13px;
            }

            .side-navbar .main-menu {
                overflow-y: auto !important;
                overflow-x: hidden;
                scrollbar-width: auto;
                scrollbar-color: var(--beyond-accent) rgba(255, 255, 255, 0.2);
            }

            .side-navbar .main-menu::-webkit-scrollbar {
                width: 10px;
            }

            .side-navbar .main-menu::-webkit-scrollbar-track {
                background: rgba(255, 255, 255, 0.14);
                border-radius: 10px;
            }

            .side-navbar .main-menu::-webkit-scrollbar-thumb {
                background: var(--beyond-accent);
                border-radius: 10px;
            }

            .mCSB_scrollTools {
                opacity: 1 !important;
            }

            .mCSB_scrollTools .mCSB_dragger .mCSB_dragger_bar {
                background-color: var(--beyond-accent) !important;
                width: 12px !important;
                border-radius: 10px !important;
            }

            .mCSB_scrollTools .mCSB_draggerRail {
                background-color: rgba(255, 255, 255, 0.2) !important;
            }

            nav.side-navbar .side-menu li a i,
            nav.side-navbar .side-menu li a span i,
            nav.side-navbar [class^="dripicons-"]:before,
            nav.side-navbar [class*=" dripicons-"]:before {
                color: var(--beyond-accent) !important;
            }

            .beyond-dashboard {
                padding: 4px 0 24px;
            }

            .beyond-dashboard-hero {
                display: flex;
                flex-wrap: wrap;
                align-items: flex-start;
                justify-content: space-between;
                gap: 16px;
                margin-bottom: 24px;
            }

            .beyond-dashboard-hero h1 {
                color: var(--beyond-primary);
                font-size: 32px;
                font-weight: 800;
                margin: 0 0 8px;
            }

            .beyond-dashboard-hero .welcome-line {
                color: var(--beyond-muted);
                font-size: 15px;
                margin: 0;
            }

            .beyond-role-badge {
                display: inline-block;
                margin-left: 8px;
                padding: 3px 10px;
                border-radius: 999px;
                background: rgba(11, 63, 144, 0.1);
                color: var(--beyond-primary);
                font-size: 12px;
                font-weight: 700;
                text-transform: lowercase;
            }

            .beyond-dashboard-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .beyond-stat-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 18px;
                margin-bottom: 24px;
            }

            @media (max-width: 1199px) {
                .beyond-stat-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 575px) {
                .beyond-stat-grid {
                    grid-template-columns: 1fr;
                }
            }

            .beyond-stat-card {
                background: #fff;
                border: 1px solid #e3e9f4;
                border-radius: 14px;
                padding: 20px 18px;
                box-shadow: 0 8px 24px rgba(15, 35, 80, 0.05);
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }

            .beyond-stat-card .label {
                color: var(--beyond-muted);
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 6px;
            }

            .beyond-stat-card .value {
                color: var(--beyond-text);
                font-size: 28px;
                font-weight: 800;
                line-height: 1.1;
            }

            .beyond-stat-icon {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                flex-shrink: 0;
            }

            .beyond-stat-icon.blue { background: rgba(11, 63, 144, 0.12); color: var(--beyond-primary); }
            .beyond-stat-icon.green { background: rgba(0, 198, 137, 0.12); color: #00a86b; }
            .beyond-stat-icon.gold { background: rgba(198, 171, 71, 0.18); color: #9a7b1a; }
            .beyond-stat-icon.purple { background: rgba(115, 54, 134, 0.12); color: #733686; }

            .beyond-panel {
                background: #fff;
                border: 1px solid #e3e9f4;
                border-radius: 14px;
                box-shadow: 0 8px 24px rgba(15, 35, 80, 0.05);
                margin-bottom: 24px;
            }

            .beyond-panel-header {
                padding: 18px 20px 0;
            }

            .beyond-panel-header h4 {
                margin: 0 0 4px;
                color: var(--beyond-primary);
                font-size: 18px;
                font-weight: 800;
            }

            .beyond-panel-header p {
                margin: 0 0 16px;
                color: var(--beyond-muted);
                font-size: 13px;
            }

            .beyond-quick-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 16px;
                padding: 0 20px 20px;
            }

            .beyond-quick-card {
                display: flex;
                align-items: flex-start;
                gap: 14px;
                padding: 18px;
                border: 1px solid #e8edf5;
                border-radius: 12px;
                text-decoration: none !important;
                transition: all 0.15s ease;
                background: #fafbfd;
            }

            .beyond-quick-card:hover {
                border-color: var(--beyond-primary);
                box-shadow: 0 8px 20px rgba(11, 63, 144, 0.08);
                transform: translateY(-1px);
            }

            .beyond-quick-card .icon-wrap {
                width: 44px;
                height: 44px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 20px;
                flex-shrink: 0;
            }

            .beyond-quick-card h5 {
                margin: 0 0 4px;
                color: var(--beyond-text);
                font-size: 15px;
                font-weight: 700;
            }

            .beyond-quick-card p {
                margin: 0;
                color: var(--beyond-muted);
                font-size: 12px;
                line-height: 1.45;
            }

            .beyond-status-list {
                padding: 0 20px 20px;
            }

            .beyond-status-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 0;
                border-bottom: 1px solid #eef2f8;
            }

            .beyond-status-item:last-child {
                border-bottom: 0;
            }

            .beyond-status-item strong {
                display: block;
                color: var(--beyond-text);
                font-size: 14px;
            }

            .beyond-status-item span {
                color: var(--beyond-muted);
                font-size: 12px;
            }

            .beyond-status-badge {
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 700;
                white-space: nowrap;
            }

            .beyond-status-badge.ok {
                background: rgba(0, 198, 137, 0.14);
                color: #008f5d;
            }

            .beyond-status-badge.info {
                background: rgba(11, 63, 144, 0.12);
                color: var(--beyond-primary);
            }

            .beyond-profile-cta {
                margin: 0 20px 20px;
                padding: 16px;
                border-radius: 12px;
                background: linear-gradient(135deg, #f7f9fd, #eef3fb);
                border: 1px solid #e3e9f4;
            }

            .beyond-profile-cta p {
                margin: 0 0 12px;
                color: var(--beyond-muted);
                font-size: 13px;
            }

            .beyond-analytics-section {
                margin-top: 8px;
            }

            .beyond-analytics-section > .card,
            .beyond-analytics-section .dashboard-counts .wrapper.count-title {
                border-radius: 14px;
            }

            .filter-toggle .btn-secondary {
                border-radius: 8px;
                font-weight: 600;
            }

            .filter-toggle .btn-secondary.active {
                background: var(--beyond-primary) !important;
                border-color: var(--beyond-primary) !important;
                color: #fff !important;
            }
        </style>
      </head>

      <body onload="myFunction()">
        <div id="loader"></div>
          <!-- Side Navbar -->
          <nav class="side-navbar">
            <div class="side-navbar-wrapper">
              @php
                  $brandTitle = $general_setting->site_title ?? config('app.name', 'Application');
                  $userInitial = strtoupper(substr(Auth::user()->name, 0, 1));
              @endphp
              <div class="sidebar-brand-block">
                  <div class="sidebar-brand-header">
                      <button type="button" class="sidebar-brand-toggle" id="sidebar-toggle-btn" aria-label="Toggle navigation">
                          <i class="fa fa-bars"></i>
                      </button>
                      <div class="sidebar-brand-text">
                          <div class="sidebar-brand-title">{{ $brandTitle }}</div>
                      </div>
                  </div>
              </div>
                <div class="main-menu">
                    <ul id="side-main-menu" class="side-menu list-unstyled">
                        @if(Auth::user()->role_id != 7)
                            <li><a href="{{ url('/admin') }}"> <i class="dripicons-meter"></i><span>{{ __('file.dashboard') }}</span></a></li>
                        @endif
                        @if(in_array((int) Auth::user()->role_id, [1, 2], true))
                            <li><a href="{{ url('/admin/site-content') }}"> <i class="dripicons-web"></i><span>Site Content</span></a></li>
                        @endif
                        <?php
                        $role = \Spatie\Permission\Models\Role::find(Auth::user()->role_id);
                        if (!isset($all_permission) || !is_array($all_permission)) {
                            $all_permission = [];
                        }
                        if (empty($all_permission) && $role) {
                            foreach ($role->permissions as $permission) {
                                $all_permission[] = $permission->name;
                            }
                        }
                        $category_permission_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'category'],
                                ['role_id', $role->id] ])->first();
                        $index_permission = DB::table('permissions')->where('name', 'products-index')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $print_barcode = DB::table('permissions')->where('name', 'print_barcode')->first();
                        $print_barcode_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $print_barcode->id],
                            ['role_id', $role->id]
                        ])->first();

                        $stock_count = DB::table('permissions')->where('name', 'stock_count')->first();
                        $stock_count_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $stock_count->id],
                            ['role_id', $role->id]
                        ])->first();

                        $adjustment = DB::table('permissions')->where('name', 'adjustment')->first();
                        $adjustment_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $adjustment->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($category_permission_active || $index_permission_active || $print_barcode_active || $stock_count_active || $adjustment_active)
                            <li><a href="#product" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-list"></i><span>{{__('file.product')}}</span><span></a>
                                <ul id="product" class="collapse list-unstyled ">
                                    @if($category_permission_active)
                                        <li id="category-menu"><a href="{{route('category.index')}}">{{__('file.category')}}</a></li>
                                    @endif
                                    @if($index_permission_active)
                                        <li id="product-list-menu"><a href="{{route('products.index')}}">{{__('file.product_list')}}</a></li>
                                            <?php
                                            $add_permission = DB::table('permissions')->where('name', 'products-add')->first();
                                            $add_permission_active = DB::table('role_has_permissions')->where([
                                                ['permission_id', $add_permission->id],
                                                ['role_id', $role->id]
                                            ])->first();
                                            ?>
                                        @if($add_permission_active)
                                            <li id="product-create-menu"><a href="{{route('products.create')}}">{{__('file.add_product')}}</a></li>
                                        @endif
                                    @endif
                                    @if($print_barcode_active)
                                        <li id="printBarcode-menu"><a href="{{route('product.printBarcode')}}">{{__('file.print_barcode')}}</a></li>
                                    @endif
                                    @if($adjustment_active)
                                        <li id="adjustment-list-menu"><a href="{{route('qty_adjustment.index')}}">{{trans('file.Adjustment List')}}</a></li>
                                        <li id="adjustment-create-menu"><a href="{{route('qty_adjustment.create')}}">{{trans('file.Add Adjustment')}}</a></li>
                                    @endif
                                    @if($stock_count_active)
                                        <li id="stock-count-menu"><a href="{{route('stock-count.index')}}">{{trans('file.Stock Count')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <?php
                        $index_permission = DB::table('permissions')->where('name', 'purchases-index')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($index_permission_active)
                            <li><a href="#purchase" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-card"></i><span>{{trans('file.Purchase')}}</span></a>
                                <ul id="purchase" class="collapse list-unstyled ">
                                    <li id="purchase-list-menu"><a href="{{route('purchases.index')}}">{{trans('file.Purchase List')}}</a></li>
                                        <?php
                                        $add_permission = DB::table('permissions')->where('name', 'purchases-add')->first();
                                        $add_permission_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $add_permission->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($add_permission_active)
                                        <li id="purchase-create-menu"><a href="{{route('purchases.create')}}">{{trans('file.Add Purchase')}}</a></li>
                                        <li id="purchase-import-menu"><a href="{{url('purchases/purchase_by_csv')}}">{{trans('file.Import Purchase By CSV')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <?php
                        $sale_index_permission = DB::table('permissions')->where('name', 'sales-index')->first();
                        $sale_index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $sale_index_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $gift_card_permission = DB::table('permissions')->where('name', 'gift_card')->first();
                        $gift_card_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $gift_card_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $coupon_permission = DB::table('permissions')->where('name', 'coupon')->first();
                        $coupon_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $coupon_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $delivery_permission_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'delivery'],
                                ['role_id', $role->id] ])->first();

                        $sale_add_permission = DB::table('permissions')->where('name', 'sales-add')->first();
                        $sale_add_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $sale_add_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($sale_index_permission_active || $gift_card_permission_active || $coupon_permission_active || $delivery_permission_active)
                            <li><a href="#sale" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-cart"></i><span>{{trans('file.Sale')}}</span></a>
                                <ul id="sale" class="collapse list-unstyled ">
                                    @if($sale_index_permission_active)
                                        <li id="sale-list-menu"><a href="{{route('sales.index')}}">{{trans('file.Sale List')}}</a></li>
                                        @if($sale_add_permission_active)
                                            <li><a href="{{route('sale.pos')}}">POS</a></li>
                                            <li id="sale-create-menu"><a href="{{route('sales.create')}}">{{trans('file.Add Sale')}}</a></li>
                                            <li id="sale-import-menu"><a href="{{url('sales/sale_by_csv')}}">{{trans('file.Import Sale By CSV')}}</a></li>
                                        @endif
                                    @endif

                                    @if($gift_card_permission_active)
                                        <li id="gift-card-menu"><a href="{{route('gift_cards.index')}}">{{trans('file.Gift Card List')}}</a> </li>
                                    @endif
                                    @if($coupon_permission_active)
                                        <li id="coupon-menu"><a href="{{route('coupons.index')}}">{{trans('file.Coupon List')}}</a> </li>
                                    @endif
                                    @if($delivery_permission_active)
                                        <li id="delivery-menu"><a href="{{route('delivery.index')}}">{{trans('file.Delivery List')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <?php
                        $index_permission_booking = DB::table('permissions')->where('name', 'booking_module')->first();
                        $index_permission_booking_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission_booking->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($index_permission_booking_active)
                            <li><a href="#booking" aria-expanded="false" data-toggle="collapse"> <i class="fa fa-exchange"></i><span>{{trans('file.Booking Module')}}</span></a>
                                <ul id="booking" class="collapse list-unstyled ">
                                        <?php
                                        $create_permission_booking = DB::table('permissions')->where('name', 'booking_create')->first();
                                        $create_permission_booking_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $create_permission_booking->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        $booking_report = DB::table('permissions')->where('name', 'booking_report')->first();
                                        $booking_report_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $booking_report->id],
                                            ['role_id', $role->id]
                                        ])->first();

                                        ?>
                                    @if($create_permission_booking_active)
                                        <li id="booking-create-menu"><a href="{{route('booking.create')}}">Booking Create</a></li>
                                    @endif
                                        <?php
                                        $create_permission_booking = DB::table('permissions')->where('name', 'booking_index')->first();
                                        $create_permission_booking_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $create_permission_booking->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($create_permission_booking_active)
                                        <li id="booking-index-menu"><a href="{{route('booking.index')}}">Booking List</a></li>
                                        <li id="booking-product-menu"><a href="{{route('booking.product')}}">Booked Products</a></li>
                                        <li id="booking-reminders-menu"><a href="{{route('booking.reminders')}}">Booking Reminder</a></li>
                                    @endif
                                    @if(in_array('booking_awaiting_signature', $all_permission))
                                        <li id="booking-awaiting-menu"><a href="{{route('booking.awaiting-signature')}}">Awaiting Signature</a></li>
                                    @endif
                                    @if(in_array('booking_pending_review', $all_permission))
                                        <li id="booking-pending-menu"><a href="{{route('booking.pending-review')}}">Pending Review</a></li>
                                    @endif
                                    @if(in_array('booking_signed_contracts', $all_permission))
                                        <li id="booking-signed-menu"><a href="{{route('booking.signed-contracts')}}">Signed Contracts</a></li>
                                    @endif
                                    @if(in_array('booking_goods_received', $all_permission))
                                        <li id="booking-goods-received-menu"><a href="{{route('booking.goods-received')}}">Goods Received</a></li>
                                    @endif
                                    @if($booking_report_active)
                                        <li id="booking-report-menu">
                                            <a href="{{url('report/daily_booking/'.date('Y').'/'.date('m'))}}">Booking Calender</a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        @if(in_array('shops-index', $all_permission))
                            <li>
                                <a href="#shop" aria-expanded="false" data-toggle="collapse"> <i class="fa fa-building"></i><span>Shops</span><span></a>
                                <ul id="shop" class="collapse list-unstyled ">
                                    <li id="shop-list-menu"><a href="{{route('shop.index')}}">Shop Listing</a></li>
                                </ul>
                            </li>
                        @endif
                        @if(in_array('orders-index', $all_permission))
                            <li><a href="#order" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-document"></i><span>{{trans('file.order')}}</span><span></a>
                                <ul id="order" class="collapse list-unstyled ">
                                    <li id="order-list-menu"><a href="{{route('order.index')}}">{{trans('file.Order List')}}</a></li>
                                    @if(Auth::user()->role_id != 12 || Auth::user()->can_donation == 1)
                                        @if(in_array('donations-index', $all_permission))<li id="donation-list-menu"><a href="{{route('donation.list')}}">Donation List</a></li>@endif
                                    @endif
                                    @if(Auth::user()->role_id != 12 || Auth::user()->can_service == 1)
                                        @if(in_array('services-index', $all_permission))<li id="service-list-menu"><a href="{{route('services.list')}}">Service List</a></li>@endif
                                    @endif
                                    @if(Auth::user()->role_id != 12 || Auth::user()->can_booking == 1)
                                        @if(in_array('booking_index', $all_permission))
                                            <li id="online-booking-index-menu"><a href="{{route('online.booking.index')}}">Online Booking List</a></li>
                                        @endif
                                    @endif
                                </ul>
                            </li>
                        @endif
                        {{--                        @if(in_array('payments-index', $all_permission))--}}
                        {{--                        <li>--}}
                        {{--                            <a href="#payment_request" aria-expanded="false" data-toggle="collapse"> <i class="fa fa-money"></i><span>Payment Request</span><span></a>--}}
                        {{--                            <ul id="payment_request" class="collapse list-unstyled ">--}}
                        {{--                                <li id="payment-list-menu"><a href="{{route('payment.list')}}">Payment Request</a></li>--}}
                        {{--                            </ul>--}}
                        {{--                        </li>--}}
                        {{--                        @endif--}}
                        @if(in_array('payments-index', $all_permission))
                            <li>
                                <a href="#payments" aria-expanded="false" data-toggle="collapse"> <i class="fa fa-dollar"></i><span>Payments</span></a>
                                <ul id="payments" class="collapse list-unstyled ">
                                    <li id="payment-index-menu"><a href="{{route('payment.index')}}">Awaiting Payment</a></li>
                                    <li id="desposit-index-menu"><a href="{{route('deposit.index')}}">All Deposits</a></li>
                                </ul>
                            </li>
                        @endif
                        <?php
                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_module')->first();
                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission_letter->id],
                            ['role_id', $role->id]
                        ])->first();

                        $letter_model = new \App\Letter();
                        $total_letters = $letter_model->where('is_active', true)->count();
                        $rejected_letters = $letter_model->where('is_active', true)->where('is_rejected', 1)->count();
                        $awaiting_editing_letters = $letter_model->where('is_active', true)->where('is_edit', 0)->where('is_approve', 0)->where('is_sign', 0)->where('is_sent', 0)->where('is_rejected', 0)->count();
                        $awaiting_approve_letters = $letter_model->where('is_active', true)->where('is_edit', 1)->where('is_approve', 0)->where('is_sign', 0)->where('is_sent', 0)->where('is_rejected', 0)->count();
                        $awaiting_sign_letters = $letter_model->where('is_active', true)->where('is_approve', 1)->where('is_sign', 0)->where('is_sent', 0)->where('is_rejected', 0)->count();
                        $ready_to_send_letters = $letter_model->where('is_active', true)->where('is_approve', 1)->where('is_sign', 1)->where('is_sent', 0)->where('is_rejected', 0)->count();
                        $sent_letters = $letter_model->where('is_active', true)->where('is_approve', 1)->where('is_sign', 1)->where('is_sent', 1)->where('is_rejected', 0)->count();

                        ?>
                        @if($index_permission_letter_active)
                            <li><a href="#letter" aria-expanded="false" data-toggle="collapse"> <i class="fa fa-newspaper-o"></i><span>{{trans('file.Letters')}}</span></a>
                                <ul id="letter" class="collapse list-unstyled ">
                                        <?php
                                        $create_permission_letter = DB::table('permissions')->where('name', 'letter_create')->first();
                                        $create_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $create_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        $category_permission_letter = DB::table('permissions')->where('name', 'letter_category')->first();
                                        $category_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $category_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>


                                    @if($category_permission_letter_active)
                                        <li id="letter-category-menu"><a href="{{route('letter.category')}}">Letter Categories</a></li>
                                    @endif

                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_index')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-index-menu">
                                            <a href="{{route('letter.all')}}">Letter Listing</a>
                                            <span class="badge badge-info badge-count">{{ $total_letters }}</span>
                                        </li>
                                    @endif

                                    @if($create_permission_letter_active)
                                        <li id="letter-create-menu"><a href="{{route('letter.create')}}">Create Letter</a></li>
                                    @endif
                                        <?php
                                        $index_permission_letter_rejected = DB::table('permissions')->where('name', 'letter_rejected')->first();
                                        $index_permission_letter_rejected_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter_rejected->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_rejected_active)
                                        <li id="letter-rejected-menu"><a href="{{route('letter.index.rejected')}}">Rejected Letters</a>
                                            <span class="badge badge-danger badge-count">{{ $rejected_letters }}</span></li>
                                    @endif
                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_awaiting_edit')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-awaiting-menu"><a href="{{route('letter.index')}}">Awaiting Editing</a>
                                            <span class="badge badge-primary badge-count">{{ $awaiting_editing_letters }}</span></li>
                                    @endif
                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_edited_index')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-edited-menu"><a href="{{route('letter.index.edited')}}">Awaiting Approval</a>
                                            <span class="badge badge-primary badge-count">{{ $awaiting_approve_letters }}</span></li>
                                    @endif
                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_approve_index')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-approved-menu"><a href="{{route('letter.index.approved')}}">Awaiting Signature</a>
                                            <span class="badge badge-primary badge-count">{{ $awaiting_sign_letters }}</span></li>
                                    @endif

                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_sign_index')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-signed-menu"><a href="{{route('letter.index.signed')}}">Ready To Send</a>
                                            <span class="badge badge-primary badge-count">{{ $ready_to_send_letters }}</span></li>
                                    @endif

                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_send_index')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-sent-menu"><a href="{{route('letter.index.sent')}}">Sent Letters</a>
                                            <span class="badge badge-primary badge-count">{{ $sent_letters }}</span></li>
                                        <li id="letter-sent-print-menu"><a href="{{route('letter.index.sent.print')}}">Print Letters</a>
                                            <span class="badge badge-primary badge-count">{{ $sent_letters }}</span></li>
                                        <li id="letter-sent-download-menu"><a href="{{route('letter.index.sent.download')}}">Download Letters</a>
                                            <span class="badge badge-primary badge-count">{{ $sent_letters }}</span></li>
                                    @endif
                                        <?php
                                        $index_permission_letter = DB::table('permissions')->where('name', 'letter_template')->first();
                                        $index_permission_letter_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $index_permission_letter->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($index_permission_letter_active)
                                        <li id="letter-template-menu"><a href="{{route('letter.template.index')}}">Templates Letter</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        @if(in_array('announcement_index', $all_permission))
                            <li id="announcement-main-menu">
                                <a href="{{ route('announcement.index') }}"> <i class="fa fa-bullhorn"></i><span>{{trans('file.Announcement')}}</span></a>
                            </li>
                        @endif
                        <?php
                        $index_permission = DB::table('permissions')->where('name', 'expenses-index')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($index_permission_active)
                            <li><a href="#expense" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-wallet"></i><span>{{trans('file.Expense')}}</span></a>
                                <ul id="expense" class="collapse list-unstyled ">
                                    <li id="exp-cat-menu"><a href="{{route('expense_categories.index')}}">{{trans('file.Expense Category')}}</a></li>
                                    @if(Auth::user()->role_id != 7)
                                        <li id="exp-list-menu"><a href="{{route('expenses.index')}}">{{trans('file.Expense List')}}</a></li>
                                    @endif
                                        <?php
                                        $add_permission = DB::table('permissions')->where('name', 'expenses-add')->first();
                                        $add_permission_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $add_permission->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($add_permission_active)
                                        <li><a id="add-expense" href=""> {{trans('file.Add Expense')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <?php
                        $index_permission = DB::table('permissions')->where('name', 'quotes-index')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($index_permission_active)
                            <li><a href="#quotation" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-document"></i><span>{{trans('file.Quotation')}}</span><span></a>
                                <ul id="quotation" class="collapse list-unstyled ">
                                    <li id="quotation-list-menu"><a href="{{route('quotations.index')}}">{{trans('file.Quotation List')}}</a></li>
                                        <?php
                                        $add_permission = DB::table('permissions')->where('name', 'quotes-add')->first();
                                        $add_permission_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $add_permission->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($add_permission_active)
                                        <li id="quotation-create-menu"><a href="{{route('quotations.create')}}">{{trans('file.Add Quotation')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        {{--                    fixed assets--}}
                        <?php
                        $index_permission = DB::table('permissions')->where('name', 'fixed_assets')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        $index_permission_report = DB::table('permissions')->where('name', 'fixed_assets_report')->first();
                        $index_permission_report_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission_report->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_index = DB::table('permissions')->where('name', 'asset-index')->first();
                        $asset_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_add = DB::table('permissions')->where('name', 'asset-add')->first();
                        $asset_add_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_add->id],
                            ['role_id', $role->id]
                        ])->first();

                        $donor_index = DB::table('permissions')->where('name', 'donor-index')->first();
                        $donor_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $donor_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $station_index = DB::table('permissions')->where('name', 'station-index')->first();
                        $station_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $station_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $region_index = DB::table('permissions')->where('name', 'region-index')->first();
                        $region_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $region_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_type_index = DB::table('permissions')->where('name', 'asset-type-index')->first();
                        $asset_type_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_type_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $activity_index = DB::table('permissions')->where('name', 'activity-index')->first();
                        $activity_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $activity_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_expense_index = DB::table('permissions')->where('name', 'asset-expense-index')->first();
                        $asset_expense_index_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_expense_index->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_sale = DB::table('permissions')->where('name', 'asset-sale')->first();
                        $asset_sale_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_sale->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_transfer = DB::table('permissions')->where('name', 'asset-transfer')->first();
                        $asset_transfer_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_transfer->id],
                            ['role_id', $role->id]
                        ])->first();

                        $asset_disppose = DB::table('permissions')->where('name', 'asset-disppose')->first();
                        $asset_disppose_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $asset_disppose->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($index_permission_active)
                            <li><a href="#assets" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-home"></i><span>{{trans('file.Fixed Assets')}}</span><span></a>
                                <ul id="assets" class="collapse list-unstyled ">
                                    @if($asset_index_active)<li id="assets-dashboard-menu"><a href="{{route('asset.dashboard')}}">{{trans('file.Assets Dashboard')}}</a></li>@endif
                                    @if($region_index_active)<li id="region-menu"><a href="{{route('region.index')}}">{{trans('file.Assets Region')}}</a></li>@endif
                                    @if($station_index_active)<li id="station-menu"><a href="{{route('station.index')}}">{{trans('file.Assets Station')}}</a></li>@endif
                                    @if($donor_index_active)<li id="donor-menu"><a href="{{route('donor.index')}}">{{trans('file.Assets Donor')}}</a></li>@endif
                                    @if($asset_type_index_active)<li id="assets-category-menu"><a href="{{route('assetCategory.index')}}">{{trans('file.Assets Type')}}</a></li>@endif
                                    @if($asset_add_active)<li id="assets-add-menu"><a href="{{route('asset.create')}}">{{trans('file.Add Assets')}}</a></li>@endif
                                    @if($asset_index_active)<li id="assets-list-menu"><a href="{{route('asset.index')}}">{{trans('file.Assets List')}}</a></li>@endif
                                    @if($activity_index_active)<li id="assets-activity-menu-repair"><a href="{{ route('asset.activity.repair') }}"> Repair Activity</a></li>@endif
                                    @if($activity_index_active)<li id="assets-activity-menu"><a href="{{ route('asset.activity') }}"> Asset Activity</a></li>@endif
                                    @if($asset_expense_index_active)<li id="assets-expense-menu"><a href="{{ route('asset.expense') }}"> Asset Expense</a></li>@endif
                                    @if($asset_disppose_active)<li id="assets-dispose-menu"><a href="{{ route('asset.dispose.list') }}"> Dispose Assets</a></li>@endif
                                    @if($asset_transfer_active)<li id="assets-transfer-menu"><a href="{{ route('asset.transfer.list') }}"> Transfer Assets</a></li>@endif
                                    @if($asset_sale_active)<li id="assets-sale-menu"><a href="{{ route('asset.sale.list') }}"> Asset Sales</a></li>@endif
                                    @if($index_permission_report_active)<li id="assets-report-menu"><a href="{{route('asset.report.dashboard')}}">{{trans('file.Assets Report')}}</a></li>@endif
                                    @if($index_permission_report_active)<li id="assets-report-menu"><a href="{{route('asset.report.category')}}">{{trans('file.Asset Category Report')}}</a></li>@endif
                                    @if($index_permission_report_active)<li id="assets-report-menu-refined"><a href="{{route('asset.report.category.new')}}">Refined Asset Category Report</a></li>@endif

                                </ul>
                            </li>
                        @endif
                        {{--                    end fixed assets--}}
                        <?php
                        $index_permission = DB::table('permissions')->where('name', 'transfers-index')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($index_permission_active)
                            <li><a href="#transfer" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-export"></i><span>{{trans('file.Transfer')}}</span></a>
                                <ul id="transfer" class="collapse list-unstyled ">
                                    <li id="transfer-list-menu"><a href="{{route('transfers.index')}}">{{trans('file.Transfer List')}}</a></li>
                                        <?php
                                        $add_permission = DB::table('permissions')->where('name', 'transfers-add')->first();
                                        $add_permission_active = DB::table('role_has_permissions')->where([
                                            ['permission_id', $add_permission->id],
                                            ['role_id', $role->id]
                                        ])->first();
                                        ?>
                                    @if($add_permission_active)
                                        <li id="transfer-create-menu"><a href="{{route('transfers.create')}}">{{trans('file.Add Transfer')}}</a></li>
                                        <li id="transfer-import-menu"><a href="{{url('transfers/transfer_by_csv')}}">{{trans('file.Import Transfer By CSV')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        <?php
                        $sale_return_index_permission = DB::table('permissions')->where('name', 'returns-index')->first();

                        $sale_return_index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $sale_return_index_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $purchase_return_index_permission = DB::table('permissions')->where('name', 'purchase-return-index')->first();

                        $purchase_return_index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $purchase_return_index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($sale_return_index_permission_active || $purchase_return_index_permission_active)
                            <li><a href="#return" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-return"></i><span>{{trans('file.return')}}</span></a>
                                <ul id="return" class="collapse list-unstyled ">
                                    @if($sale_return_index_permission_active)
                                        <li id="sale-return-menu"><a href="{{route('return-sale.index')}}">{{trans('file.Sale')}}</a></li>
                                    @endif
                                    @if($purchase_return_index_permission_active)
                                        <li id="purchase-return-menu"><a href="{{route('return-purchase.index')}}">{{trans('file.Purchase')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <?php
                        $department = DB::table('permissions')->where('name', 'department')->first();
                        $department_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $department->id],
                            ['role_id', $role->id]
                        ])->first();
                        $index_permission = DB::table('permissions')->where('name', 'account-index')->first();
                        $index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $money_transfer_permission = DB::table('permissions')->where('name', 'money-transfer')->first();
                        $money_transfer_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $money_transfer_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $balance_sheet_permission = DB::table('permissions')->where('name', 'balance-sheet')->first();
                        $balance_sheet_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $balance_sheet_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $account_statement_permission = DB::table('permissions')->where('name', 'account-statement')->first();
                        $account_statement_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $account_statement_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        $JE_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'JE-method'],
                                ['role_id', $role->id]])->first();

                        ?>
                        @if($index_permission_active || $balance_sheet_permission_active || $account_statement_permission_active || $money_transfer_permission_active)
                            <li class=""><a href="#account" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-briefcase"></i><span>{{trans('file.Accounting')}}</span></a>
                                <ul id="account" class="collapse list-unstyled ">
                                    @if($index_permission_active)
                                        <li id="account-list-menu"><a href="{{route('accounts.index')}}">{{trans('file.Account List')}}</a></li>
                                        @if($department_active)
                                            <li id="dept-menu"><a href="{{route('departments.index')}}">{{trans('file.Department')}}</a></li>
                                        @endif
                                        @if($JE_active)
                                            <li id="JE-menu">
                                                <a href="{{url('report/JE')}}">{{trans('file.JE Report')}}</a>
                                            </li>
                                        @endif
                                        <li><a id="add-account" href="">{{trans('file.Add Account')}}</a></li>
                                    @endif
                                    @if($money_transfer_permission_active)
                                        <li id="money-transfer-menu"><a href="{{route('money-transfers.index')}}">{{trans('file.Money Transfer')}}</a></li>
                                    @endif
                                    @if($balance_sheet_permission_active)
                                        <li id="balance-sheet-menu"><a href="{{route('accounts.balancesheet')}}">{{trans('file.Balance Sheet')}}</a></li>
                                    @endif
                                    @if($account_statement_permission_active)
                                        <li id="account-statement-menu"><a id="account-statement" href="">{{trans('file.Account Statement')}}</a></li>
                                    @endif
                                </ul>
                            </li>
                        @endif
                        <?php
                        $index_employee = DB::table('permissions')->where('name', 'employees-index')->first();
                        $index_employee_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $index_employee->id],
                            ['role_id', $role->id]
                        ])->first();
                        $attendance = DB::table('permissions')->where('name', 'attendance')->first();
                        $attendance_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $attendance->id],
                            ['role_id', $role->id]
                        ])->first();
                        $payroll = DB::table('permissions')->where('name', 'payroll')->first();
                        $payroll_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $payroll->id],
                            ['role_id', $role->id]
                        ])->first();
                        $hrm = DB::table('permissions')->where('name', 'hrm')->first();
                        $hrm_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $hrm->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($hrm_active)
                            @if(Auth::user()->role_id != 5)
                                <li class=""><a href="#hrm" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-user-group"></i><span>HRM</span></a>
                                    <ul id="hrm" class="collapse list-unstyled ">
                                        @if($department_active)
                                            <li id="dept-menu"><a href="{{route('departments.index')}}">{{trans('file.Department')}}</a></li>
                                        @endif
                                        @if($index_employee_active)
                                            <li id="employee-menu"><a href="{{route('employees.index')}}">{{trans('file.Employee')}}</a></li>
                                        @endif
                                        @if($attendance_active)
                                            <li id="attendance-menu"><a href="{{route('attendance.index')}}">{{trans('file.Attendance')}}</a></li>
                                        @endif
                                        @if($payroll_active)
                                            <li id="payroll-menu"><a href="{{route('payroll.index')}}">{{trans('file.Payroll')}}</a></li>
                                        @endif
                                        <li id="holiday-menu"><a href="{{route('holidays.index')}}">{{trans('file.Holiday')}}</a></li>
                                    </ul>
                                </li>
                            @endif
                        @endif
                        <?php
                        $user_index_permission_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'users-index'],
                                ['role_id', $role->id] ])->first();

                        $customer_index_permission = DB::table('permissions')->where('name', 'customers-index')->first();

                        $customer_index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $customer_index_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $biller_index_permission = DB::table('permissions')->where('name', 'billers-index')->first();

                        $biller_index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $biller_index_permission->id],
                            ['role_id', $role->id]
                        ])->first();

                        $supplier_index_permission = DB::table('permissions')->where('name', 'suppliers-index')->first();

                        $supplier_index_permission_active = DB::table('role_has_permissions')->where([
                            ['permission_id', $supplier_index_permission->id],
                            ['role_id', $role->id]
                        ])->first();
                        ?>
                        @if($user_index_permission_active || $customer_index_permission_active || $biller_index_permission_active || $supplier_index_permission_active)
                            <li><a href="#people" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-user"></i><span>{{trans('file.People')}}</span></a>
                                <ul id="people" class="collapse list-unstyled ">

                                    @if($user_index_permission_active)
                                        <li id="user-list-menu"><a href="{{route('user.index')}}">{{trans('file.User List')}}</a></li>
                                            <?php $user_add_permission_active = DB::table('permissions')
                                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                                            ->where([
                                                ['permissions.name', 'users-add'],
                                                ['role_id', $role->id] ])->first();
                                            ?>
                                        @if($user_add_permission_active)
                                            <li id="user-create-menu"><a href="{{route('user.create')}}">{{trans('file.Add User')}}</a></li>
                                        @endif
                                    @endif

                                    @if($customer_index_permission_active)
                                        <li id="customer-list-menu"><a href="{{route('customer.index')}}">{{trans('file.Customer List')}}</a></li>
                                            <?php
                                            $customer_add_permission = DB::table('permissions')->where('name', 'customers-add')->first();
                                            $customer_add_permission_active = DB::table('role_has_permissions')->where([
                                                ['permission_id', $customer_add_permission->id],
                                                ['role_id', $role->id]
                                            ])->first();
                                            ?>
                                        @if($customer_add_permission_active)
                                            <li id="customer-create-menu"><a href="{{route('customer.create')}}">{{trans('file.Add Customer')}}</a></li>
                                        @endif
                                    @endif

                                    @if($biller_index_permission_active)
                                        <li id="biller-list-menu"><a href="{{route('biller.index')}}">{{trans('file.Biller List')}}</a></li>
                                            <?php
                                            $biller_add_permission = DB::table('permissions')->where('name', 'billers-add')->first();
                                            $biller_add_permission_active = DB::table('role_has_permissions')->where([
                                                ['permission_id', $biller_add_permission->id],
                                                ['role_id', $role->id]
                                            ])->first();
                                            ?>
                                        @if($biller_add_permission_active)
                                            <li id="biller-create-menu"><a href="{{route('biller.create')}}">{{trans('file.Add Biller')}}</a></li>
                                        @endif
                                    @endif

                                    @if($supplier_index_permission_active)
                                        <li id="supplier-list-menu"><a href="{{route('supplier.index')}}">{{trans('file.Supplier List')}}</a></li>
                                            <?php
                                            $supplier_add_permission = DB::table('permissions')->where('name', 'suppliers-add')->first();
                                            $supplier_add_permission_active = DB::table('role_has_permissions')->where([
                                                ['permission_id', $supplier_add_permission->id],
                                                ['role_id', $role->id]
                                            ])->first();
                                            ?>
                                        @if($supplier_add_permission_active)
                                            <li id="supplier-create-menu"><a href="{{route('supplier.create')}}">{{trans('file.Add Supplier')}}</a></li>
                                        @endif
                                    @endif
                                </ul>
                            </li>
                        @endif

                        <?php
                        $profit_loss_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'profit-loss'],
                                ['role_id', $role->id] ])->first();
                        $best_seller_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'best-seller'],
                                ['role_id', $role->id] ])->first();
                        $warehouse_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'warehouse-report'],
                                ['role_id', $role->id] ])->first();
                        $warehouse_stock_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'warehouse-stock-report'],
                                ['role_id', $role->id] ])->first();
                        $product_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'product-report'],
                                ['role_id', $role->id] ])->first();
                        $daily_sale_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'daily-sale'],
                                ['role_id', $role->id] ])->first();
                        $monthly_sale_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'monthly-sale'],
                                ['role_id', $role->id]])->first();
                        $average_sale_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'average-report'],
                                ['role_id', $role->id]])->first();
                        $daily_purchase_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'daily-purchase'],
                                ['role_id', $role->id] ])->first();
                        $monthly_purchase_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'monthly-purchase'],
                                ['role_id', $role->id] ])->first();
                        $purchase_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'purchase-report'],
                                ['role_id', $role->id] ])->first();
                        $sale_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'sale-report'],
                                ['role_id', $role->id] ])->first();
                        $payment_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'payment-report'],
                                ['role_id', $role->id] ])->first();
                        $product_qty_alert_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'product-qty-alert'],
                                ['role_id', $role->id] ])->first();
                        $user_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'user-report'],
                                ['role_id', $role->id] ])->first();

                        $customer_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'customer-report'],
                                ['role_id', $role->id] ])->first();
                        $supplier_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'supplier-report'],
                                ['role_id', $role->id] ])->first();
                        $due_report_active = DB::table('permissions')
                            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                            ->where([
                                ['permissions.name', 'due-report'],
                                ['role_id', $role->id] ])->first();
                        ?>
                        @if($JE_active ||$average_sale_active || $profit_loss_active || $best_seller_active || $warehouse_report_active || $warehouse_stock_report_active || $product_report_active || $daily_sale_active || $monthly_sale_active || $daily_purchase_active || $monthly_purchase_active || $purchase_report_active || $sale_report_active || $payment_report_active || $product_qty_alert_active || $user_report_active || $customer_report_active || $supplier_report_active || $due_report_active)
                            <li><a href="#report" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-document-remove"></i><span>{{trans('file.Reports')}}</span></a>
                                <ul id="report" class="collapse list-unstyled ">
                                    @if($profit_loss_active)
                                        <li id="profit-loss-report-menu">
                                            {!! Form::open(['route' => 'report.profitLoss', 'method' => 'post', 'id' => 'profitLoss-report-form']) !!}
                                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                            <a id="profitLoss-link" href="">{{trans('file.Summary Report')}}</a>
                                            {!! Form::close() !!}
                                        </li>
                                    @endif
                                    @if($best_seller_active)
                                        <li id="best-seller-report-menu">
                                            <a href="{{url('report/best_seller')}}">{{trans('file.Best Seller')}}</a>
                                        </li>
                                    @endif
                                    @if($product_report_active)
                                        <li id="product-report-menu">
                                            {!! Form::open(['route' => 'report.product', 'method' => 'get', 'id' => 'product-report-form']) !!}
                                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                            <input type="hidden" name="warehouse_id" value="0" />
                                            <a id="report-link" href="">{{trans('file.Product Report')}}</a>
                                            {!! Form::close() !!}
                                        </li>
                                    @endif
                                    @if($product_report_active)
                                        <li id="category_active">
                                            <a href="{{route('report.category')}}">Category Report</a>
                                        </li>
                                    @endif
                                    @if($daily_sale_active)
                                        <li id="daily-sale-report-menu">
                                            <a href="{{url('report/daily_sale/'.date('Y').'/'.date('m'))}}">{{trans('file.Daily Sale')}}</a>
                                        </li>
                                    @endif
                                    @if($monthly_sale_active)
                                        <li id="monthly-sale-report-menu">
                                            <a href="{{url('report/monthly_sale/'.date('Y'))}}">{{trans('file.Monthly Sale')}}</a>
                                        </li>
                                    @endif
                                    @if($average_sale_active)
                                        <li id="average_sale_active-menu">
                                            <a href="{{url('report/average_sale')}}">{{trans('file.AMC Report')}}</a>
                                        </li>
                                    @endif
                                    @if($JE_active)
                                        <li id="JE-menu">
                                            <a href="{{url('report/JE')}}">{{trans('file.JE Report')}}</a>
                                        </li>
                                    @endif
                                    @if($daily_purchase_active)
                                        <li id="daily-purchase-report-menu">
                                            <a href="{{url('report/daily_purchase/'.date('Y').'/'.date('m'))}}">{{trans('file.Daily Purchase')}}</a>
                                        </li>
                                    @endif
                                    @if($monthly_purchase_active)
                                        <li id="monthly-purchase-report-menu">
                                            <a href="{{url('report/monthly_purchase/'.date('Y'))}}">{{trans('file.Monthly Purchase')}}</a>
                                        </li>
                                    @endif
                                    @if($sale_report_active)
                                        <li id="sale-report-menu">
                                            {!! Form::open(['route' => 'report.sale', 'method' => 'post', 'id' => 'sale-report-form']) !!}
                                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                            <input type="hidden" name="warehouse_id" value="0" />
                                            <a id="sale-report-link" href="">{{trans('file.Sale Report')}}</a>
                                            {!! Form::close() !!}
                                        </li>
                                    @endif
                                    @if($payment_report_active)
                                        <li id="payment-report-menu">
                                            {!! Form::open(['route' => 'report.paymentByDate', 'method' => 'post', 'id' => 'payment-report-form']) !!}
                                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                            <a id="payment-report-link" href="">{{trans('file.Payment Report')}}</a>
                                            {!! Form::close() !!}
                                        </li>
                                    @endif
                                    @if($purchase_report_active)
                                        <li id="purchase-report-menu">
                                            {!! Form::open(['route' => 'report.purchase', 'method' => 'post', 'id' => 'purchase-report-form']) !!}
                                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                            <input type="hidden" name="warehouse_id" value="0" />
                                            <a id="purchase-report-link" href="">{{trans('file.Purchase Report')}}</a>
                                            {!! Form::close() !!}
                                        </li>
                                    @endif
                                    @if($warehouse_report_active)
                                        <li id="warehouse-report-menu">
                                            <a id="warehouse-report-link" href="">{{trans('file.Warehouse Report')}}</a>
                                        </li>
                                    @endif
                                    @if($warehouse_stock_report_active)
                                        <li id="warehouse-stock-report-menu">
                                            <a href="{{route('report.warehouseStock')}}">{{trans('file.Warehouse Stock Chart')}}</a>
                                        </li>
                                    @endif
                                    @if($product_qty_alert_active)
                                        <li id="qtyAlert-report-menu">
                                            <a href="{{route('report.qtyAlert')}}">{{trans('file.Product Quantity Alert')}}</a>
                                        </li>
                                    @endif
                                    @if($user_report_active)
                                        <li id="user-report-menu">
                                            <a id="user-report-link" href="">{{trans('file.User Report')}}</a>
                                        </li>
                                    @endif
                                    @if($customer_report_active)
                                        <li id="customer-report-menu">
                                            <a id="customer-report-link" href="">{{trans('file.Customer Report')}}</a>
                                        </li>
                                    @endif
                                    @if($supplier_report_active)
                                        <li id="supplier-report-menu">
                                            <a id="supplier-report-link" href="">{{trans('file.Supplier Report')}}</a>
                                        </li>
                                    @endif
                                    @if($due_report_active)
                                        <li id="due-report-menu">
                                            {!! Form::open(['route' => 'report.dueByDate', 'method' => 'post', 'id' => 'due-report-form']) !!}
                                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                                            <a id="due-report-link" href="">{{trans('file.Due Report')}}</a>
                                            {!! Form::close() !!}
                                        </li>
                                        <li id="due-customer-report-menu">
                                            <a href="{{route('due-customer-report')}}">Creditor's Report</a>
                                        </li>
                                    @endif
                                </ul>
                            </li>
                        @endif

                        <li><a href="#setting" aria-expanded="false" data-toggle="collapse"> <i class="dripicons-gear"></i><span>{{trans('file.settings')}}</span></a>
                            <ul id="setting" class="collapse list-unstyled ">
                                <?php
                                $send_notification_permission = DB::table('permissions')->where('name', 'send_notification')->first();
                                $send_notification_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $send_notification_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $warehouse_permission = DB::table('permissions')->where('name', 'warehouse')->first();
                                $warehouse_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $warehouse_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $customer_group_permission = DB::table('permissions')->where('name', 'customer_group')->first();
                                $customer_group_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $customer_group_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $brand_permission = DB::table('permissions')->where('name', 'brand')->first();
                                $brand_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $brand_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $unit_permission = DB::table('permissions')->where('name', 'unit')->first();
                                $unit_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $unit_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $currency_permission = DB::table('permissions')->where('name', 'currency')->first();
                                $currency_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $currency_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $tax_permission = DB::table('permissions')->where('name', 'tax')->first();
                                $tax_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $tax_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $general_setting_permission = DB::table('permissions')->where('name', 'general_setting')->first();
                                $general_setting_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $general_setting_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $backup_database_permission = DB::table('permissions')->where('name', 'backup_database')->first();
                                $backup_database_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $backup_database_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $mail_setting_permission = DB::table('permissions')->where('name', 'mail_setting')->first();
                                $mail_setting_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $mail_setting_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $sms_setting_permission = DB::table('permissions')->where('name', 'sms_setting')->first();
                                $sms_setting_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $sms_setting_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $create_sms_permission = DB::table('permissions')->where('name', 'create_sms')->first();
                                $create_sms_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $create_sms_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $pos_setting_permission = DB::table('permissions')->where('name', 'pos_setting')->first();
                                $pos_setting_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $pos_setting_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $hrm_setting_permission = DB::table('permissions')->where('name', 'hrm_setting')->first();
                                $hrm_setting_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $hrm_setting_permission->id],
                                    ['role_id', $role->id]
                                ])->first();

                                $reward_point_setting_permission = DB::table('permissions')->where('name', 'reward_point_setting')->first();
                                $reward_point_setting_permission_active = DB::table('role_has_permissions')->where([
                                    ['permission_id', $reward_point_setting_permission->id],
                                    ['role_id', $role->id]
                                ])->first();
                                ?>
                                @if($role->name == 'Admin')
                                    <li id="role-menu"><a href="{{route('role.index')}}">{{trans('file.Role Permission')}}</a></li>
                                @endif
                                @if($send_notification_permission_active)
                                    <li id="notification-menu">
                                        <a href="" id="send-notification">{{trans('file.Send Notification')}}</a>
                                    </li>
                                @endif
                                @if($warehouse_permission_active)
                                    <li id="warehouse-menu"><a href="{{route('warehouse.index')}}">{{trans('file.Warehouse')}}</a></li>
                                @endif
                                @if($biller_index_permission_active)
                                    <li id="biller-list-menu"><a href="{{route('biller.index')}}">{{trans('file.Biller List')}}</a></li>
                                @endif
                                @if($customer_group_permission_active)
                                    <li id="customer-group-menu"><a href="{{route('customer_group.index')}}">{{trans('file.Customer Group')}}</a></li>
                                @endif
                                @if($brand_permission_active)
                                    <li id="brand-menu"><a href="{{route('brand.index')}}">{{trans('file.Brand')}}</a></li>
                                @endif
                                @if($unit_permission_active)
                                    <li id="unit-menu"><a href="{{route('unit.index')}}">{{trans('file.Unit')}}</a></li>
                                @endif
                                @if($currency_permission_active)
                                    <li id="currency-menu"><a href="{{route('currency.index')}}">{{trans('file.Currency')}}</a></li>
                                @endif
                                @if($tax_permission_active)
                                    <li id="tax-menu"><a href="{{route('tax.index')}}">{{trans('file.Tax')}}</a></li>
                                @endif
                                <li id="user-menu"><a href="{{route('user.profile', ['id' => Auth::id()])}}">{{trans('file.User Profile')}}</a></li>
                                @if($create_sms_permission_active)
                                    <li id="create-sms-menu"><a href="{{route('setting.createSms')}}">{{trans('file.Create SMS')}}</a></li>
                                @endif
                                @if($backup_database_permission_active)
                                    <li><a href="{{route('setting.backup')}}">{{trans('file.Backup Database')}}</a></li>
                                @endif
                                @if($general_setting_permission_active)
                                    <li id="general-setting-menu"><a href="{{route('setting.general')}}">{{trans('file.General Setting')}}</a></li>
                                    <li id="env-setting-menu"><a href="{{route('setting.env')}}">.env Settings</a></li>
                                @endif
                                @if($mail_setting_permission_active)
                                    <li id="mail-setting-menu"><a href="{{route('setting.mail')}}">{{trans('file.Mail Setting')}}</a></li>
                                @endif
                                @if($reward_point_setting_permission_active)
                                    <li id="reward-point-setting-menu"><a href="{{route('setting.rewardPoint')}}">{{trans('file.Reward Point Setting')}}</a></li>
                                @endif
                                @if($sms_setting_permission_active)
                                    <li id="sms-setting-menu"><a href="{{route('setting.sms')}}">{{trans('file.SMS Setting')}}</a></li>
                                @endif
                                @if($pos_setting_permission_active)
                                    <li id="pos-setting-menu"><a href="{{route('setting.pos')}}">POS {{trans('file.settings')}}</a></li>
                                @endif
                                @if($hrm_setting_permission_active)
                                    <li id="hrm-setting-menu"><a href="{{route('setting.hrm')}}"> {{trans('file.HRM Setting')}}</a></li>
                                @endif
                            </ul>
                        </li>
                    </ul>
                    @php $__sideMenuOrder = \App\Support\SiteMenu::sideOrder(); @endphp
                    <script>
                    (function () {
                        var order = @json($__sideMenuOrder);
                        var ul = document.getElementById('side-main-menu');
                        if (!ul || !order || !order.length) return;
                        function keyOf(li) {
                            var a = li.querySelector('a');
                            if (!a) return null;
                            if (a.getAttribute('data-nav-key')) return a.getAttribute('data-nav-key');
                            var href = a.getAttribute('href') || '';
                            if (href.charAt(0) === '#') return href.slice(1);
                            if (/\/admin\/site-content/.test(href)) return 'site-content';
                            if (/\/admin\/?$/.test(href)) return 'dashboard';
                            return null;
                        }
                        var map = {};
                        Array.prototype.slice.call(ul.children).forEach(function (li) {
                            if (li.tagName !== 'LI') return;
                            var k = keyOf(li);
                            if (k && !map[k]) map[k] = li;
                        });
                        order.forEach(function (k) { if (map[k]) ul.appendChild(map[k]); });
                    })();
                    </script>
                </div>
                <div class="sidebar-user-panel">
                    <div class="sidebar-user-meta">
                        <div class="sidebar-user-avatar">{{ $userInitial }}</div>
                        <div>
                            <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
                            <span class="sidebar-user-role">{{ ucfirst($role->name ?? 'User') }}</span>
                        </div>
                    </div>
                    <a href="{{route('user.profile', ['id' => Auth::id()])}}" class="sidebar-user-link">
                        <i class="dripicons-user"></i> My Profile
                    </a>
                    <a href="{{ route('logout') }}" class="sidebar-user-link logout-link"
                       onclick="event.preventDefault(); document.getElementById('sidebar-logout-form').submit();">
                        <i class="dripicons-power"></i> Sign Out
                    </a>
                    <form id="sidebar-logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
                        @csrf
                    </form>
                </div>
            </div>
          </nav>
        <!-- End Side Navbar -->
          <header class="header">
            <nav class="navbar">
              <div class="container-fluid">
                <div class="navbar-holder d-flex align-items-center justify-content-between">
                  <a id="toggle-btn" href="#" class="menu-btn"><i class="fa fa-bars"> </i></a>
                  <span class="brand-big">
                      <a href="{{url('/')}}"><h1 class="d-inline">{{$general_setting->site_title}}</h1></a>
                  </span>

                  <ul class="nav-menu list-unstyled d-flex flex-md-row align-items-md-center">
                    <?php
                      $add_permission = DB::table('permissions')->where('name', 'sales-add')->first();
                      $add_permission_active = DB::table('role_has_permissions')->where([
                          ['permission_id', $add_permission->id],
                          ['role_id', $role->id]
                      ])->first();

                      $empty_database_permission = DB::table('permissions')->where('name', 'empty_database')->first();
                      $empty_database_permission_active = DB::table('role_has_permissions')->where([
                          ['permission_id', $empty_database_permission->id],
                          ['role_id', $role->id]
                      ])->first();
                    ?>
                    @if($add_permission_active)
                    <li class="nav-item"><a class="dropdown-item btn-pos btn-sm" href="{{route('sale.pos')}}"><i class="dripicons-shopping-bag"></i><span> POS</span></a></li>
                    @endif
                    <li class="nav-item"><a id="btnFullscreen" data-toggle="tooltip" title="{{trans('file.Full Screen')}}"><i class="dripicons-expand"></i></a></li>
                    @if(\Auth::user()->role_id <= 2)
                      <li class="nav-item"><a href="{{route('cashRegister.index')}}" data-toggle="tooltip" title="{{trans('file.Cash Register List')}}"><i class="dripicons-archive"></i></a></li>
                    @endif
                    @if($product_qty_alert_active)
                      @if(($alert_product + count(\Auth::user()->unreadNotifications)) > 0)
                      <li class="nav-item" id="notification-icon">
                            <a rel="nofollow" data-toggle="tooltip" title="{{__('Notifications')}}" class="nav-link dropdown-item"><i class="dripicons-bell"></i><span class="badge badge-danger notification-number">{{$alert_product + count(\Auth::user()->unreadNotifications)}}</span>
                            </a>
                            <ul class="right-sidebar">
                                <li class="notifications">
                                  <a href="{{route('report.qtyAlert')}}" class="btn btn-link"> {{$alert_product}} product exceeds alert quantity</a>
                                </li>
                                @foreach(\Auth::user()->unreadNotifications as $key => $notification)
                                    <li class="notifications">
                                        @if(!empty($notification->data['link']))
                                            <a href="{{ $notification->data['link'] }}" class="btn btn-link">{{ $notification->data['message'] }}</a>
                                        @else
                                            <a href="#" class="btn btn-link">{{ $notification->data['message'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                      </li>
                      @elseif(count(\Auth::user()->unreadNotifications) > 0)
                      <li class="nav-item" id="notification-icon">
                            <a rel="nofollow" data-toggle="tooltip" title="{{__('Notifications')}}" class="nav-link dropdown-item"><i class="dripicons-bell"></i><span class="badge badge-danger notification-number">{{count(\Auth::user()->unreadNotifications)}}</span>
                            </a>
                            <ul class="right-sidebar">
                                @foreach(\Auth::user()->unreadNotifications as $key => $notification)
                                    <li class="notifications">
                                        @if(!empty($notification->data['link']))
                                            <a href="{{ $notification->data['link'] }}" class="btn btn-link">{{ $notification->data['message'] }}</a>
                                        @else
                                            <a href="#" class="btn btn-link">{{ $notification->data['message'] }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                      </li>
                      @endif
                    @endif
                    <li class="nav-item">
                          <a rel="nofollow" data-toggle="tooltip" class="nav-link dropdown-item"><i class="dripicons-web"></i></a>
                          <ul class="right-sidebar">
                              <li>
                                <a href="{{ url('language_switch/en') }}" class="btn btn-link"> English</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/es') }}" class="btn btn-link"> Español</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/ar') }}" class="btn btn-link"> عربى</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/pt_BR') }}" class="btn btn-link"> Portuguese</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/fr') }}" class="btn btn-link"> Français</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/de') }}" class="btn btn-link"> Deutsche</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/id') }}" class="btn btn-link"> Malay</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/hi') }}" class="btn btn-link"> हिंदी</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/vi') }}" class="btn btn-link"> Tiếng Việt</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/ru') }}" class="btn btn-link"> русский</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/bg') }}" class="btn btn-link"> български</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/tr') }}" class="btn btn-link"> Türk</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/it') }}" class="btn btn-link"> Italiano</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/nl') }}" class="btn btn-link"> Nederlands</a>
                              </li>
                              <li>
                                <a href="{{ url('language_switch/lao') }}" class="btn btn-link"> Lao</a>
                              </li>
                          </ul>
                    </li>
                    <li class="nav-item">
                      <a rel="nofollow" data-target="#" href="#" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" class="nav-link dropdown-item"><i class="dripicons-user"></i> <span>{{ucfirst(Auth::user()->name)}}</span> <i class="fa fa-angle-down"></i>
                      </a>
                      <ul class="right-sidebar">
                          <li>
                            <a href="{{route('user.profile', ['id' => Auth::id()])}}"><i class="dripicons-user"></i> {{trans('file.profile')}}</a>
                          </li>
                          @if($general_setting_permission_active)
                          <li>
                            <a href="{{route('setting.general')}}"><i class="dripicons-gear"></i> {{trans('file.settings')}}</a>
                          </li>
                          @endif
                          <li>
                            <a href="{{url('my-transactions/'.date('Y').'/'.date('m'))}}"><i class="dripicons-swap"></i> {{trans('file.My Transaction')}}</a>
                          </li>
                          @if(Auth::user()->role_id != 5)
                          <li>
                            <a href="{{url('holidays/my-holiday/'.date('Y').'/'.date('m'))}}"><i class="dripicons-vibrate"></i> {{trans('file.My Holiday')}}</a>
                          </li>
                          @endif
                          @if($empty_database_permission_active)
                          <li>
                            <a onclick="return confirm('Are you sure want to delete? If you do this all of your data will be lost.')" href="{{route('setting.emptyDatabase')}}"><i class="dripicons-stack"></i> {{trans('file.Empty Database')}}</a>
                          </li>
                          @endif
                      </ul>
                    </li>
                  </ul>
                </div>
              </div>
            </nav>
          </header>
        <div class="page">

          <!-- notification modal -->
          <div id="notification-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Send Notification')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'notifications.store', 'method' => 'post']) !!}
                          <div class="row">
                              <?php
                                  $lims_user_list = DB::table('users')->where([
                                    ['is_active', true],
                                    ['id', '!=', \Auth::user()->id]
                                  ])->get();
                              ?>
                              <div class="col-md-6 form-group">
                                  <label>{{trans('file.User')}} *</label>
                                  <select name="user_id" class="selectpicker form-control" required data-live-search="true"   title="Select user...">
                                      @foreach($lims_user_list as $user)
                                      <option value="{{$user->id}}">{{$user->name . ' (' . $user->email. ')'}}</option>
                                      @endforeach
                                  </select>
                              </div>
                              <div class="col-md-12 form-group">
                                  <label>{{trans('file.Message')}} *</label>
                                  <textarea rows="5" name="message" class="form-control" required></textarea>
                              </div>
                          </div>
                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end notification modal -->

          <!-- expense modal -->
          <div id="expense-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Expense')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'expenses.store', 'method' => 'post']) !!}
                        <?php
                          $lims_expense_category_list = DB::table('expense_categories')->where('is_active', true)->get();
                          $lims_products_category_list = DB::table('categories')->where('is_active', true)->get();
                          if(Auth::user()->role_id > 2)
                            $lims_warehouse_list = DB::table('warehouses')->where([
                              ['is_active', true],
                              ['id', Auth::user()->warehouse_id]
                            ])->get();
                          else
                            $lims_warehouse_list = DB::table('warehouses')->where('is_active', true)->get();
                          $lims_account_list = \App\Account::where('is_active', true)->get();
                          $lims_department_list = \App\Department::where('is_active', true)->get();

                        ?>
                          <div class="row">
                            <div class="col-md-6 form-group">
                                <label>{{trans('file.Expense Category')}} *</label>
                                <select name="expense_category_id" class="selectpicker form-control" required data-live-search="true"   title="Select Expense Category...">
                                    @foreach($lims_expense_category_list as $expense_category)
                                    <option value="{{$expense_category->id}}">{{$expense_category->name . ' (' . $expense_category->code. ')'}}</option>
                                    @endforeach
                                </select>
                            </div>
                          <div class="col-md-6 form-group">
                              <label>{{trans('file.Product Category')}} *</label>
                              <select name="category_id" class="selectpicker form-control" required data-live-search="true"   title="Select Products Category...">
                                  @foreach($lims_products_category_list as $expense_category)
                                      <option value="{{$expense_category->id}}">{{$expense_category->name}}</option>
                                  @endforeach
                              </select>
                          </div>
                            <div class="col-md-6 form-group">
                                <label>{{trans('file.Warehouse')}} *</label>
                                <select name="warehouse_id" class="selectpicker form-control" required data-live-search="true"   title="Select Warehouse...">
                                    @foreach($lims_warehouse_list as $warehouse)
                                    <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label>{{trans('file.Amount')}} *</label>
                                <input type="number" name="amount" step="any" required class="form-control">
                            </div>
                            <div class="col-md-6 form-group">
                                <label> {{trans('file.Account')}}</label>
                                <select class="form-control selectpicker" name="account_id">
                                @foreach($lims_account_list as $account)
                                    @if($account->is_default)
                                    <option selected value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                    @else
                                    <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                    @endif
                                @endforeach
                                </select>
                            </div>
                          </div>
                          <div class="form-group">
                              <label>{{trans('file.Note')}}</label>
                              <textarea name="note" rows="3" class="form-control"></textarea>
                          </div>
                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end expense modal -->

      <!-- account modal -->
      <div id="account-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
        <div role="document" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Account')}}</h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                </div>
                <div class="modal-body">
                  <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                    {!! Form::open(['route' => 'accounts.store', 'method' => 'post']) !!}
                      <div class="form-group">
                          <label>{{trans('file.Account No')}} *</label>
                          <input type="text" name="account_no" required class="form-control">
                      </div>

                    <div class="form-group">
                        <label>{{trans('file.Department')}} *</label>
                        <select class="form-control selectpicker" name="department_id" data-live-search="true" required>
                                <option value="0">Choose department</option>
                            @foreach($lims_department_list as $account)
                                <option value="{{$account->id}}">{{$account->name}} - {{$account->code}}</option>
                            @endforeach
                        </select>
                    </div>
                      <div class="form-group">
                          <label>{{trans('file.name')}} *</label>
                          <input type="text" name="name" required class="form-control">
                      </div>
                      <div class="form-group">
                          <label>{{trans('file.Initial Balance')}}</label>
                          <input type="number" name="initial_balance" step="any" class="form-control">
                      </div>
                      <div class="form-group">
                          <label>{{trans('file.Note')}}</label>
                          <textarea name="note" rows="3" class="form-control"></textarea>
                      </div>
                      <div class="form-group">
                          <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                      </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>
      </div>
      <!-- end account modal -->

          <!-- account statement modal -->
          <div id="account-statement-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Account Statement')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'accounts.statement', 'method' => 'post']) !!}
                          <div class="row">
                            <div class="col-md-6 form-group">
                                <label> {{trans('file.Account')}}</label>
                                <select class="form-control selectpicker" name="account_id">
                                @foreach($lims_account_list as $account)
                                    <option value="{{$account->id}}">{{$account->name}} [{{$account->account_no}}]</option>
                                @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label> {{trans('file.Type')}}</label>
                                <select class="form-control selectpicker" name="type">
                                    <option value="0">{{trans('file.All')}}</option>
                                    <option value="1">{{trans('file.Debit')}}</option>
                                    <option value="2">{{trans('file.Credit')}}</option>
                                </select>
                            </div>
                            <div class="col-md-12 form-group">
                                <label>{{trans('file.Choose Your Date')}}</label>
                                <div class="input-group">
                                    <input type="text" class="daterangepicker-field form-control" required />
                                    <input type="hidden" name="start_date" />
                                    <input type="hidden" name="end_date" />
                                </div>
                            </div>
                          </div>
                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end account statement modal -->

          <!-- warehouse modal -->
          <div id="warehouse-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Warehouse Report')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'report.warehouse', 'method' => 'post']) !!}
                        <?php
                          $lims_warehouse_list = DB::table('warehouses')->where('is_active', true)->get();
                        ?>
                          <div class="form-group">
                              <label>{{trans('file.Warehouse')}} *</label>
                              <select name="warehouse_id" class="selectpicker form-control" required data-live-search="true" id="warehouse-id"   title="Select warehouse...">
                                  @foreach($lims_warehouse_list as $warehouse)
                                  <option value="{{$warehouse->id}}">{{$warehouse->name}}</option>
                                  @endforeach
                              </select>
                          </div>

                          <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                          <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end warehouse modal -->

          <!-- user modal -->
          <div id="user-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.User Report')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'report.user', 'method' => 'post']) !!}
                        <?php
                          $lims_user_list = DB::table('users')->where('is_active', true)->get();
                        ?>
                          <div class="form-group">
                              <label>{{trans('file.User')}} *</label>
                              <select name="user_id" class="selectpicker form-control" required data-live-search="true" id="user-id"   title="Select user...">
                                  @foreach($lims_user_list as $user)
                                  <option value="{{$user->id}}">{{$user->name . ' (' . $user->phone. ')'}}</option>
                                  @endforeach
                              </select>
                          </div>

                          <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                          <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end user modal -->

          <!-- customer modal -->
          <div id="customer-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Customer Report')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'report.customer', 'method' => 'post']) !!}
                        <?php
                          $lims_customer_list = DB::table('customers')->where('is_active', true)->get();
                        ?>
                          <div class="form-group">
                              <label>{{trans('file.customer')}} *</label>
                              <select name="customer_id" class="selectpicker form-control" required data-live-search="true" id="customer-id"   title="Select customer...">
                                  @foreach($lims_customer_list as $customer)
                                  <option value="{{$customer->id}}">{{$customer->name . ' (' . $customer->phone_number. ')'}}</option>
                                  @endforeach
                              </select>
                          </div>

                          <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                          <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end customer modal -->

          <!-- supplier modal -->
          <div id="supplier-modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Supplier Report')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                      <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'report.supplier', 'method' => 'post']) !!}
                        <?php
                          $lims_supplier_list = DB::table('suppliers')->where('is_active', true)->get();
                        ?>
                          <div class="form-group">
                              <label>{{trans('file.Supplier')}} *</label>
                              <select name="supplier_id" class="selectpicker form-control" required data-live-search="true" id="supplier-id"   title="Select Supplier...">
                                  @foreach($lims_supplier_list as $supplier)
                                  <option value="{{$supplier->id}}">{{$supplier->name . ' (' . $supplier->phone_number. ')'}}</option>
                                  @endforeach
                              </select>
                          </div>

                          <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                          <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />

                          <div class="form-group">
                              <button type="submit" class="btn btn-primary">{{trans('file.submit')}}</button>
                          </div>
                        {{ Form::close() }}
                    </div>
                </div>
            </div>
          </div>
          <!-- end supplier modal -->

          <div style="display:none" id="content" class="animate-bottom">
              <div class="container-fluid">
                  <div id="beyond-module-tabs" class="beyond-module-tabs">
                      <div id="beyond-module-tabs-label" class="beyond-module-tabs-label"></div>
                      <div id="beyond-module-tabs-nav" class="beyond-module-tabs-nav"></div>
                  </div>
              </div>
              @yield('content')
          </div>

          <footer class="main-footer">
            <div class="container-fluid">
              <div class="row">
                <div class="col-sm-12">
                  <p>&copy; {{$general_setting->site_title}} | {{trans('file.Developed')}} {{trans('file.By')}} <span class="external">{{$general_setting->developed_by}}</span> <span class="text-muted">| v{{ \App\Support\AppVersion::label() }}</span></p>
                </div>
              </div>
            </div>
          </footer>
        </div>
        @yield('scripts')
        @include('components.image_paste_script')
        <script>
            if ('serviceWorker' in navigator ) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('{{ url('service-worker.js') }}').then(function(registration) {
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

          var alert_product = <?php echo json_encode($alert_product) ?>;

          if ($(window).outerWidth() > 1199) {
              $('nav.side-navbar').removeClass('shrink');
          }
          function myFunction() {
              setTimeout(showPage, 150);
          }
          function showPage() {
            var loader = document.getElementById("loader");
            var content = document.getElementById("content");
            if (loader) loader.style.display = "none";
            if (content) content.style.display = "block";
          }
          setTimeout(showPage, 3000);

          if ($('meta[name="csrf-token"]').length) {
              $.ajaxSetup({
                  headers: {
                      'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                  }
              });
          }

          function saveValue(e) {
              if (e && e.id) {
                  localStorage.setItem(e.id, e.value);
              }
          }

          $("div.alert").delay(3000).slideUp(750);

          function confirmDelete() {
              if (confirm("Are you sure want to delete?")) {
                  return true;
              }
              return false;
          }

          $("li#notification-icon").on("click", function (argument) {
              $.get('notifications/mark-as-read', function(data) {
                  $("span.notification-number").text(alert_product);
              });
          });

          $("a#add-expense").click(function(e){
            e.preventDefault();
            $('#expense-modal').modal();
          });

          $("a#send-notification").click(function(e){
            e.preventDefault();
            $('#notification-modal').modal();
          });

          $("a#add-account").click(function(e){
            e.preventDefault();
            $('#account-modal').modal();
          });

          $("a#account-statement").click(function(e){
            e.preventDefault();
            $('#account-statement-modal').modal();
          });

          $("a#profitLoss-link").click(function(e){
            e.preventDefault();
            $("#profitLoss-report-form").submit();
          });

          $("a#report-link").click(function(e){
            e.preventDefault();
            $("#product-report-form").submit();
          });

          $("a#report-link-category").click(function(e){
            e.preventDefault();
            $("#category-report-form").submit();
          });

          $("a#purchase-report-link").click(function(e){
            e.preventDefault();
            $("#purchase-report-form").submit();
          });

          $("a#sale-report-link").click(function(e){
            e.preventDefault();
            $("#sale-report-form").submit();
          });

          $("a#payment-report-link").click(function(e){
            e.preventDefault();
            $("#payment-report-form").submit();
          });

          $("a#warehouse-report-link").click(function(e){
            e.preventDefault();
            $('#warehouse-modal').modal();
          });

          $("a#user-report-link").click(function(e){
            e.preventDefault();
            $('#user-modal').modal();
          });

          $("a#customer-report-link").click(function(e){
            e.preventDefault();
            $('#customer-modal').modal();
          });

          $("a#supplier-report-link").click(function(e){
            e.preventDefault();
            $('#supplier-modal').modal();
          });

          $("a#due-report-link").click(function(e){
            e.preventDefault();
            $("#due-report-form").submit();
          });

          $(".daterangepicker-field").daterangepicker({
              callback: function(startDate, endDate, period){
                var start_date = startDate.format('YYYY-MM-DD');
                var end_date = endDate.format('YYYY-MM-DD');
                var title = start_date + ' To ' + end_date;
                $(this).val(title);
                $('#account-statement-modal input[name="start_date"]').val(start_date);
                $('#account-statement-modal input[name="end_date"]').val(end_date);
              }
          });

          $('.selectpicker').selectpicker({
              style: 'btn-link',
          });

          (function () {
              function isCustomerSelect($select) {
                  var name = ($select.attr('name') || '').toLowerCase();
                  var id = ($select.attr('id') || '').toLowerCase();
                  return name.indexOf('customer') !== -1 || id.indexOf('customer') !== -1 || $select.hasClass('customer-type-search');
              }

              if (!$('#customer-type-search-style').length) {
                  $('head').append('<style id="customer-type-search-style">.customer-search-empty .dropdown-menu li:not(.bs-searchbox):not(.no-results){display:none!important;}.customer-search-empty .bs-searchbox input::placeholder{color:#6c757d;}</style>');
              }

              $(document).on('shown.bs.select', 'select.selectpicker', function () {
                  var $select = $(this);
                  if (!isCustomerSelect($select)) {
                      return;
                  }
                  var $input = $select.closest('.bootstrap-select').find('.bs-searchbox input');
                  if ($input.length && !$input.attr('placeholder')) {
                      $input.attr('placeholder', 'Type name or phone to search...');
                  }
              });

              $(document).on('show.bs.select', 'select.selectpicker', function () {
                  var $select = $(this);
                  if (!isCustomerSelect($select)) {
                      return;
                  }
                  var $wrapper = $select.closest('.bootstrap-select');
                  $wrapper.addClass('customer-search-empty');
                  setTimeout(function () {
                      $wrapper.find('.bs-searchbox input').val('').trigger('keyup');
                  }, 0);
              });

              $(document).on('hidden.bs.select', 'select.selectpicker', function () {
                  $(this).closest('.bootstrap-select').removeClass('customer-search-empty');
              });

              $(document).on('keyup input', '.bs-searchbox input', function () {
                  var term = $(this).val().trim();
                  var $wrapper = $(this).closest('.bootstrap-select');
                  var $select = $wrapper.find('select.selectpicker');
                  if (!$select.length || !isCustomerSelect($select)) {
                      return;
                  }
                  if (term.length === 0) {
                      $wrapper.addClass('customer-search-empty');
                  } else {
                      $wrapper.removeClass('customer-search-empty');
                  }
              });
          })();

          function numberWithCommas(x) {
              return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
          }

          (function () {
              var tabIconMap = {
                  'category-menu': 'dripicons-tags',
                  'product-list-menu': 'dripicons-view-list',
                  'product-create-menu': 'dripicons-plus',
                  'printBarcode-menu': 'dripicons-print',
                  'adjustment-list-menu': 'dripicons-document',
                  'adjustment-create-menu': 'dripicons-document-edit',
                  'stock-count-menu': 'dripicons-checklist',
                  'purchase-list-menu': 'dripicons-cart',
                  'purchase-create-menu': 'dripicons-plus',
                  'purchase-import-menu': 'dripicons-download',
                  'sale-list-menu': 'dripicons-view-list',
                  'sale-create-menu': 'dripicons-plus',
                  'sale-import-menu': 'dripicons-download',
                  'booking-create-menu': 'dripicons-plus',
                  'booking-index-menu': 'dripicons-view-list',
                  'booking-reminders-menu': 'dripicons-bell',
                  'booking-awaiting-menu': 'dripicons-pencil',
                  'booking-pending-menu': 'dripicons-hourglass',
                  'booking-signed-menu': 'dripicons-checkmark',
                  'booking-goods-received-menu': 'dripicons-archive',
                  'booking-product-menu': 'dripicons-basket',
                  'booking-report-menu': 'dripicons-graph-line',
                  'role-menu': 'dripicons-lock',
                  'warehouse-menu': 'dripicons-home',
                  'biller-list-menu': 'dripicons-user-id',
                  'customer-group-menu': 'dripicons-user-group',
                  'brand-menu': 'dripicons-star',
                  'unit-menu': 'dripicons-scale',
                  'currency-menu': 'dripicons-wallet',
                  'tax-menu': 'dripicons-percent',
                  'user-menu': 'dripicons-user',
                  'create-sms-menu': 'dripicons-message',
                  'general-setting-menu': 'dripicons-gear',
                  'env-setting-menu': 'dripicons-document-edit',
                  'mail-setting-menu': 'dripicons-mail',
                  'reward-point-setting-menu': 'dripicons-star',
                  'sms-setting-menu': 'dripicons-message',
                  'pos-setting-menu': 'dripicons-cart',
                  'hrm-setting-menu': 'dripicons-user-group',
                  'notification-menu': 'dripicons-bell'
              };

              function resolveTabIcon($link, $parentLink) {
                  var liId = $link.closest('li').attr('id');
                  if (liId && tabIconMap[liId]) {
                      return tabIconMap[liId];
                  }

                  var parentIcon = $parentLink.find('i').first().attr('class');
                  if (parentIcon) {
                      var classes = parentIcon.split(' ').filter(function (cls) {
                          return cls.indexOf('dripicons-') === 0 || cls.indexOf('fa-') === 0;
                      });
                      if (classes.length) {
                          return classes[0];
                      }
                  }

                  return 'dripicons-link';
              }

              function buildModuleTabs() {
                  var $activeItem = $('#side-main-menu ul.collapse li.active').first();
                  if (!$activeItem.length) {
                      $('#beyond-module-tabs').removeClass('is-visible');
                      return;
                  }

                  var $submenu = $activeItem.closest('ul.collapse');
                  var $parentLink = $submenu.siblings('a').first();
                  var $parentLi = $submenu.closest('li');
                  var parentLabel = $.trim($parentLink.find('span').first().text()) || $.trim($parentLink.text());
                  var $nav = $('#beyond-module-tabs-nav');
                  var $tabsWrap = $('#beyond-module-tabs');

                  $parentLi.children('a').addClass('menu-parent-active').attr('aria-expanded', 'true');
                  $('#beyond-module-tabs-label').text(parentLabel);
                  $nav.empty();

                  $submenu.find('> li > a').each(function (index) {
                      var $link = $(this);
                      var href = $link.attr('href');
                      if (!href || href === '#' || href.indexOf('javascript') === 0) {
                          return;
                      }

                      var label = $.trim($link.text());
                      if (!label) {
                          return;
                      }

                      var tones = ['tone-blue', 'tone-gold', 'tone-purple', 'tone-pink', 'tone-green', 'tone-orange', 'tone-teal', 'tone-red'];
                      var toneClass = tones[index % tones.length];
                      var isActive = $link.closest('li').hasClass('active');
                      var iconClass = resolveTabIcon($link, $parentLink);
                      var $tab = $('<a>', {
                          'class': 'beyond-module-tab ' + toneClass + (isActive ? ' is-active' : ''),
                          'href': href
                      });
                      $tab.append($('<i>', { 'class': iconClass }));
                      $tab.append($('<span>').text(label));

                      $nav.append($tab);
                  });

                  if ($nav.children().length) {
                      $tabsWrap.addClass('is-visible');
                  } else {
                      $tabsWrap.removeClass('is-visible');
                  }
              }

              $('#side-main-menu > li > a[data-toggle="collapse"]').each(function () {
                  $(this).removeAttr('data-toggle').removeAttr('aria-expanded');
              });

              $('#sidebar-toggle-btn').on('click', function (e) {
                  e.preventDefault();
                  $('#toggle-btn').trigger('click');
              });

              $('#side-main-menu > li > a[href^="#"]').on('click', function (e) {
                  e.preventDefault();
                  var target = $(this).attr('href');
                  var $firstLink = $(target).find('> li > a[href]').filter(function () {
                      var href = $(this).attr('href');
                      return href && href !== '#' && href.indexOf('javascript') !== 0;
                  }).first();

                  if ($firstLink.length) {
                      window.location.href = $firstLink.attr('href');
                  }
              });

              buildModuleTabs();
              $(document).ready(buildModuleTabs);
          })();
        </script>
      </body>
    </html>
