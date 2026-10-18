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
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">

    {{-- Installable app (PWA): manifest + icons, driven by partials/pwa-install-button
         and public/service-worker.js - see docs/PWA.md. --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#F97316">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192-v2.png') }}">
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

    /* Unread badge on the bell. Painted here (not inline) for the same reason as
       the bell colour above: the dark palette has to be able to override it. */
    .ms-navbar .ms-notif-badge {
        position: absolute;
        top: -8px;
        right: -12px;
        min-width: 18px;
        padding: 0 5px;
        font-size: 10px;
        font-weight: 600;
        line-height: 17px;
        text-align: center;
        color: #ffffff;
        background-color: #dc3545;
        border-radius: 999px;
    }

    .ms-dark-theme .ms-navbar .ms-notif-badge {
        color: #1b1e3d;
        background-color: #ff8306;
    }

    /* A newly arrived alert pulses the badge, so a member who is not looking at the
       bell still notices it (the script adds `is-bell-fresh`). */
    @keyframes tiu-badge-pop {
        0% { transform: scale(1); }
        40% { transform: scale(1.45); }
        100% { transform: scale(1); }
    }

    .ms-navbar .ms-notif-bell.is-bell-fresh .ms-notif-badge {
        animation: tiu-badge-pop 900ms ease-out 3;
    }

    @media (prefers-reduced-motion: reduce) {
        .ms-navbar .ms-notif-bell.is-bell-fresh .ms-notif-badge {
            animation: none;
        }
    }

    /* Notification centre (partials/notification-modal). It slides in from the
       right edge as a panel instead of sitting in the middle of the page, so the
       page the member was working on stays visible behind it. Bootstrap 4.1 ships
       no offcanvas component, hence the dock below. The page at /notifications
       renders the very same rows, so these classes live here rather than in one of
       the two views: light rule, then its dark twin (docs/DISPLAY-MODE.md). The
       template matches `p` and `span` elements in dark mode, hence the element in
       each twin below. */
    .notif-modal-header,
    .notif-modal-footer {
        background-color: #f7f8fd;
        flex: 0 0 auto;
    }

    /* The dock itself. Written with the id (not a bare class) so the slide wins
       against Bootstrap's own `.modal.fade .modal-dialog`, whatever the order the
       two stylesheets land in. */
    #notificationsModal .notif-dialog {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: auto;
        width: 420px;
        max-width: 100%;
        height: 100%;
        margin: 0;
        transform: translateX(100%);
        transition: transform 260ms ease-out;
    }

    #notificationsModal.show .notif-dialog {
        transform: translateX(0);
    }

    #notificationsModal .modal-content {
        height: 100%;
        border-radius: 12px 0 0 12px;
    }

    /* Only the list scrolls: the title and the buttons hold their place. */
    #notificationsModal .notif-modal-body {
        flex: 1 1 auto;
        max-height: none;
        overflow-y: auto;
    }

    /* On a phone the panel is the screen. */
    @media (max-width: 575.98px) {
        #notificationsModal .notif-dialog {
            width: 100%;
        }

        #notificationsModal .modal-content {
            border-radius: 0;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        #notificationsModal .notif-dialog {
            transition: none;
        }
    }

    span.notif-panel-count {
        margin-left: 6px;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 600;
        color: #0062cc;
        background-color: #e7f0ff;
        border-radius: 999px;
    }

    .ms-dark-theme span.notif-panel-count {
        color: #9db2ff;
        background-color: #1e2a52;
    }

    .notif-loading {
        padding: 26px 12px;
        font-size: 13px;
        text-align: center;
        color: #8a90b5;
    }

    .ms-dark-theme .notif-loading {
        color: #b9bcd8;
    }

    .notif-empty {
        padding: 26px 12px;
        text-align: center;
    }

    .notif-empty i {
        font-size: 30px;
        color: #1c7a45;
    }

    .ms-dark-theme .notif-empty i {
        color: #7fe0a5;
    }

    p.notif-empty-title {
        margin: 10px 0 2px;
        font-weight: 600;
        color: #2f3350;
    }

    p.notif-empty-note {
        margin: 0;
        font-size: 12px;
        color: #8a90b5;
    }

    .ms-dark-theme p.notif-empty-note {
        color: #b9bcd8;
    }

    .notif-modal-footer {
        justify-content: space-between;
    }

    .notif-modal-links {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ms-dark-theme .notif-modal-header,
    .ms-dark-theme .notif-modal-footer {
        background-color: #323a67;
        border-color: #242750;
    }

    ul.notif-list {
        margin: 0;
        padding: 0;
        list-style: none;
    }

    li.notif-item {
        position: relative;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 4px 14px 16px;
        background-color: #ffffff;
        border-bottom: 1px solid #eceffb;
        transition: opacity 160ms ease, transform 160ms ease;
    }

    /* Everything in this list is unread - reading one clears it - so the accent bar
       is what tells the eye where a row starts. */
    li.notif-item::before {
        content: '';
        position: absolute;
        top: 12px;
        bottom: 12px;
        left: 0;
        width: 3px;
        background-color: #0062cc;
        border-radius: 3px;
    }

    li.notif-item.notif-item-leaving {
        opacity: 0;
        transform: translateX(16px);
    }

    span.notif-icon {
        flex: 0 0 38px;
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: #0062cc;
        background-color: #e7f0ff;
        border-radius: 50%;
    }

    span.notif-icon-caller {
        color: #0062cc;
        background-color: #e7f0ff;
    }

    span.notif-icon-guide {
        color: #1c7a45;
        background-color: #e6f7ee;
    }

    span.notif-icon-birthday {
        color: #a4640a;
        background-color: #fff4e0;
    }

    span.notif-icon-task {
        color: #6b4bb8;
        background-color: #f1ebff;
    }

    span.notif-icon-announcement {
        color: #a11f36;
        background-color: #ffe9ee;
    }

    .notif-main {
        flex: 1 1 auto;
        min-width: 0;
    }

    .notif-title {
        font-weight: 600;
        font-size: 14px;
        color: #2f3350;
    }

    p.notif-body {
        margin: 2px 0 0;
        font-size: 13px;
        color: #6b7194;
        white-space: pre-line;
    }

    .notif-meta {
        margin-top: 4px;
        font-size: 12px;
    }

    span.notif-ago {
        font-weight: 600;
        color: #0062cc;
    }

    span.notif-exact {
        color: #8a90b5;
    }

    .notif-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    .ms-dark-theme li.notif-item {
        background-color: #252851;
        border-bottom-color: #242750;
    }

    .ms-dark-theme li.notif-item::before {
        background-color: #ff8306;
    }

    .ms-dark-theme .notif-title {
        color: #ffffff;
    }

    .ms-dark-theme p.notif-body {
        color: #b9bcd8;
    }

    .ms-dark-theme span.notif-ago {
        color: #9db2ff;
    }

    .ms-dark-theme span.notif-exact {
        color: #b9bcd8;
    }

    .ms-dark-theme span.notif-icon {
        color: #9db2ff;
        background-color: #1e2a52;
    }

    .ms-dark-theme span.notif-icon-guide {
        color: #7fe0a5;
        background-color: #1f3b2a;
    }

    .ms-dark-theme span.notif-icon-birthday {
        color: #ffc46b;
        background-color: #3a2f10;
    }

    .ms-dark-theme span.notif-icon-task {
        color: #c3b1ff;
        background-color: #2a2350;
    }

    .ms-dark-theme span.notif-icon-announcement {
        color: #ff9fb2;
        background-color: #3a1820;
    }
</style>

@php
    // Display mode (dark / light). The class is rendered with the HTML so a dark
    // page never flashes white while loading; light is the default (see
    // docs/DISPLAY-MODE.md) and the switch lives in partials/theme-toggle.
    $themeClass = request()->cookie('tiu_theme') === 'dark' ? ' ms-dark-theme' : '';
@endphp

<body class="ms-body ms-aside-left-open{{ $themeClass }}">

    @include('partials.nav')

    {{-- Backdrop for the side navigation on phones: a tap on the dimmed area
         closes the menu. It reuses the `.ms-toggler` slideLeft contract, so the
         navbar button, the panel's own close button (partials/nav) and this
         backdrop all drive the one open state in public/assets/js/framework.js.
         It stays hidden from 1025px up - see public/assets/css/style.css. --}}
    <div class="ms-aside-overlay ms-overlay-left ms-toggler"
         data-target="#ms-side-nav"
         data-toggle="slideLeft"
         aria-hidden="true"></div>


    <main class="body-content">
        <nav class="navbar ms-navbar">
            <div class="ms-aside-toggler ms-toggler pl-0" data-target="#ms-side-nav" data-toggle="slideLeft">
                <span class="ms-toggler-bar bg-primary"></span>
                <span class="ms-toggler-bar bg-primary"></span>
                <span class="ms-toggler-bar bg-primary"></span>
            </div>
            Welcome {{ session('first_name', 'Guest') }} {{ session('last_name', '') }}

            {{-- The bell slides the notification centre in from the right
                 (partials/notification-modal) so the member never loses the page
                 they are working on. The href stays as the no-JS fallback. --}}
            <a href="{{ route('notifications.index') }}"
               class="ms-notif-bell ml-auto"
               data-toggle="modal"
               data-target="#notificationsModal"
               aria-haspopup="dialog"
               title="Notifications"
               aria-label="Notifications">
                <i class="fas fa-bell" style="font-size:18px;"></i>
                <span class="ms-notif-badge" id="notifBadge" @if (($unreadNotifications ?? 0) <= 0) hidden @endif>{{ ($unreadNotifications ?? 0) > 9 ? '9+' : ($unreadNotifications ?? 0) }}</span>
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

    {{-- PWA plumbing: register the service worker and capture Chrome's
         "beforeinstallprompt" so partials/pwa-install-button can offer the
         install in one click. Both are no-ops on browsers without support. --}}
    <script>
        (function () {
            'use strict';

            // Chrome hands the event out once, early in the page life; stash it.
            window.tiuInstallPrompt = null;

            window.addEventListener('beforeinstallprompt', function (event) {
                event.preventDefault();
                window.tiuInstallPrompt = event;
                window.dispatchEvent(new CustomEvent('tiu:install-available'));
            });

            window.addEventListener('appinstalled', function () {
                window.tiuInstallPrompt = null;
                window.dispatchEvent(new CustomEvent('tiu:installed'));
            });

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker.register('{{ asset('service-worker.js') }}').catch(function () {
                        // Installation still works without the worker; the page
                        // itself does not need it (no offline caching yet).
                    });
                });
            }
        })();
    </script>
    @stack('scripts')

    {{-- In-app alerts: poll the unread feed so a new caller / guide alert also
         reaches the browser as a system notification. Web only for now (no native
         app, no Web Push) - see docs/NOTIFICATIONS.md and
         public/assets/js/notifications.js. The member allows it once from the
         notification centre; until then this only keeps the bell badge fresh. --}}
    @auth
        <script>
            // Handed to public/assets/js/notifications.js: the feed the bell's
            // panel and the poller both read, the two "read" endpoints (which clear
            // the row) and the token an XHR needs. See docs/NOTIFICATIONS.md.
            window.tiuNotifications = {
                feedUrl: '{{ route('notifications.feed') }}',
                readUrl: '{{ route('notifications.read', ['id' => '__ID__']) }}',
                readAllUrl: '{{ route('notifications.read-all') }}',
                csrfToken: '{{ csrf_token() }}',
                panelLimit: 30,
                pollSeconds: {{ (int) config('notifications.poll_seconds', 60) }},
                enabled: {{ config('notifications.enabled', true) ? 'true' : 'false' }},
                icon: '{{ asset('icons/icon-192-v2.png') }}'
            };
        </script>
        <script src="{{ asset('assets/js/notifications.js') }}"></script>

        {{-- The centre itself: filled by the script above from the feed. --}}
        @include('partials.notification-modal')
    @endauth

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