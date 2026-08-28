/*global $, document, Chart, LINECHART, data, options, window*/
$(document).ready(function () {

    'use strict';

    // ------------------------------------------------------- //
    // full screen button
    // ------------------------------------------------------ //

    function toggleFullscreen(elem) {
        elem = elem || document.documentElement;
        var canNative = elem.requestFullscreen || elem.msRequestFullscreen || elem.mozRequestFullScreen || elem.webkitRequestFullscreen;
        if (!canNative) {
            document.body.classList.toggle('app-focus-mode');
            return;
        }
        if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                if (elem.requestFullscreen) {
                    elem.requestFullscreen();
                } else if (elem.msRequestFullscreen) {
                    elem.msRequestFullscreen();
                } else if (elem.mozRequestFullScreen) {
                    elem.mozRequestFullScreen();
                } else if (elem.webkitRequestFullscreen) {
                    elem.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
                }
            } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }
    }
    if ($('#btnFullscreen').length > 0) {
        $('#btnFullscreen').on('click', function (e) {
            e.preventDefault();
            toggleFullscreen();
        });
    }

    //Custom select
    $('select').selectpicker();
    
    $('[data-toggle="tooltip"]').tooltip();

    // Main Template Color
    var brandPrimary = '#33b35a';

    // ------------------------------------------------------- //
    // Custom Scrollbar
    // ------------------------------------------------------ //

    if ($(window).outerWidth() > 1199) {
        $("nav.side-navbar,.table-container,.transaction-list,.right-sidebar").mCustomScrollbar({
            theme: "light",
            scrollInertia: 200
        });
    }

    // Desktop: sidebar flush with the viewport. Mobile CSS overrides top/height
    // so the hamburger in the header stays tappable.
    if ($(window).outerWidth() > 1199) {
        $('nav.side-navbar').css({ top: '0', height: '100vh' });
    }

    // ------------------------------------------------------- //
    // Side Navbar Functionality (drawer on phones / tablets)
    // ------------------------------------------------------ //

    function isMobileNav() {
        return $(window).outerWidth() <= 1199;
    }

    function closeMobileSidebar() {
        $('nav.side-navbar').removeClass('is-open show-sm');
        $('#sidebar-backdrop, .sidebar-backdrop').removeClass('is-visible');
        $('body').removeClass('sidebar-open');
        $('#toggle-btn').attr('aria-expanded', 'false');
    }

    function openMobileSidebar() {
        $('nav.side-navbar').addClass('is-open').removeClass('shrink');
        $('#sidebar-backdrop, .sidebar-backdrop').addClass('is-visible');
        $('body').addClass('sidebar-open');
        $('#toggle-btn').attr('aria-expanded', 'true');
    }

    $('#toggle-btn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!isMobileNav()) {
            $('nav.side-navbar').toggleClass('shrink');
            $('.page').toggleClass('active');
            return;
        }

        if ($('nav.side-navbar').hasClass('is-open')) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    });

    $(document).on('click', '#sidebar-backdrop, .sidebar-backdrop, #sidebar-close-btn', function (e) {
        e.preventDefault();
        closeMobileSidebar();
    });

    $(document).on('click', 'nav.side-navbar a[href]', function () {
        if (!isMobileNav()) {
            return;
        }
        var href = $(this).attr('href') || '';
        if (!href || href === '#' || href.indexOf('javascript:') === 0) {
            return;
        }
        closeMobileSidebar();
    });

    $(window).on('resize', function () {
        if (!isMobileNav()) {
            closeMobileSidebar();
            $('nav.side-navbar').css({ top: '0', height: '100vh' });
        } else {
            $('nav.side-navbar').css({ top: '', height: '' });
            if (!$('nav.side-navbar').hasClass('is-open')) {
                closeMobileSidebar();
            }
        }
    });
    
    // ------------------------------------------------------- //
    // Header Dropdown / Right Sidebar
    // ------------------------------------------------------ //
    $('header .dropdown-item').on('click', function(){
        $('.right-sidebar.open').removeClass('open');
        $(this).siblings('.right-sidebar').addClass('open');
        $('.page').on('click', function(){
            $('.right-sidebar.open').removeClass('open');
        })
    });


    // ------------------------------------------------------- //
    // Login  form validation
    // ------------------------------------------------------ //
    $('#login-form').validate({
        messages: {
            loginUsername: 'please enter your username',
            loginPassword: 'please enter your password'
        }
    });

    // ------------------------------------------------------- //
    // Register form validation
    // ------------------------------------------------------ //
    $('#register-form').validate({
        messages: {
            registerUsername: 'please enter your first name',
            registerEmail: 'please enter a vaild Email Address',
            registerPassword: 'please enter your password'
        }
    });

    // ------------------------------------------------------- //
    // Jquery Progress Circle
    // ------------------------------------------------------ //
    var progress_circle = $("#progress-circle").gmpc({
        color: brandPrimary,
        line_width: 5,
        percent: 80
    });
    progress_circle.gmpc('animate', 80, 3000);

    // ------------------------------------------------------- //
    // External links to new window
    // ------------------------------------------------------ //

    $('.external').on('click', function (e) {

        e.preventDefault();
        window.open($(this).attr("href"));
    });

    // ------------------------------------------------------ //
    // For demo purposes, can be deleted
    // ------------------------------------------------------ //

    var stylesheet = $('link#theme-stylesheet');
    $( "<link id='new-stylesheet' rel='stylesheet'>" ).insertAfter(stylesheet);
    var alternateColour = $('link#new-stylesheet');

    if ($.cookie("theme_csspath")) {
        alternateColour.attr("href", $.cookie("theme_csspath"));
    }

    $("#colour").change(function () {

        if ($(this).val() !== '') {

            var theme_csspath = 'css/style.' + $(this).val() + '.css';

            alternateColour.attr("href", theme_csspath);

            $.cookie("theme_csspath", theme_csspath, { expires: 365, path: document.URL.substr(0, document.URL.lastIndexOf('/')) });

        }

        return false;
    });

    $('.periods li').on('click', function(){
        $('.decade-select').addClass('hidden');
        $('.month-select').removeClass('hidden');
        $('.year-select').removeClass('hidden');
    });

    $('.periods li:nth-child(5)').on('click', function(){
        $('.decade-select').removeClass('hidden');
        $('.month-select').addClass('hidden');
        $('.year-select').addClass('hidden');
    });

    $('.periods li:nth-child(3), .periods li:nth-child(4)').on('click', function(){
        $('.decade-select').addClass('hidden');
        $('.month-select').addClass('hidden');
        $('.year-select').removeClass('hidden');
    });

});
