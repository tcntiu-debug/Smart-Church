@extends('layouts.app')

@section('content')
<style>
    .summary-card {
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: transform 0.2s;
    }
    .summary-card:hover { transform: translateY(-3px); }
    .summary-card .count { font-size: 32px; font-weight: 700; }
    .summary-card .label { font-size: 13px; color: #666; margin-top: 4px; }
    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    .member-row:hover { background-color: #f8f9fa; }
    .present-badge { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
    .absent-badge { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
    .tab-btn.active { background: #667eea; color: white; border-color: #667eea; }
    .tab-btn { border-radius: 8px; padding: 8px 16px; font-size: 13px; cursor: pointer; }
    @media (max-width: 767px) {
        #member-table thead { display: none; }
        #member-table, #member-table tbody, #member-table tr, #member-table td {
            display: block; width: 100%;
        }
        #member-table tr {
            margin-bottom: 1rem; border: 1px solid #ddd;
            border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        #member-table td {
            text-align: right; padding-left: 50%;
            position: relative; border-bottom: 1px solid #eee;
            padding-top: 12px; padding-bottom: 12px;
        }
        #member-table td:last-child { border-bottom: 0; }
        #member-table td::before {
            content: attr(data-label); position: absolute;
            left: 15px; width: 45%; padding-right: 10px;
            white-space: nowrap; text-align: left;
            font-weight: bold; color: #333;
        }
    }
</style>

<div class="row">
    <div class="col-md-12">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
                <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Attendance Analysis</li>
            </ol>
        </nav>

        <div class="ms-panel">
            <div class="ms-panel-header">
                <h5><i class="fas fa-chart-bar mr-2"></i> Attendance Analysis</h5>
                <p class="text-muted mb-0 small">Select a date and group type below to see who was present and who was absent.</p>
            </div>
            <div class="ms-panel-body">
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="{{ route('attendance.analysis') }}" class="row align-items-end">
                        <div class="col-md-3 mb-3">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="date" class="form-control" value="{{ $selectedDate }}" max="{{ date('Y-m-d') }}">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label class="form-label fw-bold">View By</label>
                            <select name="view" class="form-control" id="view-select">
                                <option value="department" {{ $viewAs == 'department' ? 'selected' : '' }}>Department</option>
                                <option value="house_fellowship" {{ $viewAs == 'house_fellowship' ? 'selected' : '' }}>House Fellowship</option>
                                <option value="cluster" {{ $viewAs == 'cluster' ? 'selected' : '' }}>Cluster</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label fw-bold">Group</label>
                            <select name="group_id" class="form-control" id="group-select">
                                <option value="">-- Select a group --</option>
                                @if($viewAs == 'department')
                                    @foreach($availableDepartments as $dept)
                                        <option value="{{ $dept->dept_id }}" {{ $groupId == $dept->dept_id ? 'selected' : '' }}>
                                            {{ $dept->dept_name }}
                                        </option>
                                    @endforeach
                                @elseif($viewAs == 'house_fellowship')
                                    @foreach($availableHFs as $hf)
                                        <option value="{{ $hf->dept_id }}" {{ $groupId == $hf->dept_id ? 'selected' : '' }}>
                                            {{ $hf->dept_name }}
                                        </option>
                                    @endforeach
                                @elseif($viewAs == 'cluster')
                                    @foreach($availableClusters as $cl)
                                        <option value="{{ $cl->dept_id }}" {{ $groupId == $cl->dept_id ? 'selected' : '' }}>
                                            {{ $cl->dept_name }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search mr-1"></i> Analyze
                            </button>
                            <a href="{{ route('attendance.analysis') }}" class="btn btn-outline-secondary w-100 mt-2">
                                <i class="fas fa-sync-alt mr-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                @if($groupId)
                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="summary-card bg-white border">
                            <div class="count text-primary">{{ $totalMembers }}</div>
                            <div class="label">Total Members in <strong>{{ $groupName }}</strong></div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="summary-card" style="background: #d4edda;">
                            <div class="count text-success">{{ $totalPresent }}</div>
                            <div class="label">Present on {{ date('j M Y', strtotime($selectedDate)) }}</div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="summary-card" style="background: #f8d7da;">
                            <div class="count text-danger">{{ $totalAbsent }}</div>
                            <div class="label">Absent on {{ date('j M Y', strtotime($selectedDate)) }}</div>
                        </div>
                    </div>
                </div>

                <!-- Toggle: Present / Absent -->
                <div class="mb-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn tab-btn active" id="show-absent-btn" onclick="toggleView('absent')">
                            <i class="fas fa-times-circle text-danger mr-1"></i> Absent ({{ $totalAbsent }})
                        </button>
                        <button type="button" class="btn tab-btn" id="show-present-btn" onclick="toggleView('present')">
                            <i class="fas fa-check-circle text-success mr-1"></i> Present ({{ $totalPresent }})
                        </button>
                    </div>
                </div>

                <!-- Absent Members Table -->
                <div id="absent-section">
                    <div class="ms-panel">
                        <div class="ms-panel-header">
                            <h6><i class="fas fa-user-times text-danger mr-2"></i>Absent Members — <strong>{{ $groupName }}</strong></h6>
                        </div>
                        <div class="ms-panel-body">
                            <div class="table-responsive">
                                <table id="absent-table" class="table table-hover thead-primary w-100">
                                    <thead>
                                        <tr>
                                            <th>S/N</th>
                                            <th>Full Name</th>
                                            <th>Phone</th>
                                            <th>Gender</th>
                                            <th>Departments</th>
                                            <th>House Fellowship</th>
                                            <th>Cluster</th>
                                            <th>Church Type</th>
                                            <th>Community</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($absentMembers as $index => $member)
                                        <tr class="member-row">
                                            <td data-label="S/N">{{ $index + 1 }}</td>
                                            <td data-label="Full Name">
                                                <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>
                                            </td>
                                            <td data-label="Phone">
                                                <a href="https://wa.me/{{ $member->whatsapp_phone }}" target="_blank" class="text-success mr-2">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                                {{ $member->phone_number }}
                                            </td>
                                            <td data-label="Gender">{{ $member->gender ?? 'N/A' }}</td>
                                            <td data-label="Departments">{{ $member->department_names }}</td>
                                            <td data-label="House Fellowship">{{ $member->hf_names }}</td>
                                            <td data-label="Cluster">{{ $member->cluster_names }}</td>
                                            <td data-label="Church Type">{{ $member->church_type_name }}</td>
                                            <td data-label="Community">{{ $member->community_name }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                <p class="mb-0">Everyone in this group was present on {{ date('j M Y', strtotime($selectedDate)) }}!</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Present Members Table -->
                <div id="present-section" style="display: none;">
                    <div class="ms-panel">
                        <div class="ms-panel-header">
                            <h6><i class="fas fa-user-check text-success mr-2"></i>Present Members — <strong>{{ $groupName }}</strong></h6>
                        </div>
                        <div class="ms-panel-body">
                            <div class="table-responsive">
                                <table id="present-table" class="table table-hover thead-primary w-100">
                                    <thead>
                                        <tr>
                                            <th>S/N</th>
                                            <th>Full Name</th>
                                            <th>Phone</th>
                                            <th>Gender</th>
                                            <th>Departments</th>
                                            <th>House Fellowship</th>
                                            <th>Cluster</th>
                                            <th>Church Type</th>
                                            <th>Community</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($presentMembersList as $index => $member)
                                        <tr class="member-row">
                                            <td data-label="S/N">{{ $index + 1 }}</td>
                                            <td data-label="Full Name">
                                                <strong>{{ $member->first_name }} {{ $member->last_name }}</strong>
                                            </td>
                                            <td data-label="Phone">
                                                <a href="https://wa.me/{{ $member->whatsapp_phone }}" target="_blank" class="text-success mr-2">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                                {{ $member->phone_number }}
                                            </td>
                                            <td data-label="Gender">{{ $member->gender ?? 'N/A' }}</td>
                                            <td data-label="Departments">{{ $member->department_names }}</td>
                                            <td data-label="House Fellowship">{{ $member->hf_names }}</td>
                                            <td data-label="Cluster">{{ $member->cluster_names }}</td>
                                            <td data-label="Church Type">{{ $member->church_type_name }}</td>
                                            <td data-label="Community">{{ $member->community_name }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="9" class="text-center py-4 text-muted">
                                                <i class="fas fa-exclamation-circle fa-2x text-warning mb-2"></i>
                                                <p class="mb-0">No one in this group was marked present on {{ date('j M Y', strtotime($selectedDate)) }}.</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                @else
                <!-- No selection yet -->
                <div class="text-center py-5">
                    <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Select a date and group to view attendance analysis</h5>
                    <p class="text-muted">Choose a Department, House Fellowship, or Cluster from the filters above.</p>
                </div>
                @endif

                <!-- ================================================================ -->
                <!-- CHILDREN ATTENDANCE ANALYSIS                                     -->
                <!-- ================================================================ -->
                <div class="ms-panel mt-4">
                    <div class="ms-panel-header" style="background: #e8f4f8;">
                        <h5><i class="fas fa-child mr-2" style="color: #17a2b8;"></i> Children Attendance Analysis</h5>
                        <p class="text-muted mb-0 small">Children who were checked in or missing on <strong>{{ date('j M Y', strtotime($selectedDate)) }}</strong>.</p>
                    </div>
                    <div class="ms-panel-body">
                        <!-- Children Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4 mb-3">
                                <div class="summary-card bg-white border">
                                    <div class="count text-info">{{ $totalChildren }}</div>
                                    <div class="label">Total Children Registered</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="summary-card" style="background: #d4edda;">
                                    <div class="count text-success">{{ $totalChildrenPresent }}</div>
                                    <div class="label">Checked In on {{ date('j M Y', strtotime($selectedDate)) }}</div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="summary-card" style="background: #fff3cd;">
                                    <div class="count text-warning">{{ $totalChildrenAbsent }}</div>
                                    <div class="label">Not Checked In on {{ date('j M Y', strtotime($selectedDate)) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Children Toggle: Present / Absent -->
                        <div class="mb-3">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn tab-btn active" id="show-child-absent-btn" onclick="toggleChildrenView('absent')">
                                    <i class="fas fa-times-circle text-warning mr-1"></i> Not Checked In ({{ $totalChildrenAbsent }})
                                </button>
                                <button type="button" class="btn tab-btn" id="show-child-present-btn" onclick="toggleChildrenView('present')">
                                    <i class="fas fa-check-circle text-success mr-1"></i> Checked In ({{ $totalChildrenPresent }})
                                </button>
                            </div>
                        </div>

                        <!-- Absent Children Table -->
                        <div id="child-absent-section">
                            <div class="ms-panel">
                                <div class="ms-panel-header" style="background: #fff3cd;">
                                    <h6><i class="fas fa-child text-warning mr-2"></i>Children Not Checked In — <strong>{{ date('j M Y', strtotime($selectedDate)) }}</strong></h6>
                                </div>
                                <div class="ms-panel-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover thead-primary w-100">
                                            <thead>
                                                <tr>
                                                    <th>S/N</th>
                                                    <th>Child Name</th>
                                                    <th>Parent/Guardian</th>
                                                    <th>Parent Phone</th>
                                                    <th>Date of Birth</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($absentChildren as $index => $child)
                                                <tr class="member-row">
                                                    <td data-label="S/N">{{ $index + 1 }}</td>
                                                    <td data-label="Child Name"><strong>{{ $child->child_name }}</strong></td>
                                                    <td data-label="Parent/Guardian">{{ $child->parent_name ?? 'N/A' }}</td>
                                                    <td data-label="Parent Phone">
                                                        @if($child->parent_phone)
                                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', ltrim($child->parent_phone, '0')) }}" target="_blank" class="text-success mr-1">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                        @endif
                                                        {{ $child->parent_phone ?? 'N/A' }}
                                                    </td>
                                                    <td data-label="Date of Birth">{{ $child->dob ?? 'N/A' }}</td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                                        <p class="mb-0">All children were checked in on {{ date('j M Y', strtotime($selectedDate)) }}!</p>
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Present Children Table -->
                        <div id="child-present-section" style="display: none;">
                            <div class="ms-panel">
                                <div class="ms-panel-header" style="background: #d4edda;">
                                    <h6><i class="fas fa-child text-success mr-2"></i>Children Checked In — <strong>{{ date('j M Y', strtotime($selectedDate)) }}</strong></h6>
                                </div>
                                <div class="ms-panel-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover thead-primary w-100">
                                            <thead>
                                                <tr>
                                                    <th>S/N</th>
                                                    <th>Child Name</th>
                                                    <th>Parent/Guardian</th>
                                                    <th>Parent Phone</th>
                                                    <th>Date of Birth</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($presentChildren as $index => $child)
                                                <tr class="member-row">
                                                    <td data-label="S/N">{{ $index + 1 }}</td>
                                                    <td data-label="Child Name"><strong>{{ $child->child_name }}</strong></td>
                                                    <td data-label="Parent/Guardian">{{ $child->parent_name ?? 'N/A' }}</td>
                                                    <td data-label="Parent Phone">
                                                        @if($child->parent_phone)
                                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', ltrim($child->parent_phone, '0')) }}" target="_blank" class="text-success mr-1">
                                                            <i class="fab fa-whatsapp"></i>
                                                        </a>
                                                        @endif
                                                        {{ $child->parent_phone ?? 'N/A' }}
                                                    </td>
                                                    <td data-label="Date of Birth">{{ $child->dob ?? 'N/A' }}</td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        <i class="fas fa-exclamation-circle fa-2x text-warning mb-2"></i>
                                                        <p class="mb-0">No children were checked in on {{ date('j M Y', strtotime($selectedDate)) }}.</p>
                                                    </td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleView(view) {
    if (view === 'absent') {
        document.getElementById('absent-section').style.display = 'block';
        document.getElementById('present-section').style.display = 'none';
        document.getElementById('show-absent-btn').classList.add('active');
        document.getElementById('show-present-btn').classList.remove('active');
    } else {
        document.getElementById('absent-section').style.display = 'none';
        document.getElementById('present-section').style.display = 'block';
        document.getElementById('show-present-btn').classList.add('active');
        document.getElementById('show-absent-btn').classList.remove('active');
    }
}

function toggleChildrenView(view) {
    if (view === 'absent') {
        document.getElementById('child-absent-section').style.display = 'block';
        document.getElementById('child-present-section').style.display = 'none';
        document.getElementById('show-child-absent-btn').classList.add('active');
        document.getElementById('show-child-present-btn').classList.remove('active');
    } else {
        document.getElementById('child-absent-section').style.display = 'none';
        document.getElementById('child-present-section').style.display = 'block';
        document.getElementById('show-child-present-btn').classList.add('active');
        document.getElementById('show-child-absent-btn').classList.remove('active');
    }
}

// Auto-switch group dropdown when view type changes
document.getElementById('view-select').addEventListener('change', function() {
    const view = this.value;
    const groupSelect = document.getElementById('group-select');

    // Clear current options
    groupSelect.innerHTML = '<option value="">-- Select a group --</option>';

    // Get the available groups for the selected view
    @if($viewAs == 'department' || !$groupId)
        const departments = @json($allDepartments->toArray());
        const hfs = @json($allHouseFellowships->toArray());
        const clusters = @json($allClusters->toArray());
    @else
        const departments = @json($availableDepartments->toArray());
        const hfs = @json($availableHFs->toArray());
        const clusters = @json($availableClusters->toArray());
    @endif

    let options = [];
    if (view === 'department') {
        options = departments;
    } else if (view === 'house_fellowship') {
        options = hfs;
    } else if (view === 'cluster') {
        options = clusters;
    }

    options.forEach(function(item) {
        const opt = document.createElement('option');
        opt.value = item.dept_id;
        opt.textContent = item.dept_name;
        groupSelect.appendChild(opt);
    });
});
</script>
@endpush
