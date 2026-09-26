@extends('layouts.app')

@section('content')
<style>
    .ms-panel-body canvas {
        min-height: 400px;
        max-height: 400px;
    }
</style>

<div class="ms-panel">
    <div class="ms-panel-header"><h6>Filter Report</h6></div>
    <div class="ms-panel-body">
        <form class="row g-3 align-items-end" method="GET" action="{{ url('/report-piecharts') }}">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" value="{{ e($startDate) }}" required>
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" value="{{ e($endDate) }}" required>
            </div>
            <div class="col-md-4">
                <label for="church_type_id" class="form-label">Church Type</label>
                <select name="church_type_id" id="church_type_id" class="form-control">
                    <option value="All">All</option>
                    @foreach($churchTypes as $ct)
                        <option value="{{ $ct->id }}" {{ $currentChurchFilter == $ct->id ? 'selected' : '' }}>{{ e($ct->church_type_name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Gender Distribution</h6></div><div class="ms-panel-body"><canvas id="gender-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Age Distribution</h6></div><div class="ms-panel-body"><canvas id="age-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Attendant Type Distribution</h6></div><div class="ms-panel-body"><canvas id="attendant-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Occupation Distribution</h6></div><div class="ms-panel-body"><canvas id="occupation-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Church Type Distribution</h6></div><div class="ms-panel-body"><canvas id="church-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Status Distribution</h6></div><div class="ms-panel-body"><canvas id="status-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Marital Status Distribution</h6></div><div class="ms-panel-body"><canvas id="marital-status-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>'How Did You Hear' Distribution</h6></div><div class="ms-panel-body"><canvas id="how-did-you-hear-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>'Born Again' Distribution</h6></div><div class="ms-panel-body"><canvas id="born-again-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Water Baptism Distribution</h6></div><div class="ms-panel-body"><canvas id="water-baptism-pie-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Holy Ghost Baptism Distribution</h6></div><div class="ms-panel-body"><canvas id="holy-ghost-baptism-pie-chart"></canvas></div></div></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
    Chart.register(ChartDataLabels);

    const genderData = {!! json_encode($genderData) !!};
    const ageData = {!! json_encode($ageData) !!};
    const attendantData = {!! json_encode($attendantData) !!};
    const occupationData = {!! json_encode($occupationData) !!};
    const churchData = {!! json_encode($churchData) !!};
    const statusData = {!! json_encode($statusData) !!};
    const maritalStatusData = {!! json_encode($maritalStatusData) !!};
    const howDidYouHearData = {!! json_encode($howDidYouHearData) !!};
    const bornAgainData = {!! json_encode($bornAgainData) !!};
    const waterBaptismData = {!! json_encode($waterBaptismData) !!};
    const holyGhostBaptismData = {!! json_encode($holyGhostBaptismData) !!};

    function getChartColors() {
        return [
            'rgba(192, 57, 43, 0.8)', 'rgba(41, 128, 185, 0.8)', 'rgba(243, 156, 18, 0.8)',
            'rgba(39, 174, 96, 0.8)', 'rgba(142, 68, 173, 0.8)', 'rgba(44, 62, 80, 0.8)',
            'rgba(211, 84, 0, 0.8)', 'rgba(0, 150, 136, 0.8)', 'rgba(216, 67, 21, 0.8)'
        ];
    }

    function createPieChart(canvasId, data) {
        const labels = Object.keys(data);
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');

        if (labels.length === 0) {
            ctx.font = "16px Arial";
            ctx.fillStyle = "#888";
            ctx.textAlign = "center";
            ctx.fillText("No data available for this period", ctx.canvas.width / 2, ctx.canvas.height / 2);
            return;
        }

        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{ data: Object.values(data), backgroundColor: getChartColors() }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    datalabels: {
                        formatter: (value, ctx) => {
                            const label = ctx.chart.data.labels[ctx.dataIndex];
                            return label + ' (' + value + ')';
                        },
                        color: '#fff',
                        font: { weight: 'bold', size: 14 },
                        textAlign: 'center'
                    }
                }
            },
            plugins: [ChartDataLabels]
        });
    }

    createPieChart('gender-pie-chart', genderData);
    createPieChart('age-pie-chart', ageData);
    createPieChart('attendant-pie-chart', attendantData);
    createPieChart('occupation-pie-chart', occupationData);
    createPieChart('church-pie-chart', churchData);
    createPieChart('status-pie-chart', statusData);
    createPieChart('marital-status-pie-chart', maritalStatusData);
    createPieChart('how-did-you-hear-pie-chart', howDidYouHearData);
    createPieChart('born-again-pie-chart', bornAgainData);
    createPieChart('water-baptism-pie-chart', waterBaptismData);
    createPieChart('holy-ghost-baptism-pie-chart', holyGhostBaptismData);
</script>
@endpush
