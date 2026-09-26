@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Filter Section -->
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Filter Attendance Records</h6></div>
            <div class="ms-panel-body">
                <form method="GET" action="">
                    <div class="form-row">
                        <div class="col-md-4 mb-3">
                            <label for="cohort">Filter by Cohort</label>
                            <select name="cohort" id="cohort" class="form-control">
                                <option value="">-- All Cohorts --</option>
                                @foreach($cohorts as $c)
                                    <option value="{{ $c->cohort_name }}" {{ $selectedCohort == $c->cohort_name ? 'selected' : '' }}>{{ $c->cohort_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate }}">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cohort Summary -->
        @if(count($cohortSummary) > 0)
            <div class="ms-panel">
                <div class="ms-panel-header"><h6>Cohort Summary</h6></div>
                <div class="ms-panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="bg-info text-white">
                                <tr>
                                    <th>Cohort</th>
                                    <th>Total Students</th>
                                    <th>Present Count</th>
                                    <th>Absent Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cohortSummary as $cohort => $summary)
                                    <tr>
                                        <td>{{ $cohort }}</td>
                                        <td>{{ count($summary['students']) }}</td>
                                        <td class="text-success font-weight-bold">{{ $summary['present_count'] }}</td>
                                        <td class="text-danger font-weight-bold">{{ $summary['absent_count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- Detailed Attendance Table -->
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Attendance Details</h6></div>
            <div class="ms-panel-body p-0">
                @if(count($students) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Student Name</th>
                                    <th>Cohort</th>
                                    @foreach($weeks as $w)
                                        <th class="text-center">Week {{ $w }}</th>
                                    @endforeach
                                    <th class="text-center">Attendance (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($students as $studentName => $data)
                                    @php
                                        $presentCount = 0;
                                        $totalCount = 0;
                                    @endphp
                                    <tr>
                                        <td>{{ $studentName }}</td>
                                        <td>{{ $data['cohort'] }}</td>
                                        @foreach($weeks as $w)
                                            @php
                                                $status = $data['weeks'][$w] ?? '-';
                                                if ($status === 'Present') {
                                                    $presentCount++;
                                                    $totalCount++;
                                                } elseif ($status === 'Absent') {
                                                    $totalCount++;
                                                }
                                            @endphp
                                            <td class="text-center">
                                                @if($status === 'Present')
                                                    <span class="badge badge-success">P</span>
                                                @elseif($status === 'Absent')
                                                    <span class="badge badge-danger">A</span>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        @php
                                            $percentage = $totalCount > 0 ? round(($presentCount / $totalCount) * 100, 1) : 0;
                                        @endphp
                                        <td class="text-center font-weight-bold">
                                            {{ $percentage }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-center py-4">No attendance records found for the selected filters.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
