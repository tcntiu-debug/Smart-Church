@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active">Transport Analytics</li>
            </ol>
        </nav>

        <!-- Filter Panel -->
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Filter Report</h6></div>
            <div class="ms-panel-body">
                <form class="row" method="GET">
                    <div class="col-md-4">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-4">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block">Filter Analytics</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- Route Usage Chart -->
            <div class="col-lg-6 col-md-12">
                <div class="ms-panel">
                    <div class="ms-panel-header"><h6>Route Usage</h6></div>
                    <div class="ms-panel-body">
                        <canvas id="routeChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top 5 Stops Chart -->
            <div class="col-lg-6 col-md-12">
                <div class="ms-panel">
                    <div class="ms-panel-header"><h6>Top 5 Stops</h6></div>
                    <div class="ms-panel-body">
                        <canvas id="stopChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Usage Log Table -->
            <div class="col-md-12">
                <div class="ms-panel">
                    <div class="ms-panel-header"><h6>Usage Log</h6></div>
                    <div class="ms-panel-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Route</th>
                                        <th>Count</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($tableData as $r)
                                    <tr>
                                        <td>{{ date('d M, Y', strtotime($r->attendance_date)) }}</td>
                                        <td>{{ $r->route_name }}</td>
                                        <td><strong>{{ $r->total }}</strong></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No attendance records found.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .ms-panel-body canvas {
        min-height: 400px;
        max-height: 400px;
    }
</style>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/Chart.bundle.min.js') }}"></script>
<script>
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
        yAxes: [{
            ticks: { beginAtZero: true, stepSize: 1 }
        }]
    }
};

// Route Bar Chart
const routeLabels = {!! json_encode($routeLabels) !!};
if (routeLabels.length > 0) {
    new Chart(document.getElementById('routeChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: routeLabels,
            datasets: [{
                label: 'Passengers',
                data: {!! json_encode($routeCounts) !!},
                backgroundColor: 'rgba(41, 128, 185, 0.8)',
                borderWidth: 1
            }]
        },
        options: chartOptions
    });
} else {
    document.getElementById('routeChart').parentElement.innerHTML = '<div class="text-center py-5 text-muted">No attendance data available for the selected period.</div>';
}

// Stop Pie Chart
const stopLabels = {!! json_encode($stopLabels) !!};
if (stopLabels.length > 0) {
    new Chart(document.getElementById('stopChart').getContext('2d'), {
        type: 'pie',
        data: {
            labels: stopLabels,
            datasets: [{
                data: {!! json_encode($stopCounts) !!},
                backgroundColor: ['#ff8c00', '#357ffa', '#f0ad4e', '#5cb85c', '#d9534f']
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });
} else {
    document.getElementById('stopChart').parentElement.innerHTML = '<div class="text-center py-5 text-muted">No stop attendance data available.</div>';
}
</script>
@endpush
