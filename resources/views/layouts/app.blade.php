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
</style>

<body class="ms-body ms-aside-left-open">

    @include('partials.nav')


    <main class="body-content">
        <nav class="navbar ms-navbar">
            <div class="ms-aside-toggler ms-toggler pl-0" data-target="#ms-side-nav" data-toggle="slideLeft">
                <span class="ms-toggler-bar bg-primary"></span>
                <span class="ms-toggler-bar bg-primary"></span>
                <span class="ms-toggler-bar bg-primary"></span>
            </div>
            Welcome {{ session('first_name', 'Guest') }} {{ session('last_name', '') }}
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