{{-- resources/views/my-task/partials/modals.blade.php --}}

{{-- BIO DETAILS MODAL --}}
<div class="modal fade" id="detailsModal{{ $ft_id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Bio Details: {{ $ft->first_name }} {{ $ft->last_name }}</h5>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <div class="detail-label">Attendant Type</div>
                <p>{{ $ft->attendant_type ?? 'N/A' }}</p>
                <div class="detail-label">Phone</div>
                <p>{{ $ft->phone_number ?? 'N/A' }}</p>
                <div class="detail-label">Gender</div>
                <p>{{ $ft->gender ?? 'N/A' }}</p>
                <div class="detail-label">Occupation</div>
                <p>{{ $ft->occupation ?? 'N/A' }}</p>
            </div>
        </div>
    </div>
</div>

{{-- DEPARTMENT MODAL --}}
<div class="modal fade" id="deptModal{{ $ft_id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('my-task.update') }}" method="post" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5>Update Department</h5>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <select name="department" class="form-control" required>
                    @foreach($department_list as $d)
                        <option value="{{ $d }}" {{ ($up->department ?? '') == $d ? 'selected' : '' }}>
                            {{ $d }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="first_timer_id" value="{{ $ft_id }}">
                <input type="hidden" name="update_type" value="department">
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_first_timer_details" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- CLUSTER MODAL --}}
<div class="modal fade" id="clusterModal{{ $ft_id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('my-task.update') }}" method="post" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5>Update Cluster</h5>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <select name="cluster" class="form-control" required>
                    @foreach($cluster_list as $cl)
                        <option value="{{ $cl }}" {{ ($up->cluster ?? '') == $cl ? 'selected' : '' }}>
                            {{ $cl }}
                        </option>
                    @endforeach
                </select>
                <input type="hidden" name="first_timer_id" value="{{ $ft_id }}">
                <input type="hidden" name="update_type" value="cluster">
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_first_timer_details" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- FOF MODAL --}}
<div class="modal fade" id="fofModal{{ $ft_id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('my-task.update') }}" method="post" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5>FOF Pre Registration</h5>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <select name="foundation_of_faith" class="form-control">
                    <option value="No" {{ ($up->foundation_of_faith ?? '') == 'No' ? 'selected' : '' }}>No</option>
                    <option value="Yes" {{ ($up->foundation_of_faith ?? '') == 'Yes' ? 'selected' : '' }}>Yes</option>
                </select>
                <input type="hidden" name="first_timer_id" value="{{ $ft_id }}">
                <input type="hidden" name="update_type" value="foundation_of_faith">
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_first_timer_details" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

{{-- ADDRESS MODAL --}}
<div class="modal fade" id="addressModal{{ $ft_id }}" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('my-task.update') }}" method="post" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5>Update Address</h5>
                <button type="button" class="close" data-dismiss="modal">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" rows="3">{{ $cleanAddress }}</textarea>
                </div>
                <div class="form-group">
                    <label>Community</label>
                    <select name="community_id" class="form-control">
                        <option value="">Select Community...</option>
                        @foreach($communities as $community)
                            <option value="{{ $community->id }}" {{ ($ft->community_id ?? '') == $community->id ? 'selected' : '' }}>
                                {{ $community->community_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="first_timer_id" value="{{ $ft_id }}">
                <input type="hidden" name="update_type" value="address">
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_first_timer_details" class="btn btn-primary">Save Address</button>
            </div>
        </form>
    </div>
</div>

{{-- WEEK TASK MODALS --}}
@php 
    $f_json = json_decode($tk->followup_response_new ?? '[]', true);
@endphp
@if(!empty($f_json))
    @foreach($f_json as $week => $tasks)
        @php $w_num = array_search($week, array_keys($f_json)) + 1; @endphp
        <div class="modal fade" id="modal-{{ $tk->tracking_id }}-{{ $w_num }}" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6>{{ $week }}: Tasks for {{ $ft->first_name }}</h6>
                        <button type="button" class="close" data-dismiss="modal">×</button>
                    </div>
                    <div class="modal-body">
                        <ul class="ms-list">
                            @foreach($tasks as $t_key => $det)
                                @if($det['display'] ?? false)
                                    @php 
                                        $st = $det['status'] ?? 'Pending'; 
                                        $clr = ($st == 'Approved') ? 'green' : (($st == 'Skipped') ? 'orange' : 'grey');
                                    @endphp
                                    <li class="ms-list-item media">
                                        <div class="media-body">
                                            <h5>{{ $det['text'] }}</h5>
                                            <span class="badge" style="background-color: {{ $clr }}; color: white;">
                                                {{ htmlspecialchars($st) }}
                                            </span>
                                            @if($st == 'Pending')
                                                <div class="float-right">
                                                    <button class="btn btn-sm btn-info open-outcome-modal" 
                                                        data-track="{{ $tk->tracking_id }}" 
                                                        data-wk="{{ $week }}" 
                                                        data-tk="{{ $t_key }}" 
                                                        data-txt="{{ htmlspecialchars($det['text']) }}">
                                                        <i class="fa fa-pen"></i> Update Task
                                                    </button>
                                                    @if($is_admin)
                                                        <button class="btn btn-sm btn-warning ml-2 skip-task-btn" 
                                                            data-tracking-id="{{ $tk->tracking_id }}" 
                                                            data-week="{{ htmlspecialchars($week) }}" 
                                                            data-task-key="{{ htmlspecialchars($t_key) }}">
                                                            <i class="fa fa-forward"></i> Skip Task
                                                        </button>
                                                    @endif
                                                </div>
                                            @endif
                                            <div class="mt-3">
                                                @if(!empty($det['logs']))
                                                    @foreach(array_reverse($det['logs']) as $l)
                                                        <div class="task-log-entry">
                                                            <small><strong>{{ $l['outcome'] }}</strong> logged on {{ date("M d, Y h:i A", strtotime($l['timestamp'])) }}</small>
                                                            @if(!empty($l['comment']))
                                                                <p class="log-comment"><em>"{{ htmlspecialchars($l['comment']) }}"</em></p>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endif