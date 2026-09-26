@extends('layouts.app')

@section('content')
<!-- Today's Church Attendance -->
<div class="ms-panel">
    <div class="ms-panel-header">
        <h6>Today's Church Attendance <span class="badge badge-primary">{{ count($todayAttendance) }}</span></h6>
    </div>
    <div class="ms-panel-body">
        @if(count($todayAttendance) > 0)
        <div class="table-responsive">
            <table class="table table-hover thead-primary mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Service Type</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($todayAttendance as $att)
                    <tr>
                        <td>{{ $att->full_name }}</td>
                        <td>{{ $att->member_phone ?? 'N/A' }}</td>
                        <td>{{ $att->church_type_name ?? 'N/A' }}</td>
                        <td>{{ isset($att->date_created) ? \Carbon\Carbon::parse($att->date_created)->format('h:i A') : 'N/A' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-muted mb-0">No attendance records for today yet.</p>
        @endif
    </div>
</div>

<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center">
        <h6>First-Timers Assigned to Guides</h6>
        <form method="GET" action="{{ url('/aoverview') }}" class="form-inline">
            <div class="form-group mr-2">
                <label for="member_role" class="mr-1">Role:</label>
                <select name="member_role" id="member_role" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="tiu" {{ ($filter_role ?? 'tiu') == 'tiu' ? 'selected' : '' }}>TIU</option>
                    <option value="guest" {{ ($filter_role ?? '') == 'guest' ? 'selected' : '' }}>Guest</option>
                </select>
            </div>
            <div class="form-group mr-2">
                <label for="gender" class="mr-1">Gender:</label>
                <select name="gender" id="gender" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="Male" {{ ($filter_gender ?? '') == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ ($filter_gender ?? '') == 'Female' ? 'selected' : '' }}>Female</option>
                </select>
            </div>
            <a href="{{ url('/aoverview') }}" class="btn btn-sm btn-secondary">Reset</a>
        </form>
    </div>
    <div class="ms-panel-body">
        <div class="accordion" id="guidesAccordion">
            @foreach($grouped_guides as $key => $group)
            @if(!empty($group['guides']))
            <div class="card">
                <div class="card-header p-0" id="heading-{{ $key }}">
                    <h2 class="mb-0">
                        <button class="btn btn-link btn-block text-left p-3 collapsed" type="button" data-toggle="collapse" data-target="#collapse-{{ $key }}" aria-expanded="false" aria-controls="collapse-{{ $key }}">
                            {{ $group['label'] }}
                            <span class="badge badge-primary float-right">{{ count($group['guides']) }}</span>
                        </button>
                    </h2>
                </div>

                <div id="collapse-{{ $key }}" class="collapse" aria-labelledby="heading-{{ $key }}" data-parent="#guidesAccordion">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover thead-primary mb-0">
                                <thead>
                                    <tr>
                                        <th>Guide</th>
                                        <th>Phone Number</th>
                                        <th>Role</th>
                                        <th>Department(s)</th>
                                        <th>Assigned First-Timers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['guides'] as $guide)
                                    @php
                                    $first_timers_html = [];
                                    $first_timers_for_whatsapp = [];
                                    if (!empty($guide['first_timers_data'])) {
                                    $ft_items = explode('~||~', $guide['first_timers_data']);
                                    foreach ($ft_items as $item) {
                                    $parts = explode('~|~', $item);
                                    if (count($parts) >= 3) {
                                    $ft_first_name = $parts[0];
                                    $ft_last_name = $parts[1];
                                    $ft_reg_date = $parts[2];
                                    $ft_full_name = htmlspecialchars("$ft_first_name $ft_last_name");
                                    $first_timers_for_whatsapp[] = "$ft_first_name $ft_last_name";

                                    $diff = date_diff(date_create($ft_reg_date), date_create('now'));
                                    $weeks = floor($diff->format("%a") / 7);

                                    if ($weeks <= 3) $color_style="badge badge-danger" ;
                                        elseif ($weeks < 12) $color_style="badge badge-secondary" ;
                                        else $color_style="badge badge-success" ;

                                        $first_timers_html[]="<span class='{$color_style}'>$ft_full_name</span>" ;
                                        }
                                        }
                                        }

                                        $encodedMessage="" ;
                                        if (!empty($first_timers_for_whatsapp)) {
                                        $names_str=implode("\n- ", $first_timers_for_whatsapp);
                                                        $greeting = " Good day, this is a reminder about the first-timer(s) assigned to you:\n\n- $names_str\n\nPlease follow up with them. Thank you.\nTCN Ikorodu";
                                        $encodedMessage=urlencode($greeting);
                                        }

                                        $phone_number_display=htmlspecialchars($guide['phone_number'] ?? '' );
                                        $phone_number_link=preg_replace('/^0/', '+234' , $guide['phone_number'] ?? '' );

                                        // Logic to display department names based on the JSON string
                                        $department_names_display='N/A' ;
                                        if (!empty($guide['department_name']) && $guide['department_name'] !=='[]' && $guide['department_name'] !=='NULL' ) {
                                        $dept_ids_array=json_decode($guide['department_name'], true);
                                        if (is_array($dept_ids_array) && !empty($dept_ids_array)) {
                                        $current_member_dept_names=[];
                                        foreach ($dept_ids_array as $dept_id) {
                                        $dept_id_int=(int)$dept_id;
                                        if (isset($all_departments[$dept_id_int])) {
                                        $current_member_dept_names[]=htmlspecialchars($all_departments[$dept_id_int]);
                                        }
                                        }
                                        if (!empty($current_member_dept_names)) {
                                        $department_names_display=implode(', ', $current_member_dept_names);
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                <tr>
                                                    <td>
                                                        <a href="{{ url('/my-tasks?guide_id=' . $guide['tiu_member_id'] . '&guide_name=' . urlencode($guide['first_name'] . ' ' . $guide['last_name'])) }}">
    {{ $guide['first_name'] }} {{ $guide['last_name'] }}
</a>
                                                    </td>
                                                    <td>
                                                        @if(!empty($encodedMessage))
                                                            <a href="https://wa.me/{{ $phone_number_link }}?text={{ $encodedMessage }}" target="_blank" title="Send WhatsApp Reminder">
                                                                <i class="fab fa-whatsapp" style="color: green;"></i> {{ $phone_number_display }}
                                                            </a>
                                                        @else
                                                            {{ $phone_number_display }}
                                                        @endif
                                                    </td>
                                                    <td>{{ $guide['member_role'] }}</td>
                                                    <td>{{ $department_names_display }}</td>
                                                    <td>{!! !empty($first_timers_html) ? implode(' ', $first_timers_html) : ' None' !!}</td>
                                        </tr>
                                        @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</div>

<!-- SCRIPTS -->
@push('scripts')
<script>
    $(document).ready(function() {
        // Initialize any accordion behavior if needed
    });
</script>
@endpush
@endsection