<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TIU Dashboard</title>

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="{{ asset('vendors/iconic-fonts/font-awesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/style2.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/theme-toggle.css') }}" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.ico') }}">
</head>
<style>
    /* Fix for accordion button hover - make text visible */
    .accordion .card-header .btn-link {
        color: #0062cc !important;
        text-decoration: none !important;
        font-weight: bold !important;
        background-color: transparent !important;
    }

    .accordion .card-header .btn-link:hover {
        color: #0056b3 !important;
        background-color: #e9ecef !important;
        text-decoration: none !important;
    }

    .accordion .card-header .btn-link:not(.collapsed) {
        color: #333 !important;
        background-color: #f7f7f7 !important;
    }

    .accordion .card-header .btn-link:not(.collapsed):hover {
        color: #333 !important;
        background-color: #e9ecef !important;
    }

    /* Make sure the button text is always visible */
    .accordion .card-header .btn-link:focus,
    .accordion .card-header .btn-link:active {
        text-decoration: none !important;
        outline: none !important;
    }

    /* Fix for any white text on white background */
    .btn-link {
        color: #0062cc !important;
    }

    .btn-link:hover {
        color: #0056b3 !important;
    }

    /* Dark twins for the `!important` rules above. A page-local twin cannot beat
       them unless it is `!important` too, so the dark palette for `.btn-link`
       lives here (see docs/DISPLAY-MODE.md) - otherwise every `.btn-link` (e.g.
       the guide names on the Drag & Drop page) stays #0062cc blue on the dark
       surfaces. Same light-rule-then-twin pattern as `.ms-navbar .ms-notif-bell`
       below. */
    .ms-dark-theme .btn-link,
    .ms-dark-theme .accordion .card-header .btn-link {
        color: #ff8306 !important;
    }

    .ms-dark-theme .btn-link:hover,
    .ms-dark-theme .accordion .card-header .btn-link:hover {
        color: #ffffff !important;
        background-color: #2a2e5b !important;
    }

    .ms-dark-theme .accordion .card-header .btn-link:not(.collapsed),
    .ms-dark-theme .accordion .card-header .btn-link:not(.collapsed):hover {
        color: #ffffff !important;
        background-color: #323a67 !important;
    }

    /* Notification bell: colour lives here (not inline) so the dark palette can
       override it - #0062cc on the dark navbar is unreadable. */
    .ms-navbar .ms-notif-bell {
        position: relative;
        color: #0062cc;
        text-decoration: none;
    }

    .ms-navbar .ms-notif-bell:hover,
    .ms-navbar .ms-notif-bell:focus {
        color: #0056b3;
        text-decoration: none;
    }

    .ms-dark-theme .ms-navbar .ms-notif-bell,
    .ms-dark-theme .ms-navbar .ms-notif-bell:hover,
    .ms-dark-theme .ms-navbar .ms-notif-bell:focus {
        color: #ffffff;
    }
</style>

@php
    // Display mode (dark / light). Rendered with the HTML so a dark page never
    // flashes white while loading; the switch itself lives in partials/theme-toggle.
    $themeClass = request()->cookie('tiu_theme') === 'dark' ? ' ms-dark-theme' : '';
@endphp

<body class="ms-body ms-aside-left-open{{ $themeClass }}">

    @include('partials.theme-init')

    @include('partials.nav')


    <main class="body-content">
        <nav class="navbar ms-navbar">
            <div class="ms-aside-toggler ms-toggler pl-0" data-target="#ms-side-nav" data-toggle="slideLeft">
                <span class="ms-toggler-bar bg-primary"></span>
                <span class="ms-toggler-bar bg-primary"></span>
                <span class="ms-toggler-bar bg-primary"></span>
            </div>
            Welcome {{ session('first_name', 'Guest') }} {{ session('last_name', '') }}

            <a href="{{ route('notifications.index') }}"
               class="ms-notif-bell ml-auto"
               title="Notifications">
                <i class="fas fa-bell" style="font-size:18px;"></i>
                @if (($unreadNotifications ?? 0) > 0)
                    <span class="badge badge-danger badge-pill"
                          style="position:absolute;top:-8px;right:-12px;font-size:10px;">
                        {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                    </span>
                @endif
            </a>

            @include('partials.theme-toggle', ['variant' => 'navbar'])
        </nav>

        <div class="ms-content-wrapper">
            @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    <script src="{{ asset('assets/js/jquery-3.3.1.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('assets/js/popper.min.js') }}"></script>
    <script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('assets/js/perfect-scrollbar.js') }}"></script>
    <script src="{{ asset('assets/js/framework.js') }}"></script>
    <script src="{{ asset('assets/js/theme.js') }}"></script>
    @stack('scripts')

    <!-- Airtime Topup Modal -->
    <div id="airtimeModalContainer" style="display:none;">
        <div class="modal fade" id="airtimeModal" tabindex="-1" role="dialog" aria-labelledby="airtimeModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="airtimeModalLabel">Airtime Topup Available 1pm-5pm</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>You will be credited with <strong>N200</strong> airtime.</p>
                        <p>This may take a few minutes. Only one request per week is allowed.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="confirmAirtimeBtn">Continue</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            function initAirtimeFeature() {
                if (typeof jQuery === 'undefined' || typeof jQuery.fn.modal === 'undefined') {
                    setTimeout(initAirtimeFeature, 100);
                    return;
                }

                var $ = jQuery;

                var modalContainer = $('#airtimeModalContainer');
                if (modalContainer.length && !$('#airtimeModal').parent().is('body')) {
                    modalContainer.children().appendTo('body');
                    modalContainer.remove();
                }

                $(document).on('click', '#airtimeTopupBtn', function(e) {
                    e.preventDefault();
                    $('#airtimeModal').modal('show');
                });

                $(document).on('click', '#confirmAirtimeBtn', function() {
                    var btn = $(this);
                    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');
                    $.ajax({
                        url: '{{ url("/airtime-request") }}',
                        type: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                $('#airtimeModal').modal('hide');
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Server error. Please try again.');
                        },
                        complete: function() {
                            btn.prop('disabled', false).html('Continue');
                        }
                    });
                });
            }

            initAirtimeFeature();
        })();
    </script>
</body>

</html>