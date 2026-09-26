@extends('layouts.app')

@section('content')
<style>
    .task-text { font-size: 1.05em; color: #333; font-weight: 500; }
    .week-badge { background-color: #6c757d; color: #fff; padding: 2px 7px; border-radius: 4px; font-size: 0.75em; text-transform: uppercase; }
    .log-container { background: #f9f9f9; padding: 8px; border-radius: 5px; border: 1px solid #eee; }
    .log-comment { font-size: 0.9em; color: #444; display: block; line-height: 1.4; margin-bottom: 4px; }
    .log-date { color: #999; font-size: 0.75em; font-style: italic; }
    .wa-ft-link { color: #25D366; font-size: 1.3rem; margin-left: 8px; vertical-align: middle; transition: 0.2s; }
    .wa-ft-link:hover { color: #128C7E; transform: scale(1.1); }
    .attendant-type { font-size: 0.8em; color: #5a5a5a; font-style: italic; background: #e9ecef; padding: 1px 5px; border-radius: 3px; }
    .filter-section { background: #f4f7f6; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; margin-bottom: 20px; }
</style>

<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6><i class="fa fa-list-ul"></i> Pending Tasks Action Board</h6>
            <span class="badge bg-primary px-3 py-2">Total Records: {{ count($pending_actions) }}</span>
        </div>
        <div class="card-body">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="{{ route('pending-tasks.index') }}">
                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <label class="font-weight-bold">Filter by Guide Touchpoint:</label>
                            <select name="touchpoint" class="form-control">
                                <option value="">-- All Touchpoints --</option>
                                <option value="None" {{ $filter_tp == 'None' ? 'selected' : '' }}>None (Unassigned)</option>
                                @foreach($tp_list as $tp)
                                    <option value="{{ $tp->subgroup }}" {{ $filter_tp == $tp->subgroup ? 'selected' : '' }}>
                                        {{ $tp->subgroup }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100"><i class="fa fa-filter"></i> Apply</button>
                        </div>
                        @if($filter_tp !== '')
                        <div class="col-md-2">
                            <a href="{{ route('pending-tasks.index') }}" class="btn btn-secondary w-100">Reset</a>
                        </div>
                        @endif
                    </div>
                </form>
            </div>

            @if(empty($pending_actions))
                <div class="alert alert-success text-center py-5">
                    <h4><i class="fa fa-check-circle"></i> No pending tasks found for your campus!</h4>
                    <p class="mb-0">You are viewing records for campus ID: {{ $campus_id }}</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead class="table-primary">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 20%;">First Timer</th>
                                <th style="width: 25%;">Next Action Required</th>
                                <th style="width: 20%;">Last Attempt on this Task</th>
                                <th style="width: 15%;">Assigned Guide</th>
                                <th class="text-right" style="width: 15%;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $counter = 1; @endphp
                            @foreach($pending_actions as $action)
                                @php
                                    $ft_full_name = $action->ft_first_name . ' ' . $action->ft_last_name;
                                    $guide_full_name = $action->guide_first_name . ' ' . $action->guide_last_name;
                                    $raw_tp = trim($action->guide_touchpoint ?? '');
                                    $guide_tp_display = !empty($raw_tp) ? $raw_tp : 'None';
                                    
                                    $ft_wa_phone = preg_replace('/[^0-9]/', '', $action->ft_phone);
                                    if (strpos($ft_wa_phone, '234') !== 0) {
                                        $ft_wa_phone = (strpos($ft_wa_phone, '0') === 0) ? '234' . substr($ft_wa_phone, 1) : '234' . $ft_wa_phone;
                                    }
                                    $ft_wa_body = "{$time_greeting} {$ft_full_name}, just following up...";
                                    
                                    $guide_wa_phone = preg_replace('/[^0-9]/', '', $action->guide_phone);
                                    if (strpos($guide_wa_phone, '234') !== 0) {
                                        $guide_wa_phone = (strpos($guide_wa_phone, '0') === 0) ? '234' . substr($guide_wa_phone, 1) : '234' . $guide_wa_phone;
                                    }
                                    $guide_wa_body = "Hello {$action->guide_first_name}, following up on the status of {$ft_full_name}.";
                                @endphp
                                <tr>
                                    <td class="align-middle"><strong>{{ $counter++ }}</strong></td>
                                    <td class="align-middle">
                                        <strong>{{ $ft_full_name }}</strong>
                                        @if(!empty($action->ft_phone))
                                            <a href="https://wa.me/{{ $ft_wa_phone }}?text={{ urlencode($ft_wa_body) }}" target="_blank" class="wa-ft-link">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        @endif
                                        <br><span class="attendant-type">{{ $action->attendant_type }}</span>
                                        <br><small class="text-muted">Campus ID: {{ $action->ft_campus_id }}</small>
                                    </td>
                                    <td class="align-middle">
                                        <span class="week-badge mb-1 d-inline-block">{{ $action->week_label }}</span><br>
                                        <span class="task-text">{{ $action->next_task }}</span>
                                    </td>
                                    <td class="align-middle">
                                        @if(!empty($action->latest_pending_log))
                                            <div class="log-container">
                                                <strong>Last Outcome:</strong>
                                                <span class="badge bg-info mb-1">{{ $action->latest_pending_log['outcome'] ?? 'No outcome' }}</span>
                                                <span class="log-comment">{{ $action->latest_pending_log['comment'] ?? 'No comment' }}</span>
                                                <span class="log-date"><i class="fa fa-calendar-alt"></i> {{ $action->latest_pending_log['timestamp'] ?? '' }}</span>
                                            </div>
                                        @else
                                            <span class="text-muted"><em>No attempts logged for this task yet</em></span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <a href="https://tiu.tcnikorodu.org/dash-board?tiu_member_id={{ $action->tiu_member_id }}&full_name={{ urlencode($guide_full_name) }}" target="_blank" class="text-primary font-weight-bold">
                                            {{ $guide_full_name }}
                                        </a><br>
                                        <small class="text-muted"><i class="fa fa-phone-alt"></i> {{ $action->guide_phone }}</small><br>
                                        <span class="badge {{ $guide_tp_display == 'None' ? 'bg-danger' : 'bg-outline-primary' }} mt-1">
                                            <i class="fa fa-map-marker-alt"></i> {{ $guide_tp_display }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-right">
                                        <a href="https://wa.me/{{ $guide_wa_phone }}?text={{ urlencode($guide_wa_body) }}" target="_blank" class="btn btn-success btn-sm">
                                            <i class="fab fa-whatsapp"></i> Chat Guide
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection