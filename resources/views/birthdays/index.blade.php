@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center">
        <h6>Member Birthdays</h6>
        <form method="GET" action="{{ route('birthdays.filter') }}" class="form-inline">
            <div class="form-group mr-2">
                <label class="mr-1">Month:</label>
                <select name="month" class="form-control form-control-sm" onchange="this.form.submit()">
                    @foreach($months ?? [] as $num => $name)
                        <option value="{{ $num }}" {{ ($month ?? $currentMonth ?? now()->month) == $num ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('birthdays.index') }}" class="btn btn-sm btn-secondary">Reset</a>
        </form>
    </div>
    <div class="ms-panel-body">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <!-- Upcoming Birthdays (Next 7 Days) -->
        @if(isset($upcomingBirthdays) && count($upcomingBirthdays) > 0)
            <div class="alert alert-warning">
                <h6><i class="fas fa-birthday-cake"></i> Member's Upcoming Birthdays (Next 7 Days)</h6>
            </div>
            <div class="table-responsive mb-4">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Birthday</th>
                            <th>Phone Number</th>
                            <th>Days Left</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($upcomingBirthdays as $b)
                        <tr>
                            <td>{{ $b->first_name ?? '' }} {{ $b->last_name ?? '' }}</td>
                            <td>{{ $b->birthday_date ?? '' }}</td>
                            <td>{{ $b->phone_number ?? 'N/A' }}</td>
                            <td>
                                <span class="badge {{ $b->days_left <= 1 ? 'badge-danger' : 'badge-warning' }}">
                                    {{ $b->days_left }} days
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-success birthday-wish" 
                                        data-name="{{ $b->first_name ?? '' }}" 
                                        data-phone="{{ $b->phone_number ?? '' }}">
                                    <i class="fab fa-whatsapp"></i> Send Wish
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('.birthday-wish').on('click', function() {
        var name = $(this).data('name');
        var phone = $(this).data('phone');
        
        if (!phone || phone === 'N/A') {
            alert('No phone number available for ' + name);
            return;
        }
        
        if (confirm('Send birthday wish to ' + name + ' (' + phone + ')?')) {
            $.ajax({
                url: '{{ url("/birthdays/send-wish") }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    phone: phone,
                    name: name
                },
                success: function(resp) {
                    if (resp.success && resp.whatsapp_url) {
                        window.open(resp.whatsapp_url, '_blank');
                    } else {
                        alert(resp.message || 'Error sending wish');
                    }
                },
                error: function() {
                    alert('Server error. Please try again.');
                }
            });
        }
    });
});
</script>
@endpush