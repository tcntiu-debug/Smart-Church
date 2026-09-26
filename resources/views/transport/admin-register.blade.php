@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item active">Transport Registration (Admin)</li>
            </ol>
        </nav>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="ms-panel">
                    <div class="ms-panel-header">
                        <h6>Bus Route Registration (Admin Portal)</h6>
                    </div>
                    <div class="ms-panel-body">
                        <!-- Step 1: Lookup -->
                        <div id="step-lookup">
                            <p class="ms-directions">Enter email or phone to check if the member exists in your campus.</p>
                            <div class="form-row">
                                <div class="col-md-6 mb-3">
                                    <label>Email Address</label>
                                    <input type="email" class="form-control" id="lookup-email" placeholder="example@mail.com">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label>Phone Number (11 digits)</label>
                                    <input type="tel" class="form-control" id="lookup-phone" placeholder="08012345678" pattern="[0-9]{11}" maxlength="11" minlength="11" oninput="this.value=this.value.replace(/\D/g,'')">
                                    <small class="text-muted">Exactly 11 digits required</small>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-block btn-lg" id="lookup-btn" onclick="doLookup()">
                                <i class="fas fa-search mr-2"></i>Check Member & Proceed
                            </button>
                            <div id="lookup-error" class="alert alert-danger mt-3" style="display: none;"></div>
                        </div>

                        <!-- Step 2: Full Registration Form (hidden initially) -->
                        <div id="step-registration" style="display: none;">
                            <form id="full-registration-form" novalidate onsubmit="return submitRegistration(this)">
                                @csrf
                                <input type="hidden" name="email" id="reg-email" value="">
                                <input type="hidden" name="phone_number" id="reg-phone" value="">
                                <input type="hidden" name="tiu_member_id" id="reg-member-id" value="">
                                <input type="hidden" name="source" id="reg-source" value="new_user">

                                <div id="user-found-alert" class="alert alert-info" style="display: none;"></div>
                                <div id="new-user-fields" style="display: none;">
                                    <h6 class="text-primary mb-3">Member Details:</h6>
                                    <div class="form-row">
                                        <div class="col-md-4 mb-3">
                                            <label>First Name*</label>
                                            <input type="text" class="form-control" name="first_name" id="reg-first-name" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Surname*</label>
                                            <input type="text" class="form-control" name="last_name" id="reg-last-name" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label>Gender*</label>
                                            <select class="form-control" name="gender" id="reg-gender" required>
                                                <option value="">-- Select --</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Residential Address (Optional)</label>
                                        <input type="text" class="form-control" name="residential_address" id="reg-address" placeholder="Enter residential address">
                                    </div>
                                </div>

                                <div class="route-selection-box">
                                    <h6 class="font-weight-bold mb-3"><i class="fa fa-bus mr-2"></i>Route Selection</h6>
                                    <div class="form-group">
                                        <label>Step 1: Choose Route*</label>
                                        <select id="route-select" class="form-control" name="route_id" required onchange="loadStops(this.value)">
                                            <option value="">-- Please select a route --</option>
                                            @foreach($routes as $route)
                                                <option value="{{ $route->route_id }}">{{ $route->route_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Step 2: Choose Bus Stop*</label>
                                        <select id="stop-select" class="form-control" name="bus_stop_id" required disabled>
                                            <option value="">-- Select route first --</option>
                                        </select>
                                    </div>

                                    <div id="confirmation-box" style="display: none;" class="bg-white p-2 border rounded mt-2">
                                        <p id="confirmation-text" class="mb-0 small"></p>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">
                                    <i class="fas fa-check-circle mr-2"></i>Confirm & Register
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .route-selection-box {
        background-color: #e3f2fd !important;
        border: 1px solid #90caf9;
        color: #1565c0;
        padding: 20px;
        border-radius: 8px;
        margin-top: 10px;
    }
</style>

{{-- SCRIPT MUST BE INLINE BEFORE framework.js TO AVOID framework.js ERROR BLOCKING IT --}}
<script>
// GLOBAL FUNCTIONS - defined outside document.ready to work even if framework.js throws errors

/**
 * Look up a member by email or phone
 */
function doLookup() {
    const email = $('#lookup-email').val().trim();
    const phone = $('#lookup-phone').val().trim();
    $('#lookup-error').hide();

    if (!email && !phone) {
        $('#lookup-error').html('Please enter an email or phone number.').show();
        return;
    }

    // Validate phone number is exactly 11 digits if provided
    if (phone && phone.length !== 11) {
        $('#lookup-error').html('Phone number must be exactly 11 digits.').show();
        return;
    }

    const btn = $('#lookup-btn');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Searching...');

    $.ajax({
        url: '{{ route("transport.lookup-member") }}',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            email: email,
            phone_number: phone
        },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                const data = res.data;
                $('#reg-email').val(data.email || '');
                $('#reg-phone').val(data.phone_number || '');
                $('#reg-source').val(data.source || 'new_user');
                $('#reg-member-id').val(data.tiu_member_id || '');

                if (data.source === 'tiu_member') {
                    var currentRouteHtml = '';
                    if (data.current_route) {
                        currentRouteHtml = '<p class="mb-0 mt-1"><strong>Current Bus Route:</strong> ' + data.current_route + '<br>' +
                            '<strong>Current Bus Stop:</strong> ' + data.current_stop + '</p>' +
                            '<div class="bg-warning text-dark p-2 rounded mt-2 small font-weight-bold"><i class="fas fa-exclamation-triangle mr-1"></i>Assigning a new route will overwrite the existing one.</div>';
                    } else {
                        currentRouteHtml = '<p class="mb-0 mt-1 text-muted"><i class="fas fa-info-circle mr-1"></i>No bus route currently assigned.</p>';
                    }
                    $('#user-found-alert').html(
                        '<strong><i class="fas fa-user-check mr-2"></i>Record Found</strong>' +
                        '<p class="mb-0 mt-2"><strong>Name:</strong> ' + (data.first_name + ' ' + data.last_name) + '<br>' +
                        '<strong>Phone:</strong> ' + (data.phone_number || '') + '</p>' +
                        currentRouteHtml
                    ).show();
                    $('#new-user-fields').hide();
                } else if (data.source === 'first_timer') {
                    $('#user-found-alert').html(
                        '<strong><i class="fas fa-user-plus mr-2"></i>Record Found</strong>' +
                        '<p class="mb-0 mt-2"><strong>Name:</strong> ' + (data.first_name + ' ' + data.last_name) + '<br>' +
                        '<strong>Phone:</strong> ' + (data.phone_number || '') + '</p>' +
                        '<small class="text-muted">This first timer will be integrated as a member and assigned to a bus route.</small>'
                    ).show();
                    $('#reg-first-name').val(data.first_name || '');
                    $('#reg-last-name').val(data.last_name || '');
                    $('#reg-gender').val(data.gender || '');
                    $('#reg-address').val(data.residential_address || '');
                    $('#new-user-fields').show();
                } else {
                    $('#user-found-alert').html(
                        '<strong><i class="fas fa-user-plus mr-2"></i>New User Registration</strong>' +
                        '<p class="mb-0 mt-2">No existing record found. Please fill in the details below.</p>'
                    ).show();
                    $('#new-user-fields').show();
                }

                $('#step-lookup').hide();
                $('#step-registration').show();
            } else {
                $('#lookup-error').html(res.message || 'Lookup failed.').show();
            }
        },
        error: function() {
            $('#lookup-error').html('System error. Please try again.').show();
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="fas fa-search mr-2"></i>Check Member & Proceed');
        }
    });
}

/**
 * Load bus stops for a selected route
 */
function loadStops(routeId) {
    const stopSelect = $('#stop-select');
    stopSelect.prop('disabled', true).html('<option>Loading...</option>');

    if (!routeId) return;

    $.getJSON('{{ route("transport.get-stops") }}', { route_id: routeId }, function(response) {
        stopSelect.prop('disabled', false).empty().append('<option value="">-- Select your stop --</option>');
        if (response.stops) {
            response.stops.forEach(function(s) {
                stopSelect.append('<option value="' + s.stop_id + '">' + s.stop_name + ' (' + s.take_off_time + ')</option>');
            });
        }
    });
}

/**
 * Submit registration form via AJAX
 */
function submitRegistration(form) {
    const btn = $(form).find('button[type="submit"]');
    const originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Processing...');

    $.ajax({
        url: '{{ route("transport.admin-register-submit") }}',
        type: 'POST',
        data: $(form).serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                alert('Registration Successful');
                window.location.href = res.redirect || '{{ url("/transport-register") }}';
            } else {
                alert('Error: ' + res.message);
                btn.prop('disabled', false).html(originalText);
            }
        },
        error: function() {
            alert('System error. Please try again.');
            btn.prop('disabled', false).html(originalText);
        }
    });

    return false; // prevent normal form submission
}
</script>
@endsection
