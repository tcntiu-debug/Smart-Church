@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <div class="d-flex justify-content-between align-items-center">
            <h6>Attendance Report</h6>
            <form method="GET" action="{{ route('attendance.report') }}" class="form-inline">
                <div class="form-group mr-2">
                    <label for="report_date" class="mr-2">Date:</label>
                    <input type="date" class="form-control form-control-sm" name="report_date" value="{{ $selectedDate }}">
                </div>
                <div class="form-group mr-2">
                    <label for="church_type" class="mr-2">Service:</label>
                    <select name="church_type" class="form-control form-control-sm">
                        <option value="0">All Services</option>
                        @foreach($churchTypes as $ct)
                            <option value="{{ $ct->church_type_id }}" {{ $churchTypeId == $ct->church_type_id ? 'selected' : '' }}>
                                {{ $ct->church_type_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group mr-2">
                    <label for="member_type" class="mr-2">Type:</label>
                    <select name="member_type" class="form-control form-control-sm">
                        <option value="both" {{ $memberTypeFilter == 'both' ? 'selected' : '' }}>Both</option>
                        <option value="members" {{ $memberTypeFilter == 'members' ? 'selected' : '' }}>Members Only</option>
                        <option value="first_timers" {{ $memberTypeFilter == 'first_timers' ? 'selected' : '' }}>First Timers Only</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>
    <div class="ms-panel-body">
        <div class="alert alert-info">
            <strong>Report Date:</strong> {{ \Carbon\Carbon::parse($selectedDate)->format('l, F j, Y') }}
        </div>

        @if($missingMembers->count() > 0)
            <div class="ms-panel mt-3">
                <div class="ms-panel-header bg-warning text-white">
                    <h6 class="mb-0">Missing Members ({{ $missingMembers->count() }}) — Not Clocked In</h6>
                </div>
                <div class="ms-panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($missingMembers as $i => $m)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $m->last_name }}, {{ $m->first_name }}</td>
                                    <td>{{ $m->phone_number }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if($missingFirstTimers->count() > 0)
            <div class="ms-panel mt-3">
                <div class="ms-panel-header bg-info text-white">
                    <h6 class="mb-0">Unintegrated First Timers ({{ $missingFirstTimers->count() }}) — Not Clocked In</h6>
                </div>
                <div class="ms-panel-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($missingFirstTimers as $i => $ft)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td>{{ $ft->last_name }}, {{ $ft->first_name }}</td>
                                    <td>{{ $ft->phone_number }}</td>
                                    <td><span class="badge badge-warning">{{ $ft->status }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        @if($missingMembers->count() == 0 && $missingFirstTimers->count() == 0)
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h5>All members clocked in for {{ \Carbon\Carbon::parse($selectedDate)->format('F j, Y') }}!</h5>
            </div>
        @endif
    </div>
</div>
@endsection
