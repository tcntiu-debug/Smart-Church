@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Bus Route Config</li>
            </ol>
        </nav>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <!-- PANEL 1: CREATE / EDIT ROUTE -->
        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Create / Edit Bus Route</h6>
            </div>
            <div class="ms-panel-body pb-0">
                <div class="form-row align-items-end">
                    <div class="col-md-5">
                        <label for="edit-route-select">Select a Route to Edit</label>
                        <select class="form-control" id="edit-route-select">
                            <option value="">-- Choose an existing route --</option>
                            @foreach($routes as $route)
                                <option value="{{ $route->route_id }}">{{ $route->route_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-info w-100" id="load-route-btn">Load for Editing</button>
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-secondary w-100" id="new-route-btn">Create New Route</button>
                    </div>
                </div>
                <hr>
            </div>
            <div class="ms-panel-body">
                <form id="route-form" class="form-row">
                    @csrf
                    <input type="hidden" name="route_id" id="route_id_field" value="">

                    <div class="col-md-6 mb-3">
                        <label>Route Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="route_name" required placeholder="e.g., North Route">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Route Status</label>
                        <select class="form-control" name="status" id="route_status">
                            <option value="active">Active (Visible to members)</option>
                            <option value="inactive">Inactive (Hidden from members)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Bus Driver Name</label>
                        <input type="text" class="form-control" name="driver_name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Bus Driver Contact</label>
                        <input type="text" class="form-control" name="driver_contact">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Bus Team Lead Name</label>
                        <input type="text" class="form-control" name="team_lead_name">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Bus Team Lead Contact</label>
                        <input type="text" class="form-control" name="team_lead_contact">
                    </div>

                    <div class="col-md-12">
                        <hr>
                        <h5>Bus Stops</h5>
                        <div id="bus-stops-container"></div>
                        <button type="button" id="add-stop-btn" class="btn btn-secondary btn-sm mt-2">
                            <i class="fa fa-plus"></i> Add Another Bus Stop
                        </button>
                    </div>

                    <div class="col-md-12 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Route
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PANEL 2: ROUTE VIEWER & ANALYTICS -->
        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>Route Viewer & Analytics</h6>
            </div>
            <div class="ms-panel-body">
                <div class="form-row">
                    <div class="col-md-6 mb-3">
                        <label>Select a Route to View</label>
                        <select class="form-control" id="route-viewer-select">
                            <option value="">-- Please choose a route --</option>
                            @foreach($routes as $route)
                                <option value="{{ $route->route_id }}">{{ $route->route_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <div id="routeMap" style="height: 450px; width: 100%; border: 1px solid #ddd; border-radius: .25rem;"></div>
                    </div>
                </div>
                <div id="route-members-container"></div>
            </div>
        </div>

        <!-- PANEL 3: ALL ROUTES LIST -->
        <div class="ms-panel">
            <div class="ms-panel-header">
                <h6>All Routes</h6>
            </div>
            <div class="ms-panel-body">
                <div class="table-responsive">
                    <table class="table table-hover thead-primary">
                        <thead>
                            <tr>
                                <th>Route Name</th>
                                <th>Status</th>
                                <th>Driver</th>
                                <th>Driver Contact</th>
                                <th>Team Lead</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($routes as $route)
                                <tr>
                                    <td>{{ $route->route_name }}</td>
                                    <td>
                                        <span class="badge badge-{{ $route->status == 'active' ? 'success' : 'secondary' }}">
                                            {{ $route->status }}
                                        </span>
                                    </td>
                                    <td>{{ $route->driver_name ?: '—' }}</td>
                                    <td>{{ $route->driver_contact ?: '—' }}</td>
                                    <td>{{ $route->team_lead_name ?: '—' }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-route-btn"
                                                data-id="{{ $route->route_id }}"
                                                title="Edit Route">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No routes found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Leaflet Map Libraries -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
.bus-stop-row {
    padding: 15px;
    border: 1px solid #e3e3e3;
    border-radius: 5px;
    margin-bottom: 15px;
    background-color: #f9f9f9;
}
</style>

<script>
$(document).ready(function() {
    let stopCounter = 0;

    function updateBusStopUI() {
        const stopCount = $('#bus-stops-container .bus-stop-row').length;
        $('#no-stops-message').remove();
        if (stopCount === 0) {
            $('#bus-stops-container').append(
                '<div class="alert alert-warning" id="no-stops-message">' +
                'No bus stops are defined. <strong>All existing stops for this route will be removed upon saving.</strong>' +
                '</div>'
            );
        }
    }

    function addBusStopRow(stopData = null) {
        stopCounter++;
        const stopId = stopData ? stopData.stop_id : '';
        const stopName = stopData ? stopData.stop_name : '';
        const takeOffTime = stopData ? stopData.take_off_time : '';
        const latitude = stopData ? stopData.latitude : '';
        const longitude = stopData ? stopData.longitude : '';

        const html = `
        <div class="bus-stop-row form-row">
            <div class="col-12"><strong class="mb-2 d-block">Stop #${stopCounter}</strong></div>
            <input type="hidden" name="stop_id[]" value="${stopId}">
            <div class="col-md-6 mb-3">
                <label>Stop Name</label>
                <input type="text" class="form-control" name="stop_name[]" required value="${stopName}">
            </div>
            <div class="col-md-6 mb-3">
                <label>Take-Off Time</label>
                <input type="time" class="form-control" name="take_off_time[]" value="${takeOffTime}">
            </div>
            <div class="col-md-5 mb-3">
                <label>Latitude</label>
                <input type="number" step="any" class="form-control" name="latitude[]" placeholder="e.g., 5.6037" value="${latitude}">
            </div>
            <div class="col-md-5 mb-3">
                <label>Longitude</label>
                <input type="number" step="any" class="form-control" name="longitude[]" placeholder="e.g., -0.1870" value="${longitude}">
            </div>
            <div class="col-md-2 d-flex align-items-end mb-3">
                <button type="button" class="btn btn-danger remove-stop-btn w-100">Remove</button>
            </div>
        </div>`;
        $('#bus-stops-container').append(html);
        updateBusStopUI();
    }

    function clearForm() {
        $('#route-form')[0].reset();
        $('#route_id_field').val('');
        $('#bus-stops-container').empty();
        stopCounter = 0;
        addBusStopRow();
        alert('Form cleared. You can now create a new route.');
    }

    // Initial setup
    addBusStopRow();

    // Event handlers
    $('#add-stop-btn').on('click', () => addBusStopRow());
    $('#new-route-btn').on('click', clearForm);

    $('#bus-stops-container').on('click', '.remove-stop-btn', function() {
        $(this).closest('.bus-stop-row').remove();
        updateBusStopUI();
    });

    $('#load-route-btn').on('click', function() {
        const routeId = $('#edit-route-select').val();
        if (!routeId) {
            alert('Please select a route to edit.');
            return;
        }
        $.ajax({
            url: '{{ url("bus-route/get") }}/' + routeId,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.error) {
                    alert('Error: ' + response.error);
                    return;
                }
                const d = response.data;
                $('#route_id_field').val(d.route_id);
                $('input[name="route_name"]').val(d.route_name);
                $('input[name="driver_name"]').val(d.driver_name);
                $('input[name="driver_contact"]').val(d.driver_contact);
                $('input[name="team_lead_name"]').val(d.team_lead_name);
                $('input[name="team_lead_contact"]').val(d.team_lead_contact);
                $('#route_status').val(d.status);
                $('#bus-stops-container').empty();
                stopCounter = 0;
                if (d.stops && d.stops.length > 0) {
                    d.stops.forEach(stop => addBusStopRow(stop));
                } else {
                    updateBusStopUI();
                }
            },
            error: function() {
                alert('Failed to load route data.');
            }
        });
    });

    // Form submission via AJAX
    $('#route-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: '{{ route("transport.save-route") }}',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(jqXHR) {
                let errorMsg = 'An unexpected error occurred. Please try again.';
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                alert(errorMsg);
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Route');
            }
        });
    });

    // Inline edit buttons in the routes table
    $('.edit-route-btn').on('click', function() {
        const routeId = $(this).data('id');
        $('#edit-route-select').val(routeId);
        $('#load-route-btn').trigger('click');
        $('html, body').animate({ scrollTop: 0 }, 500);
    });

    // --- Route Viewer & Map Logic ---
    const map = L.map('routeMap').setView([5.6037, -0.1870], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    let routeLayer = L.layerGroup().addTo(map);

    $('#route-viewer-select').on('change', function() {
        const routeId = $(this).val();
        routeLayer.clearLayers();
        $('#route-members-container').html('<div class="text-center py-3"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');

        if (!routeId) {
            $('#route-members-container').empty();
            return;
        }

        $.ajax({
            url: '{{ url("bus-route/details") }}',
            type: 'GET',
            data: { route_id: routeId },
            dataType: 'json',
            success: function(response) {
                if (response.stops && response.stops.length > 0) {
                    const latLngs = [];
                    response.stops.forEach(stop => {
                        const lat = parseFloat(stop.latitude);
                        const lng = parseFloat(stop.longitude);
                        if (!isNaN(lat) && !isNaN(lng)) {
                            L.marker([lat, lng])
                                .addTo(routeLayer)
                                .bindPopup('<b>' + stop.stop_name + '</b><br>Time: ' + (stop.take_off_time || '—'));
                            latLngs.push([lat, lng]);
                        }
                    });
                    if (latLngs.length > 1) {
                        const polyline = L.polyline(latLngs, { color: 'blue' }).addTo(routeLayer);
                        map.fitBounds(polyline.getBounds());
                    } else if (latLngs.length === 1) {
                        map.setView(latLngs[0], 15);
                    }
                }

                // Show registered members
                if (response.members_html) {
                    $('#route-members-container').html(response.members_html);
                } else {
                    $('#route-members-container').html('<p class="text-center">No members registered for this route.</p>');
                }
            },
            error: function() {
                $('#route-members-container').html('<p class="text-center text-danger">Failed to load route details.</p>');
                alert('Failed to load route details for map.');
            }
        });
    });
});
</script>
@endpush
