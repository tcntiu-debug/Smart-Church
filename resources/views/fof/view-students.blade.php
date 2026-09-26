@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>FOF Mark Attendance</h6></div>
            <div class="ms-panel-body">
                <form method="GET" action="" class="mb-0" id="fof-mark-attendance-form">
                    <div class="form-row">
                        <div class="col-md-5 mb-3">
                            <label for="cohort_id">Select Cohort</label>
                            <select name="cohort_id" id="attendance_cohort_select" class="form-control">
                                <option value="">-- All Active Cohorts --</option>
                                @foreach($cohorts as $c)
                                    <option value="{{ $c->cohort_id }}" {{ $cohortId == $c->cohort_id ? 'selected' : '' }}>{{ $c->cohort_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="date">Attendance Date</label>
                            <input type="date" name="date" id="date" class="form-control" value="{{ $date }}">
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-block">Load Students</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($students->count() > 0)
            <div class="ms-panel">
                <div class="ms-panel-header d-flex justify-content-between align-items-center">
                    <h6>Week <span id="current-week-label">1</span> &mdash; {{ $date ?? date('Y-m-d') }}</h6>
                    <div class="d-flex align-items-center">
                        <label class="mr-2 mb-0 font-weight-bold">Week:</label>
                        <select id="week-select" class="form-control form-control-sm" style="width: 120px;">
                            @for($w = 1; $w <= 8; $w++)
                                <option value="{{ $w }}">Week {{ $w }}</option>
                            @endfor
                        </select>
                        <button class="btn btn-primary btn-sm ml-2" id="load-week-btn">Load</button>
                    </div>
                </div>
                <div class="ms-panel-body p-0">
                    <div id="attendance-summary" class="alert alert-info d-none mx-3 mt-3">
                        <strong>Summary:</strong> <span id="present-count">0</span> Present, <span id="absent-count">0</span> Absent
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0">
                            <thead class="bg-primary text-white">
                                <tr>
                                    <th>#</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($students as $index => $student)
                                    @php
                                        $studentId = $student->id;
                                        $currMarkedStatus = $markedToday[$studentId] ?? '';
                                    @endphp
                                    <tr id="student-row-{{ $studentId }}" data-student-id="{{ $studentId }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $student->first_name }} {{ $student->last_name }}</td>
                                        <td>{{ $student->email }}</td>
                                        <td>{{ $student->phone_number }}</td>
                                        <td>
                                            <select class="form-control form-control-sm attendance-status-select" data-student-id="{{ $studentId }}" data-cohort="" data-week="1" style="width: 130px;">
                                                <option value="">-- Mark --</option>
                                                <option value="Present" {{ $currMarkedStatus === 'Present' ? 'selected' : '' }}>Present</option>
                                                <option value="Absent" {{ $currMarkedStatus === 'Absent' ? 'selected' : '' }}>Absent</option>
                                            </select>
                                            <span class="badge badge-success ml-1 d-none" id="saved-badge-{{ $studentId }}">Saved</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ms-panel-footer text-right">
                    <button class="btn btn-success" id="finish-week-btn"><i class="fa fa-lock"></i> Finish Marking Week</button>
                    <button class="btn btn-warning" id="edit-week-btn" style="display:none;"><i class="fa fa-unlock"></i> Edit Week</button>
                </div>
            </div>
        @elseif($cohortId)
            <div class="alert alert-warning">No students found for the selected cohort.</div>
        @else
            <div class="alert alert-info">Please select a cohort to view students.</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var currentWeek = 1;
    var currentCohortId = {{ $cohortId ?: 'null' }};
    var cohortName = '';

    function updateAttendanceData() {
        if (!currentCohortId) {
            Swal.fire('Info', 'Please select a cohort.', 'info');
            return;
        }

        $.get('{{ route("fof.get-attendance") }}', {
            cohort_id: currentCohortId,
            week: currentWeek
        }, function(response) {
            cohortName = response.cohort_name;
            $('#current-week-label').text(currentWeek);
            var presentCount = 0;
            var absentCount = 0;

            $('.attendance-status-select').each(function() {
                var studentId = $(this).data('student-id');
                var $badge = $('#saved-badge-' + studentId);
                var status = '';

                if (response.attendance && response.attendance[studentId]) {
                    status = response.attendance[studentId].status;
                    if (status === 'Present') presentCount++;
                    if (status === 'Absent') absentCount++;
                }

                $(this).val(status);
                $(this).data('week', currentWeek);
                $(this).data('cohort', cohortName);

                if (response.finished) {
                    $(this).prop('disabled', true);
                    if (status) {
                        $badge.removeClass('d-none').text('Saved');
                    } else {
                        $badge.addClass('d-none');
                    }
                } else {
                    $(this).prop('disabled', false);
                    if (status) {
                        $badge.removeClass('d-none').text('Saved');
                    } else {
                        $badge.addClass('d-none');
                    }
                }
            });

            $('#present-count').text(presentCount);
            $('#absent-count').text(absentCount);
            $('#attendance-summary').removeClass('d-none');

            if (response.finished) {
                $('#finish-week-btn').prop('disabled', true).text('Week ' + currentWeek + ' Finished');
                $('#edit-week-btn').show();
            } else {
                $('#finish-week-btn').prop('disabled', false).text('Finish Marking Week ' + currentWeek);
                $('#edit-week-btn').hide();
            }
        }, 'json');
    }

    $('#load-week-btn').on('click', function() {
        currentWeek = parseInt($('#week-select').val());
        updateAttendanceData();
    });

    $(document).on('change', '.attendance-status-select', function() {
        var studentId = $(this).data('student-id');
        var cohort = $(this).data('cohort') || cohortName;
        var week = $(this).data('week') || currentWeek;
        var status = $(this).val();

        if (!status) return;

        Swal.fire({
            title: 'Mark ' + $(this).find("option:selected").text() + '?',
            text: 'Update attendance for this student?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('{{ route("fof.mark-attendance") }}', {
                    _token: '{{ csrf_token() }}',
                    student_id: studentId,
                    cohort: cohort,
                    week: week,
                    status: status
                }, function(response) {
                    if (response.status === 'success') {
                        $('#saved-badge-' + studentId).removeClass('d-none').text('Saved');
                        updateAttendanceData();
                    }
                }, 'json');
            }
        });
    });

    $('#finish-week-btn').on('click', function() {
        if (!cohortName) {
            Swal.fire('Error', 'Please load a cohort first.', 'error');
            return;
        }
        Swal.fire({
            title: 'Finish Week ' + currentWeek + '?',
            text: 'Once finished, attendance cannot be edited until unlocked.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            confirmButtonText: 'Yes, finish marking'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('{{ route("fof.finish-week") }}', {
                    _token: '{{ csrf_token() }}',
                    cohort: cohortName,
                    week: currentWeek
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Done!', response.message, 'success');
                        updateAttendanceData();
                    }
                }, 'json');
            }
        });
    });

    $('#edit-week-btn').on('click', function() {
        if (!cohortName) {
            Swal.fire('Error', 'Please load a cohort first.', 'error');
            return;
        }
        Swal.fire({
            title: 'Unlock Week ' + currentWeek + '?',
            text: 'This allows re-marking attendance.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'Yes, unlock'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('{{ route("fof.edit-week") }}', {
                    _token: '{{ csrf_token() }}',
                    cohort: cohortName,
                    week: currentWeek
                }, function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Unlocked!', response.message, 'success');
                        updateAttendanceData();
                    }
                }, 'json');
            }
        });
    });

    $('#attendance_cohort_select, #date').on('change', function() {
        currentCohortId = $('#attendance_cohort_select').val();
        $('#fof-mark-attendance-form').submit();
    });

    if (currentCohortId) {
        setTimeout(function() {
            updateAttendanceData();
        }, 500);
    }
});
</script>
@endpush
