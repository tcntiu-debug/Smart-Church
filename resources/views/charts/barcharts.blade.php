@extends('layouts.app')

@section('content')
<style>
    .ms-panel-body canvas { min-height: 450px; max-height: 450px; }
    .comp-header { background: #f8f9fa; border-radius: 5px; padding: 15px; margin-bottom: 20px; border-left: 5px solid #2980b9; }
    .totals-summary { font-size: 1.2rem; text-align: center; margin-bottom: 15px; background: #f0f2f5; padding: 8px; border-radius: 6px; }
</style>

<div class="ms-panel">
    <div class="ms-panel-header"><h6>General Report Filter</h6></div>
    <div class="ms-panel-body">
        <form class="row g-3 align-items-end" method="GET" action="{{ url('/report-barcharts') }}">
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
                <button type="submit" class="btn btn-primary w-100">Filter Report</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Gender Per Month</h6></div><div class="ms-panel-body"><canvas id="gender-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Age Per Month</h6></div><div class="ms-panel-body"><canvas id="age-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Occupation Per Month</h6></div><div class="ms-panel-body"><canvas id="occupation-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Guest Type Per Month</h6></div><div class="ms-panel-body"><canvas id="attendant-type-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Church Type Per Month</h6></div><div class="ms-panel-body"><canvas id="church-type-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Status Per Month</h6></div><div class="ms-panel-body"><canvas id="status-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Marital Status Per Month</h6></div><div class="ms-panel-body"><canvas id="marital-status-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>How Did You Hear Per Month</h6></div><div class="ms-panel-body"><canvas id="how-did-you-hear-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Born Again Status Per Month</h6></div><div class="ms-panel-body"><canvas id="born-again-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Water Baptism Status Per Month</h6></div><div class="ms-panel-body"><canvas id="water-baptism-bar-chart"></canvas></div></div></div>
    <div class="col-lg-6 col-md-12"><div class="ms-panel"><div class="ms-panel-header"><h6>Holy Ghost Baptism Status Per Month</h6></div><div class="ms-panel-body"><canvas id="holy-ghost-baptism-bar-chart"></canvas></div></div></div>
</div>

<!-- Year-over-Year Comparison Section -->
<div class="ms-panel">
    <div class="ms-panel-header"><h6>Yearly / Monthly Comparison Analysis</h6></div>
    <div class="ms-panel-body">
        <form class="row g-3 align-items-end" method="GET" id="comparisonForm" action="{{ url('/report-barcharts') }}">
            <input type="hidden" name="start_date" value="{{ e($startDate) }}">
            <input type="hidden" name="end_date" value="{{ e($endDate) }}">
            @if($currentChurchFilter && $currentChurchFilter !== 'All')
                <input type="hidden" name="church_type_id" value="{{ e($currentChurchFilter) }}">
            @endif

            <div class="col-md-2">
                <label class="form-label">Compare By</label>
                <select name="comp_type" id="comp_type" class="form-control">
                    <option value="month" {{ $compType == 'month' ? 'selected' : '' }}>Specific Month</option>
                    <option value="year" {{ $compType == 'year' ? 'selected' : '' }}>Full Year</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">First Year</label>
                <select name="comp_y1" class="form-control">
                    @foreach($yearsFound as $y)
                        <option value="{{ $y }}" {{ $y == $compY1 ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Second Year</label>
                <select name="comp_y2" class="form-control">
                    @foreach($yearsFound as $y)
                        <option value="{{ $y }}" {{ $y == $compY2 ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3" id="monthDropdownWrapper">
                <label class="form-label">Month (if month comparison)</label>
                <select name="comp_m" class="form-control">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == $compM ? 'selected' : '' }}>{{ date("F", mktime(0,0,0,$m,10)) }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-success w-100">Compare</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>{{ $comparisonTitle }}</h6></div>
            <div class="ms-panel-body">
                <div class="totals-summary">
                    {!! $summaryText !!}
                </div>
                <canvas id="yoy-comparison-chart"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Data from server
    const genderData = {!! json_encode($genderData) !!};
    const ageData = {!! json_encode($ageData) !!};
    const occupationData = {!! json_encode($occupationData) !!};
    const attendant_typeData = {!! json_encode($attendantTypeData) !!};
    const church_typeData = {!! json_encode($churchTypeData) !!};
    const statusData = {!! json_encode($statusData) !!};
    const maritalStatusData = {!! json_encode($maritalStatusData) !!};
    const howDidYouHearData = {!! json_encode($howDidYouHearData) !!};
    const bornAgainData = {!! json_encode($bornAgainData) !!};
    const waterBaptismData = {!! json_encode($waterBaptismData) !!};
    const holyGhostBaptismData = {!! json_encode($holyGhostBaptismData) !!};
    const labels = {!! json_encode($months) !!};

    const genderKeys = {!! json_encode($genderGroups) !!};
    const ageKeys = {!! json_encode($ageGroups) !!};
    const occupationKeys = {!! json_encode($occupationGroups) !!};
    const attendant_typeKeys = {!! json_encode($attendantTypeGroups) !!};
    const church_typeKeys = {!! json_encode($churchTypeGroups) !!};
    const statusKeys = {!! json_encode($statusGroups) !!};
    const maritalStatusKeys = {!! json_encode($maritalStatusGroups) !!};
    const howDidYouHearKeys = {!! json_encode($howDidYouHearGroups) !!};
    const bornAgainKeys = {!! json_encode($bornAgainGroups) !!};
    const waterBaptismKeys = {!! json_encode($waterBaptismGroups) !!};
    const holyGhostBaptismKeys = {!! json_encode($holyGhostBaptismGroups) !!};

    const backgroundColors = [
        'rgba(192, 57, 43, 0.8)', 'rgba(41, 128, 185, 0.8)', 'rgba(243, 156, 18, 0.8)',
        'rgba(39, 174, 96, 0.8)', 'rgba(142, 68, 173, 0.8)', 'rgba(44, 62, 80, 0.8)',
        'rgba(211, 84, 0, 0.8)', 'rgba(0, 150, 136, 0.8)', 'rgba(216, 67, 21, 0.8)'
    ];

    function createDatasets(data, keys) {
        return keys.map((key, index) => ({
            label: key,
            data: labels.map(label => (data[label] && data[label][key]) || 0),
            backgroundColor: backgroundColors[index % backgroundColors.length],
            borderWidth: 1
        }));
    }

    const chartOptions = { 
        responsive: true, 
        maintainAspectRatio: false,
        scales: { 
            y: { 
                beginAtZero: true, 
                ticks: { stepSize: 1 } 
            } 
        }
    };

    // Initialize all bar charts
    new Chart(document.getElementById('gender-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(genderData, genderKeys) }, options: chartOptions });
    new Chart(document.getElementById('age-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(ageData, ageKeys) }, options: chartOptions });
    new Chart(document.getElementById('occupation-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(occupationData, occupationKeys) }, options: chartOptions });
    new Chart(document.getElementById('attendant-type-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(attendant_typeData, attendant_typeKeys) }, options: chartOptions });
    new Chart(document.getElementById('church-type-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(church_typeData, church_typeKeys) }, options: chartOptions });
    new Chart(document.getElementById('status-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(statusData, statusKeys) }, options: chartOptions });
    new Chart(document.getElementById('marital-status-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(maritalStatusData, maritalStatusKeys) }, options: chartOptions });
    new Chart(document.getElementById('born-again-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(bornAgainData, bornAgainKeys) }, options: chartOptions });
    new Chart(document.getElementById('how-did-you-hear-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(howDidYouHearData, howDidYouHearKeys) }, options: chartOptions });
    new Chart(document.getElementById('water-baptism-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(waterBaptismData, waterBaptismKeys) }, options: chartOptions });
    new Chart(document.getElementById('holy-ghost-baptism-bar-chart').getContext('2d'), { type: 'bar', data: { labels, datasets: createDatasets(holyGhostBaptismData, holyGhostBaptismKeys) }, options: chartOptions });

    // Year-over-Year Comparison Chart
    new Chart(document.getElementById('yoy-comparison-chart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartLabels) !!},
            datasets: [{
                label: {!! json_encode($chartDatasetLabel) !!},
                data: [{{ $totalY1 }}, {{ $totalY2 }}],
                backgroundColor: ['rgba(231, 76, 60, 0.8)', 'rgba(52, 152, 219, 0.8)'],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    // Toggle month dropdown visibility based on comparison type
    const compTypeSelect = document.getElementById('comp_type');
    const monthWrapper = document.getElementById('monthDropdownWrapper');
    function toggleMonthField() {
        if (compTypeSelect && compTypeSelect.value === 'year') {
            monthWrapper.style.display = 'none';
        } else if (monthWrapper) {
            monthWrapper.style.display = 'block';
        }
    }
    if (compTypeSelect) {
        toggleMonthField();
        compTypeSelect.addEventListener('change', toggleMonthField);
    }
</script>
@endpush
