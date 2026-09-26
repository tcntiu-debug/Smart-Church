@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <!-- Filter Section -->
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Filter Registration Records</h6></div>
            <div class="ms-panel-body">
                <form method="GET" action="">
                    <div class="form-row">
                        <div class="col-md-5 mb-3">
                            <label for="cohort">Filter by Cohort</label>
                            <select name="cohort" id="cohort" class="form-control">
                                <option value="">-- All Cohorts --</option>
                                @foreach($cohorts as $c)
                                    <option value="{{ $c->cohort_id }}" {{ $selectedCohortId == $c->cohort_id ? 'selected' : '' }}>{{ $c->cohort_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5 mb-3">
                            <label for="year">Filter by Year</label>
                            <select name="year" id="year" class="form-control">
                                <option value="">-- All Years --</option>
                                @foreach($years as $y)
                                    <option value="{{ $y }}" {{ $selectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Registration Details Table -->
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Registration Details</h6></div>
            <div class="ms-panel-body p-0">
                @if(count($members) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>Full Name</th>
                                    <th>Email & Phone</th>
                                    <th>Cohort</th>
                                    <th>Department(s)</th>
                                    <th>Reg. Date</th>
                                    <th>SMART Request</th>
                                    <th>Commitment</th>
                                    <th>Source</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($members as $reg)
                                    <tr>
                                        <td>{{ $reg->first_name }} {{ $reg->last_name }}</td>
                                        <td>
                                            {{ $reg->email }}<br>
                                            <small>{{ $reg->phone_number }}</small>
                                        </td>
                                        <td>{{ $reg->cohort_name ?? 'N/A' }}</td>
                                        <td>
                                            @php
                                                $deptNames = [];
                                                if (!empty($reg->member_role) && !empty($reg->department_name)) {
                                                    $deptIdsArray = json_decode($reg->department_name, true);
                                                    if (is_array($deptIdsArray)) {
                                                        foreach ($deptIdsArray as $id) {
                                                            $cleanId = intval($id);
                                                            if (isset($deptMap[$cleanId])) {
                                                                $deptNames[] = $deptMap[$cleanId];
                                                            }
                                                        }
                                                    }
                                                }
                                            @endphp
                                            @if(!empty($deptNames))
                                                {{ implode(', ', $deptNames) }}
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>{{ \Carbon\Carbon::parse($reg->registration_date)->format('M j, Y') }}</td>
                                        <td>{{ $reg->smart_request }}</td>
                                        <td>{{ $reg->commitment }}</td>
                                        <td>
                                            @if(!empty($reg->how_heard))
                                                {{ $reg->how_heard }}
                                                @if($reg->how_heard === 'Other' && !empty($reg->how_heard_other))
                                                    ({{ $reg->how_heard_other }})
                                                @endif
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if(!empty($reg->member_photo) && file_exists(public_path($reg->member_photo)))
                                                <button class="btn btn-sm btn-info view-photo-btn"
                                                        data-img-src="{{ asset($reg->member_photo) }}"
                                                        data-student-name="{{ $reg->first_name }} {{ $reg->last_name }}">
                                                    <i class="fa fa-eye"></i> View Photo
                                                </button>
                                            @elseif(!empty($reg->profile_photo) && file_exists(public_path($reg->profile_photo)))
                                                <button class="btn btn-sm btn-info view-photo-btn"
                                                        data-img-src="{{ asset($reg->profile_photo) }}"
                                                        data-student-name="{{ $reg->first_name }} {{ $reg->last_name }}">
                                                    <i class="fa fa-eye"></i> View Photo
                                                </button>
                                            @else
                                                <span class="text-muted">No Photo</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-center py-4">No registration records found for the selected filters.</p>
                @endif
            </div>
        </div>

        <!-- Bar Chart Section -->
        @if($chartLabels->count() > 0)
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Registrations by Month/Year</h6></div>
            <div class="ms-panel-body">
                <canvas id="registrationChart" height="80"></canvas>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Photo Viewer Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" role="dialog" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="photoModalLabel">Student Photo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="photoModalImage" src="" alt="Student Photo" class="img-fluid" style="max-height: 400px; border-radius: 5px;">
            </div>
            <div class="modal-footer">
                <a id="photoModalDownloadBtn" href="#" class="btn btn-success" download>
                    <i class="fa fa-download"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(document).ready(function() {
    @if($chartLabels->count() > 0)
    const ctx = document.getElementById('registrationChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($chartLabels) !!},
            datasets: [{
                label: '# of Registrations',
                data: {!! json_encode($chartValues) !!},
                backgroundColor: 'rgba(0, 123, 255, 0.5)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
    @endif

    $('.view-photo-btn').on('click', function(e) {
        e.preventDefault();
        var imgSrc = $(this).data('img-src');
        var studentName = $(this).data('student-name');
        var downloadName = studentName.replace(/\s+/g, '_') + '.jpg';

        $('#photoModalImage').attr('src', imgSrc);
        $('#photoModalLabel').text(studentName + "'s Photo");
        $('#photoModalDownloadBtn').attr('href', imgSrc);
        $('#photoModalDownloadBtn').attr('download', downloadName);
        $('#photoModal').modal('show');
    });
});
</script>
@endpush
