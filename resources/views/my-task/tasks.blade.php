@extends('layouts.app')

@section('content')
<style>
    /* Modern UI Enhancements */
    .task-timeline { border-left: 3px solid #e9ecef; padding-left: 20px; position: relative; margin-left: 10px; }
    .task-item { position: relative; margin-bottom: 2.5rem; }
    
    /* The dots on the timeline */
    .task-item::before { 
        content: ""; position: absolute; left: -28px; top: 5px; 
        width: 14px; height: 14px; border-radius: 50%; background: #dee2e6; border: 3px solid #fff; 
        z-index: 1;
    }
    .task-item.status-approved::before { background: #28a745; box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2); }
    .task-item.status-pending::before { background: #ffc107; box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2); }
    
    .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 12px; }
    .profile-header { background: #fff; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border-left: 5px solid #4e73df; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
    
    /* Soft Badges */
    .badge-soft-success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .badge-soft-warning { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
    .badge-soft-danger { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    .badge-soft-secondary { background-color: #e2e3e5; color: #41464b; border: 1px solid #d3d3d4; }
    
    .btn-whatsapp { background-color: #25d366; color: white; border: none; transition: all 0.3s; }
    .btn-whatsapp:hover { background-color: #128c7e; color: white; transform: translateY(-1px); }
    
    .log-entry { font-size: 0.85rem; padding: 8px 12px; border-radius: 8px; background: #f8f9fa; margin-bottom: 6px; border-left: 3px solid #cbd5e0; }
    .italic { font-style: italic; }
</style>

<div class="container py-4">
    {{-- Header Section --}}
    <div class="profile-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1" style="background: transparent; padding: 0;">
                    <li class="breadcrumb-item"><a href="{{ route('my-tasks.index') }}" class="text-decoration-none text-muted">My Tasks</a></li>
                    <li class="breadcrumb-item active text-primary">Task Management</li>
                </ol>
            </nav>
            <h2 class="mb-0 fw-bold text-dark">{{ $firstTimer->first_name ?? 'N/A' }} {{ $firstTimer->last_name ?? '' }}</h2>
            <div class="mt-1">
                <span class="badge bg-light text-dark border me-2"><i class="fa fa-phone me-1"></i> {{ $firstTimer->phone_number ?? 'No Phone' }}</span>
                <span class="badge bg-light text-dark border"><i class="fa fa-user-tag me-1"></i> {{ $firstTimer->attendant_type ?? 'First Timer' }}</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary shadow-sm" data-toggle="modal" data-target="#detailsModal">
                <i class="fa fa-info-circle"></i> Bio Details
            </button>
            <a href="https://wa.me/234{{ ltrim($firstTimer->phone_number ?? '', '0') }}" target="_blank" class="btn btn-whatsapp shadow-sm">
                <i class="fab fa-whatsapp"></i> WhatsApp Chat
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success')) 
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center">
            <i class="fa fa-check-circle me-2"></i> {{ session('success') }}
        </div> 
    @endif
    @if(session('error')) 
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center">
            <i class="fa fa-exclamation-triangle me-2"></i> {{ session('error') }}
        </div> 
    @endif

    {{-- Tasks List --}}
    <div class="row">
        <div class="col-lg-12">
            @forelse($tasks as $weekName => $weekTasks)
                <div class="mb-5">
                    <h5 class="text-uppercase tracking-wider text-muted mb-4 fw-bold px-2" style="font-size: 0.85rem; letter-spacing: 1px;">
                        <i class="fa fa-calendar-alt me-2 text-primary"></i> {{ ucfirst(str_replace('_', ' ', $weekName)) }}
                    </h5>
                    
                    <div class="task-timeline">
                        @foreach($weekTasks as $taskKey => $task)
                            @if($task['display'] ?? false)
                                @php
                                    $status = $task['status'] ?? 'Not Started';
                                    $statusClass = '';
                                    if($status == 'Approved') $statusClass = 'status-approved';
                                    elseif($status == 'Pending') $statusClass = 'status-pending';
                                @endphp

                                <div class="task-item {{ $statusClass }}">
                                    <div class="card shadow-sm mb-3">
                                        <div class="card-body p-4">
                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <div class="flex-grow-1">
                                                    <h6 class="fw-bold mb-2 text-dark" style="font-size: 1.1rem;">{!! $task['text'] !!}</h6>
                                                    
                                                    @if($status == 'Approved')
                                                        <span class="badge badge-soft-success px-3 py-2"><i class="fa fa-check-circle"></i> Approved</span>
                                                    @elseif($status == 'Skipped')
                                                        <span class="badge badge-soft-danger px-3 py-2"><i class="fa fa-forward"></i> Skipped</span>
                                                    @elseif($status == 'Pending')
                                                        <span class="badge badge-soft-warning px-3 py-2"><i class="fa fa-hourglass-half"></i> Pending Approval</span>
                                                    @else
                                                        <span class="badge badge-soft-secondary px-3 py-2">To Do</span>
                                                    @endif
                                                </div>

                                                <div class="d-flex align-items-center">
                                                    @if((Auth::user()->member_role == 'Admin' || Auth::user()->member_role == 'Super User') && $status != 'Approved')
                                                        <button type="button" class="btn btn-sm text-danger fw-bold" onclick="skipTask('{{ $tracking->tracking_id }}', '{{ $weekName }}', '{{ $taskKey }}')">
                                                            Skip Task
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>

                                            {{-- History / Previous Attempts --}}
                                            @if(isset($task['logs']) && count($task['logs']) > 0)
                                                <div class="mt-4">
                                                    <p class="text-uppercase fw-bold text-muted mb-2" style="font-size: 0.7rem;">Previous Attempts History</p>
                                                    @foreach($task['logs'] as $log)
                                                        <div class="log-entry">
                                                            <div class="d-flex justify-content-between">
                                                                <span class="fw-bold text-dark">{{ $log['outcome'] }}</span>
                                                                <span class="text-muted small">
                                                                    {{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->diffForHumans() : '' }}
                                                                </span>
                                                            </div>
                                                            @if(!empty($log['comment'])) 
                                                                <p class="mb-0 text-muted mt-1 small italic">"{{ $log['comment'] }}"</p> 
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- Final Result or Action Form --}}
                                            @if($status == 'Approved')
                                                <div class="mt-4 pt-3 border-top bg-light-50">
                                                    <div class="row g-3">
                                                        <div class="col-md-4">
                                                            <small class="d-block text-muted">Final Outcome</small>
                                                            <span class="fw-bold">{{ $task['outcome'] ?? 'N/A' }}</span>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <small class="d-block text-muted">Completed On</small>
                                                            <span class="fw-bold">{{ $task['completed_at'] ?? 'N/A' }}</span>
                                                        </div>
                                                        @if(!empty($task['comment']))
                                                            <div class="col-md-4">
                                                                <small class="d-block text-muted">Note</small>
                                                                <span class="italic text-dark">"{{ $task['comment'] }}"</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @elseif($status == 'Skipped')
                                                <div class="mt-3 p-2 rounded bg-light border-start border-danger border-4 small">
                                                    This task was skipped by an Administrator.
                                                </div>
                                            @else
                                                {{-- Submission Form --}}
                                                <div class="mt-4 pt-4 border-top">
                                                    <form action="{{ route('my-tasks.update-task') }}" method="POST">
                                                        @csrf
                                                        <input type="hidden" name="tracking_id" value="{{ $tracking->tracking_id }}">
                                                        <input type="hidden" name="week" value="{{ $weekName }}">
                                                        <input type="hidden" name="task_key" value="{{ $taskKey }}">
                                                        <input type="hidden" name="action" value="log_outcome">

                                                        <div class="row g-3">
                                                            <div class="col-md-5">
                                                                <label class="form-label fw-bold text-muted small uppercase">Outcome *</label>
                                                                <select name="outcome" class="form-select border-primary shadow-sm" required>
                                                                    <option value="">Choose outcome...</option>
                                                                    <option value="Successful - Positive">✅ Successful - Positive (Approve)</option>
                                                                    <option value="Successful - Neutral">😐 Successful - Neutral (Pending)</option>
                                                                    <option value="Successful - Negative">❌ Successful - Negative (Pending)</option>
                                                                    <option value="Unable to Contact - No Answer">📞 No Answer</option>
                                                                    <option value="Unable to Contact - Invalid Number">❓ Invalid Number</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <label class="form-label fw-bold text-muted small uppercase">Internal Comment</label>
                                                                <textarea name="comment" class="form-control" rows="1" placeholder="Add optional details..."></textarea>
                                                            </div>
                                                            <div class="col-md-2 d-flex align-items-end">
                                                                <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm py-2">Submit</button>
                                                            </div>
                                                        </div>
                                                        <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                                            <i class="fa fa-info-circle text-primary"></i> 
                                                            Note: Tasks have a 48-hour cooldown after approval before the next one unlocks.
                                                        </div>
                                                    </form>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="fa fa-tasks fa-3x text-light mb-3"></i>
                        <h5 class="text-muted">No tasks found for this record.</h5>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- BIO DETAILS MODAL -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-0">
                <h5 class="modal-title fw-bold">First Timer Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <table class="table mb-0">
                    <tbody class="small">
                        <tr><td class="text-muted ps-4 py-3">Full Name</td><td class="fw-bold pe-4 py-3 text-end">{{ ($firstTimer->first_name ?? '') . ' ' . ($firstTimer->last_name ?? '') }}</td></tr>
                        <tr><td class="text-muted ps-4 py-3">Gender</td><td class="fw-bold pe-4 py-3 text-end">{{ $firstTimer->gender ?? 'N/A' }}</td></tr>
                        <tr><td class="text-muted ps-4 py-3">Occupation</td><td class="fw-bold pe-4 py-3 text-end">{{ $firstTimer->occupation ?? 'N/A' }}</td></tr>
                        <tr><td class="text-muted ps-4 py-3">Address</td><td class="fw-bold pe-4 py-3 text-end text-wrap" style="max-width: 200px;">{{ $firstTimer->address ?? 'N/A' }}</td></tr>
                        <tr>
                            <td class="text-muted ps-4 py-3 border-0">Registered On</td>
                            <td class="fw-bold pe-4 py-3 text-end text-primary border-0">
                                {{ isset($firstTimer->created_at) ? \Carbon\Carbon::parse($firstTimer->created_at)->format('M d, Y') : 'N/A' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light-50">
                <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function skipTask(trackingId, week, taskKey) {
    if (confirm('Are you sure you want to skip this task? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("my-tasks.update-task") }}';
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = '{{ csrf_token() }}';
        form.appendChild(csrf);
        
        const fields = [
            {name: 'tracking_id', value: trackingId},
            {name: 'week', value: week},
            {name: 'task_key', value: taskKey},
            {name: 'action', value: 'skip_task'},
            {name: 'outcome', value: 'Skipped'}
        ];
        
        fields.forEach(field => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = field.name;
            input.value = field.value;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
@endsection