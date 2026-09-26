@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>HANGOUT RECORD</h6>
    </div>
    <div class="ms-panel-body">
        <div class="mb-4">
            <form method="GET" action="{{ route('admin.hangout') }}" class="form-inline">
                <div class="form-group mr-2">
                    <label class="mr-2">From:</label>
                    <input type="date" name="start_date" class="form-control" value="{{ $startDate ?? '' }}">
                </div>
                <div class="form-group mr-2">
                    <label class="mr-2">To:</label>
                    <input type="date" name="end_date" class="form-control" value="{{ $endDate ?? '' }}">
                </div>
                <button type="submit" class="btn btn-primary btn-sm mr-2">Filter</button>
                <a href="{{ route('admin.hangout') }}" class="btn btn-secondary btn-sm">Clear</a>
            </form>
        </div>

        <div class="table-responsive">
            <table id="order-listing" class="table table-hover thead-primary w-100">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Occupation</th>
                        <th>Occupation Detail</th>
                        <th>Member Status</th>
                        <th>Register Date</th>
                        <th>Phone</th>
                        <th>Department</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currentDate = null; $dayCounter = 0; @endphp
                    @forelse($hangouts as $i => $h)
                    @php
                        $hDate = $h->hangout_date ? \Carbon\Carbon::parse($h->hangout_date)->format('Y-m-d') : null;
                        if ($hDate !== $currentDate) {
                            $dayCounter = 1;
                            $currentDate = $hDate;
                        } else {
                            $dayCounter++;
                        }
                    @endphp
                    <tr>
                        <td>{{ $dayCounter }}</td>
                        <td>
                            @php
                                $rawPhone = $h->phone_number ?? '';
                                $waPhone = preg_replace('/[^0-9]/', '', $rawPhone);
                                if (strlen($waPhone) === 11 && substr($waPhone, 0, 1) === '0') {
                                    $waPhone = '234' . substr($waPhone, 1);
                                }
                                $msg = "Hello " . e($h->first_name . ' ' . $h->last_name) . ",\n\nYou can now login to the church app.\n\nGod bless you!";
                            @endphp
                            <a href="https://wa.me/{{ $waPhone }}?text={{ urlencode($msg) }}" target="_blank" title="Send Login Details">
                                <i class="fab fa-whatsapp wa-icon" style="color:#25D366;"></i>
                            </a>
                            {{ $h->first_name }} {{ $h->last_name }}
                        </td>
                        <td>{{ $h->occupation ?? '—' }}</td>
                        <td>{{ $h->occupation2 ?? '—' }}</td>
                        <td>{{ $h->member_status ?? '—' }}</td>
                        <td>{{ $h->hangout_date ? \Carbon\Carbon::parse($h->hangout_date)->format('M d, Y') : '—' }}</td>
                        <td>{{ $h->phone_number ?? '—' }}</td>
                        <td>{{ $h->department ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">No hangout records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>ATTENDANCE SUMMARY</h6>
    </div>
    <div class="ms-panel-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Year</th>
                        <th>Month</th>
                        <th>Total Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary as $i => $s)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $s->yr }}</td>
                        <td>{{ $s->mnth }}</td>
                        <td><strong>{{ $s->total }}</strong></td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-center py-3 text-muted">No summary data.</td></tr>
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
            "order": [[5, "desc"]]
        });

        // ========== AUTO-REFRESH: Reload page every 5 seconds when date range is set ==========
        var startDate = '{{ $startDate ?? '' }}';
        var endDate = '{{ $endDate ?? '' }}';
        if (startDate && endDate) {
            console.log('Auto-refresh enabled - will reload every 5 seconds');
            setInterval(function() {
                location.reload(true);
            }, 5000);
        } else {
            console.log('No date range - auto-refresh disabled');
        }
    });
</script>
@endpush
