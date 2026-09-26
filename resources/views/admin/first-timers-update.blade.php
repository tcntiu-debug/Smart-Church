s @extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>FIRST TIMER UPDATES REPORT</h6>
    </div>
    <div class="ms-panel-body">
        <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
            <form class="d-flex flex-wrap align-items-center gap-2" method="GET" action="{{ route('admin.first-timers-update') }}">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="{{ $startDate ?? '' }}">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="{{ $endDate ?? '' }}">
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </form>
            <div class="col-md-auto d-flex gap-2">
                <a href="?filter=this_week" class="btn btn-info {{ ($filter ?? '') === 'this_week' ? 'active-filter' : '' }}">Birthdays This Week</a>
                <a href="{{ route('admin.first-timers-update') }}" class="btn btn-secondary">Clear Filter</a>
            </div>
        </div>
        <div class="table-responsive">
            <table id="order-listing" class="table table-hover thead-primary w-100">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Department</th>
                        <th>Cluster</th>
                        <th>House Fellowship</th>
                        <th>Foundation of Faith</th>
                        <th>Water Baptism</th>
                        <th>Holy Ghost Baptism</th>
                        <th>Birthday</th>
                        <th>Date Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($updates as $i => $u)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $u->first_name }} {{ $u->last_name }}</td>
                        <td>{{ $u->phone_number }}</td>
                        <td>{{ $u->department ?? '—' }}</td>
                        <td>{{ $u->cluster ?? '—' }}</td>
                        <td>{{ $u->house_felloship ?? '—' }}</td>
                        <td>{{ $u->foundation_of_faith ?? '—' }}</td>
                        <td>{{ $u->water_baptism ?? '—' }}</td>
                        <td>{{ $u->holy_ghost_baptism ?? '—' }}</td>
                        <td>{{ $u->birthday ?? '—' }}</td>
                        <td>{{ $u->created_date ? \Carbon\Carbon::parse($u->created_date)->format('Y-m-d') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center py-4 text-muted">No updates found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#order-listing').DataTable({
            "aLengthMenu": [[5, 10, 15, -1], [5, 10, 15, "All"]],
            "iDisplayLength": 10,
            "language": { search: "" }
        });
    });
</script>
<style>
    .active-filter { background-color: #0062cc !important; border-color: #005cbf !important; color: white !important; }
</style>
@endpush
