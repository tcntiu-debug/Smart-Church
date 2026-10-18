<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'TCN Ikorodu - Create Account')</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="{{ asset('vendors/iconic-fonts/flat-icons/flaticon.css') }}" rel="stylesheet">
    <link href="{{ asset('vendors/iconic-fonts/font-awesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/jquery-ui.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    {{-- Loaded here (and not only from the CDN copy in layouts/app) so a public
         page can raise Swal.fire() on its own - the registration forms report
         every validation error that way. --}}
    <link href="{{ asset('assets/css/sweetalert2.min.css') }}" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">

    <style>
        /* .ms-auth is sized for the login card (height:100vh), but a guest page
           can be taller than the screen - let it grow and scroll instead of
           pushing the form past the bottom of the window. */
        .ms-content-wrapper.ms-auth {
            height: auto;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        /* Bootstrap utilities carry `!important`, so their twins need it too:
           `.text-muted` is #6c757d, which is hard to read on the dark card. */
        .ms-dark-theme .text-muted {
            color: #b9bcd8 !important;
        }
    </style>

    @stack('styles')
</head>

@php
    // Display mode (dark / light). The class is rendered with the HTML so a dark
    // page never flashes white while loading; light is the default and the switch
    // itself only exists on the signed-in screens (see docs/DISPLAY-MODE.md).
    $themeClass = request()->cookie('tiu_theme') === 'dark' ? ' ms-dark-theme' : '';
@endphp

<body class="ms-body ms-primary-theme ms-logged-out{{ $themeClass }}">

    <main class="body-content">
        <div class="ms-content-wrapper ms-auth">
            @yield('content')
        </div>
    </main>

    <script src="{{ asset('assets/js/jquery-3.3.1.min.js') }}"></script>
    <script src="{{ asset('assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/js/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('assets/js/framework.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}"></script>
    <script src="{{ asset('assets/js/settings.js') }}"></script>
    <script src="{{ asset('assets/js/sweetalert2.min.js') }}"></script>
    @stack('scripts')

</body>
</html>
