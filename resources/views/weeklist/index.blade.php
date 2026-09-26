@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>{{ $page_title }}</h6>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <form method="GET" action="{{ route('weeklist.index') }}" class="mb-4">
                <div class="row align-items-end">
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="start_date">Start Date</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $start_date_filter }}">
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="end_date">End Date</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $end_date_filter }}">
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="guest_type">Guest Type</label>
                        <select class="form-control" name="guest_type" id="guest_type">
                            <option value="">All Guest Types</option>
                            @foreach($guestTypes as $type)
                                <option value="{{ $type }}" {{ $guest_type_filter == $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <label for="church_type_id">Church Type</label>
                        <select class="form-control" name="church_type_id" id="church_type_id">
                            <option value="All">All Church Types</option>
                            @foreach($churchTypes as $ct)
                                <option value="{{ $ct->id }}" {{ $church_type_id_filter == $ct->id ? 'selected' : '' }}>{{ $ct->church_type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-6 mb-3">
                        <button type="submit" class="btn btn-primary w-100 mb-2">Apply</button>
                        <a href="{{ route('weeklist.index') }}" class="btn btn-secondary w-100">Reset</a>
                    </div>
                </div>
            </form>

            <!-- Google Meet Link -->
            <p class="mb-3">
                <a href="https://meet.google.com/mus-whut-qus" target="_blank" class="btn btn-link">
                    <i class="fas fa-video"></i> Click to Join Prayers Meetings
                </a>
            </p>

            <div class="table-responsive">
                <table id="first-timers-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>Full Name</th>
                            <th>Guest Type</th>
                            <th>Church Type</th>
                            <th>Gender</th>
                            <th>Guide</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $index => $row)
                            @php
                                $whatsappPhone = $row->whatsapp_phone;
                                $encodedMessage = urlencode($row->whatsapp_message);
                                $guideWhatsAppMessage = "";
                                if ($row->isGuideAssigned) {
                                    $salutation = ($row->guide_gender == "Male") ? "Hello Sir. " : "Hello Ma. ";
                                    $guideWhatsAppMessage = $salutation . "I trust this meets you well?\n\nA first-timer has been assigned to you - " . $row->first_name . " " . $row->last_name . ".\n\n*Login to the TIU APP for details.*";
                                    $guideWhatsAppMessage = urlencode($guideWhatsAppMessage);
                                }
                            @endphp
                            <tr class="{{ $row->isDuplicate ? 'table-warning' : '' }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    @if(in_array($member_role, ['Super User', 'Admin']) && $row->status != "Disable")
                                        <a href="https://wa.me/{{ $whatsappPhone }}?text={{ $encodedMessage }}" target="_blank" title="Send WhatsApp message to {{ $row->first_name }} {{ $row->last_name }}">
                                            <i class="fab fa-whatsapp" style="color: green; margin-right: 5px;"></i>
                                        </a>
                                    @endif
                                    {{ $row->first_name }} {{ $row->last_name }}
                                    @if($row->isDuplicate)
                                        <span class="badge badge-warning"><i class="fas fa-exclamation-triangle"></i> Possible Duplicate</span>
                                    @endif
                                </td>
                                <td>{{ $row->attendant_type }}</td>
                                <td>{{ $row->church_type_name ?? 'N/A' }}</td>
                                <td>{{ $row->gender ?? 'N/A' }}</td>
                                <td>
                                    @if($row->isGuideAssigned && $row->status != "Disable")
                                        <a href="https://wa.me/{{ $row->guide_phone }}?text={{ $guideWhatsAppMessage }}" target="_blank" title="Contact guide {{ $row->guide_name }}">
                                            <i class="fab fa-whatsapp" style="color: green; margin-right: 5px;"></i>
                                            {{ $row->guide_name }}
                                        </a>
                                    @else
                                        Unassigned
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No records found for the selected period.</td≯
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    @media (max-width: 767px) {
        #first-timers-table thead { display: none; }
        #first-timers-table, #first-timers-table tbody, #first-timers-table tr, #first-timers-table td { display: block; width: 100%; }
        #first-timers-table tr { margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 5px; }
        #first-timers-table td { text-align: right; padding-left: 50%; position: relative; border-bottom: 1px solid #eee; padding-top: 12px; padding-bottom: 12px; }
        #first-timers-table td::before { content: attr(data-label); position: absolute; left: 15px; width: 45%; padding-right: 10px; white-space: nowrap; text-align: left; font-weight: bold; }
    }
</style>
@endsection