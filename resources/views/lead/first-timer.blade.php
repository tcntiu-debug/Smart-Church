@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>First Timers Record</h6>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" class="mb-4">
                <div class="row">
                    <div class="col-md-3">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-3">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-3">
                        <label>Gender</label>
                        <select name="gender_filter" class="form-control">
                            <option value="">All Genders</option>
                            <option value="Male" {{ $genderFilter == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ $genderFilter == 'Female' ? 'selected' : '' }}>Female</option>
                        </select>
                    </div>
                    <div class="col-md-3 align-self-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ url()->current() }}" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover" id="first-timers-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Occupation</th>
                            <th>Guest Type</th>
                            <th>Church Type</th>
                            <th>Status</th>
                            <th>Register Date</th>
                            <th>Guide</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($firstTimers as $index => $ft)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $ft->first_name }} {{ $ft->last_name }}</td>
                            <td>
                                <a href="https://wa.me/{{ $ft->whatsapp_phone }}" target="_blank" class="text-success">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                {{ $ft->phone_number }}
                            </td>
                            <td>{{ $ft->gender ?? 'N/A' }}</td>
                            <td>{{ $ft->age ?? 'N/A' }}</td>
                            <td>{{ $ft->occupation ?? 'N/A' }}</td>
                            <td>{{ $ft->attendant_type ?? 'N/A' }}</td>
                            <td>{{ $ft->church_type_name ?? 'N/A' }}</td>
                            <td>{{ $ft->status ?? 'N/A' }}</td>
                            <td>{{ \Carbon\Carbon::parse($ft->register_date)->format('M d, Y h:i A') }}</td>
                            <td>{{ $ft->guide_name ?? 'Unassigned' }}</td>
                        </tr>
                        @empty
                        <!-- No rows - message will show below table -->
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(count($firstTimers) == 0)
                <div class="alert alert-info text-center mt-3">No first timers found for your church type(s).</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    // Only initialize DataTable if there are actual data rows
    if ($('#first-timers-table tbody tr').length > 0) {
        $('#first-timers-table').DataTable({
            responsive: true,
            scrollX: true,
            pageLength: 25
        });
    }
});
</script>
@endpush
@endsection