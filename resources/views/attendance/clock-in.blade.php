@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Clock In / Mark Attendance</h6>
    </div>
    <div class="ms-panel-body">
        <form id="attendanceForm">
            @csrf
            <div class="form-group">
                <label for="search_phone">Search by Phone Number</label>
                <input type="tel" class="form-control" id="search_phone" 
                       placeholder="Enter phone number to search" required>
            </div>
            <div class="form-group text-center">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-search"></i> Search & Clock In
                </button>
            </div>
        </form>

        <div id="searchResults" class="mt-4" style="display:none;">
            <div class="ms-panel mt-3">
                <div class="ms-panel-header">
                    <h6>Search Results</h6>
                </div>
                <div class="ms-panel-body">
                    <div id="resultsList"></div>
                </div>
            </div>
        </div>

        <div id="noResults" class="alert alert-warning mt-3" style="display:none;">
            <i class="fas fa-exclamation-triangle"></i> No attendees found. 
            <a href="{{ url('/ft-register') }}" class="alert-link">Register as First Timer</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    $('#attendanceForm').on('submit', function(e) {
        e.preventDefault();
        var phone = $('#search_phone').val().trim();
        if (!phone) return;

        $.ajax({
            url: '{{ url("/attendance/search") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                phone: phone
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.count > 0) {
                    var html = '<form id="submitAttendanceForm" method="POST" action="{{ url("/attendance/submit") }}">';
                    html += '{{ csrf_field() }}';
                    resp.data.forEach(function(item) {
                        var name = item.first_name + ' ' + item.last_name;
                        var val = item.id + '|' + item.member_type + '|' + name + '|' + (item.church_type_id || '');
                        html += '<div class="custom-control custom-checkbox mb-2">';
                        html += '<input type="checkbox" class="custom-control-input" name="attendance[]" ';
                        html += 'value="' + val + '" id="att_' + item.member_type + '_' + item.id + '" checked>';
                        html += '<label class="custom-control-label" for="att_' + item.member_type + '_' + item.id + '">';
                        html += '<strong>' + name + '</strong> <span class="badge badge-info">' + item.member_type.replace('_', ' ') + '</span>';
                        html += ' &mdash; ' + item.phone_number;
                        html += '</label></div>';
                    });
                    html += '<hr><button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Clock In Selected</button>';
                    html += '</form>';
                    $('#resultsList').html(html);
                    $('#searchResults').show();
                    $('#noResults').hide();
                } else {
                    $('#searchResults').hide();
                    $('#noResults').show();
                }
            },
            error: function() {
                alert('Search failed. Please try again.');
            }
        });
    });
});
</script>
@endpush
