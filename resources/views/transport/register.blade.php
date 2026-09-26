@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6>Bus Route Registration</h6>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <!-- Display Current Registration Status -->
                    @if($currentRegistration)
                        <div class="alert alert-info">
                            <strong>Your Current Registration:</strong><br>
                            You are registered for the <strong>{{ $currentRegistration->route_name }}</strong>.<br>
                            Your pickup point is <strong>{{ $currentRegistration->stop_name }}</strong> at
                            <strong>{{ date('g:i A', strtotime($currentRegistration->take_off_time)) }}</strong>.
                            <div class="contact-info mt-2">
                                <strong>Driver:</strong> {{ $currentRegistration->driver_name ?? 'N/A' }} 
                                ({{ $currentRegistration->driver_contact ?? 'N/A' }})<br>
                                <strong>Team Lead:</strong> {{ $currentRegistration->team_lead_name ?? 'N/A' }} 
                                ({{ $currentRegistration->team_lead_contact ?? 'N/A' }})
                            </div>
                            <small class="mt-2 d-block">To change, simply select a new route and stop below.</small>
                        </div>
                    @else
                        <div class="alert alert-light">
                            You are not currently registered for bus transportation. Please select a route to begin.
                        </div>
                    @endif

                    <!-- Registration Form -->
                    <form id="registration-form">
                        @csrf
                        <div class="form-group mb-3">
                            <label>Step 1: Choose Your Route</label>
                            <select id="route-select" class="form-control" required>
                                <option value="">-- Please select a route --</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route->route_id }}">{{ $route->route_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label>Step 2: Choose Your Nearest Bus Stop</label>
                            <select id="stop-select" class="form-control" required disabled>
                                <option value="">-- Please select a route first --</option>
                            </select>
                        </div>

                        <div id="confirmation-box" class="p-3 mb-3" style="border: 1px solid #0258c9; background-color: #f0f7ff; border-radius: 5px; display: none;">
                            <h5 class="mt-0">Please Confirm Your Selection</h5>
                            <p id="confirmation-text"></p>
                            <div id="contact-info-display" class="contact-info mt-2"></div>
                            <button type="submit" class="btn btn-primary mt-3">Confirm and Register</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .contact-info {
        font-size: 0.9em;
        margin-top: 10px;
        padding: 10px;
        background: #e9ecef;
        border-radius: 5px;
    }
</style>

@push('scripts')
<script>
$(document).ready(function() {
    const routeSelect = $('#route-select');
    const stopSelect = $('#stop-select');
    const confirmationBox = $('#confirmation-box');
    const confirmationText = $('#confirmation-text');
    const contactInfoDisplay = $('#contact-info-display');
    // Use unescaped output for JSON to generate valid JS, fallback to null
    const currentRegStop = {!! $currentRegistration ? json_encode($currentRegistration->stop_name) : 'null' !!};

    // When a route is selected
    routeSelect.on('change', function() {
        const routeId = $(this).val();
        stopSelect.prop('disabled', true).html('<option value="">Loading stops...</option>');
        confirmationBox.hide();

        if (!routeId) {
            stopSelect.html('<option value="">-- Please select a route first --</option>');
            stopSelect.prop('disabled', true);
            return;
        }

        // AJAX call to get stops and route details
        $.ajax({
            url: '{{ url("/bus-route/get-stops") }}',
            type: 'GET',
            data: { route_id: routeId },
            dataType: 'json',
            success: function(response) {
                stopSelect.empty().append('<option value="">-- Select your stop --</option>');
                if (response.stops && response.stops.length > 0) {
                    $.each(response.stops, function(index, stop) {
                        const time = new Date('1970-01-01T' + stop.take_off_time).toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });
                        const optionText = stop.stop_name + (stop.take_off_time ? ' (' + time + ')' : '');
                        stopSelect.append($('<option>', {
                            value: stop.stop_id,
                            text: optionText
                        }));
                    });
                }
                // Store route contact details for later use
                stopSelect.data('contact-info', response.route_details);
                stopSelect.prop('disabled', false);
            },
            error: function() {
                stopSelect.html('<option value="">Error loading stops. Please select a route again.</option>');
                stopSelect.prop('disabled', false);
            }
        });
    });

    // When a stop is selected
    stopSelect.on('change', function() {
        const stopId = $(this).val();
        const routeName = routeSelect.find('option:selected').text();

        if (!stopId) {
            confirmationBox.hide();
            return;
        }

        const stopText = $(this).find('option:selected').text();
        const contactInfo = $(this).data('contact-info');

        confirmationText.html('You have selected the <strong>' + routeName + '</strong>.<br>Your pick-up point will be <strong>' + stopText + '</strong>.');

        if (contactInfo) {
            contactInfoDisplay.html(
                '<strong>Driver:</strong> ' + (contactInfo.driver_name || 'N/A') + ' (' + (contactInfo.driver_contact || 'N/A') + ')<br>' +
                '<strong>Team Lead:</strong> ' + (contactInfo.team_lead_name || 'N/A') + ' (' + (contactInfo.team_lead_contact || 'N/A') + ')'
            );
        }

        confirmationBox.show();
    });

    // Form Submission
    $('#registration-form').on('submit', function(e) {
        e.preventDefault();
        const selectedStopId = stopSelect.val();

        if (currentRegStop && currentRegStop !== null) {
            if (!confirm('You are already registered for "' + currentRegStop + '". Are you sure you want to switch to the new stop?')) {
                return;
            }
        }

        $.ajax({
            url: '{{ route("transport.save-registration") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                bus_stop_id: selectedStopId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred. Please try again.');
            }
        });
    });
});
</script>
@endpush
@endsection