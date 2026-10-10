@extends('layouts.app')

@section('content')
<style>
    /* Modern UI Enhancements */
    .task-timeline { border-left: 3px solid #e9ecef; padding-left: 12px; position: relative; margin-left: 4px; }
    .task-item { position: relative; margin-bottom: 1.5rem; }
    
    /* The dots on the timeline */
    .task-item::before { 
        content: ""; position: absolute; left: -20px; top: 5px; 
        width: 14px; height: 14px; border-radius: 50%; background: #dee2e6; border: 3px solid #fff; 
        z-index: 1;
    }
    .task-item.status-approved::before { background: #28a745; box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2); }
    .task-item.status-pending::before { background: #ffc107; box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2); }
    
    .card { border: none; box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); border-radius: 12px; }
    .profile-header { background: #fff; padding: 1rem 1.15rem; border-radius: 12px; margin-bottom: 1.1rem; border-left: 5px solid #4e73df; box-shadow: 0 2px 4px rgba(0,0,0,0.04); }
    /* style.css ships `h1..h6{...}` (h2 is 48px), which made the first-timer
       name dominate the card, so it gets its own (class) size. */
    .profile-header .first-timer-name { font-size: 1.4rem; font-weight: 700; line-height: 1.25; margin-bottom: 0.35rem; word-break: break-word; }
    .profile-header .breadcrumb { font-size: 0.82rem; background: transparent; padding: 0; margin-bottom: 0.3rem; }
    .profile-header .first-timer-subtitle { font-size: 0.82rem; }
    /* Small meta pills for phone / attendant type / joined date.
       Deliberately NOT `bg-light`: style.css redefines
       `.bg-light{background-color:#878793}`, which painted these chips dark
       grey and swallowed their text. */
    .meta-chip {
        display: inline-flex; align-items: center;
        padding: 0.2rem 0.65rem; margin: 0 0.35rem 0.35rem 0;
        border-radius: 999px; border: 1px solid #dfe4f3;
        background: #f2f4fb; color: #333c5c;
        font-size: 0.78rem; font-weight: 600; line-height: 1.5; white-space: nowrap;
    }
    .meta-chip i { color: #4e73df; margin-right: 0.35rem; }
    .profile-header-actions .btn { padding: 0.4rem 0.9rem; font-size: 0.85rem; line-height: 1.3; border-radius: 8px; }
    /* Same reason as `.meta-chip`: style.css paints `bg-light` #878793 (dark
       grey), so soft surfaces get their own token instead. */
    .soft-surface { background: #f2f4fb; }
    
    /* Soft Badges */
    .badge-soft-success { background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; }
    .badge-soft-warning { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
    .badge-soft-danger { background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7; }
    .badge-soft-secondary { background-color: #e2e3e5; color: #41464b; border: 1px solid #d3d3d4; }
    .badge-soft-info { background-color: #cff4fc; color: #055160; border: 1px solid #b6effb; }
    
    .btn-whatsapp { background-color: #25d366; color: white; border: none; transition: all 0.3s; }
    .btn-whatsapp:hover { background-color: #128c7e; color: white; transform: translateY(-1px); }
    
    .log-entry { font-size: 0.85rem; padding: 8px 12px; border-radius: 8px; background: #f8f9fa; margin-bottom: 6px; border-left: 3px solid #cbd5e0; }
    .italic { font-style: italic; }

    /* ---- Bootstrap 4.1.3 shims ------------------------------------------
       This markup uses a few utilities that only exist in later Bootstrap
       releases (and are therefore not in bootstrap.min.css), which is why the
       page used to render unstyled headings and labels. */
    .fw-bold { font-weight: 700 !important; }
    .text-decoration-none { text-decoration: none !important; }
    .form-label { display: block; margin-bottom: 0.25rem; }
    .form-label.uppercase { text-transform: uppercase; }

    /* ---- Follow-up progress summary ---- */
    .summary-strip { border-left: 5px solid #4e73df; }
    .summary-progress { height: 10px; border-radius: 6px; }
    .summary-progress .progress-bar { background-color: #4e73df; transition: width 0.4s ease; }
    .summary-progress .progress-bar.is-complete { background-color: #28a745; }
    .week-toolbar-btn { padding: 0.25rem 0.7rem; border-radius: 8px; border: 1px solid #d7dcec; background: #fff; color: #4e73df; font-weight: 700; font-size: 0.75rem; }
    .week-toolbar-btn:hover { background: #eef1fb; }

    /* ---- Collapsible week panels ---- */
    .week-panel { margin-bottom: 1rem; overflow: hidden; }
    .week-panel.is-active { border-left: 4px solid #4e73df; }
    .week-panel.is-complete { border-left: 4px solid #28a745; }
    .week-panel-header { background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
    .week-toggle { display: block; width: 100%; padding: 1rem 1.25rem; border: 0; background: transparent; text-align: left; color: inherit; cursor: pointer; }
    .week-toggle:hover { background: rgba(78, 115, 223, 0.06); }
    .week-toggle:focus { outline: none; box-shadow: inset 0 0 0 2px rgba(78, 115, 223, 0.25); }
    .week-index { flex: 0 0 auto; display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; margin-right: 0.75rem; border-radius: 50%; background: rgba(78, 115, 223, 0.12); color: #4e73df; font-weight: 700; font-size: 0.85rem; }
    .week-panel.is-complete .week-index { background: rgba(40, 167, 69, 0.12); color: #28a745; }
    .week-title { font-weight: 700; font-size: 0.95rem; letter-spacing: 0.3px; }
    .week-chevron { color: #6c757d; transition: transform 0.25s ease; }
    .week-toggle[aria-expanded="true"] .week-chevron { transform: rotate(180deg); }
    .week-progress { display: inline-block; width: 72px; height: 6px; border-radius: 3px; background: #e9ecef; overflow: hidden; vertical-align: middle; }
    .week-progress-bar { display: block; height: 100%; background: #4e73df; }
    .week-panel.is-complete .week-progress-bar { background: #28a745; }

    /* ---- Task cards ----
       The old 4px status-coloured left rail (`border-left`) was removed: the
       timeline dot already carries the status colour, so the rail just read as
       a second panel edge next to the dot. */
    .btn-skip { padding: 0.2rem 0.6rem; border-radius: 8px; border: 1px solid rgba(220, 53, 69, 0.3); background: rgba(220, 53, 69, 0.08); color: #dc3545; font-weight: 700; font-size: 0.78rem; }
    .btn-skip:hover { background: rgba(220, 53, 69, 0.18); color: #bd2130; }
    .skipped-note { padding: 0.65rem 0.85rem; border-radius: 8px; border-left: 4px solid #dc3545; background: #fdf2f2; color: #842029; font-size: 0.85rem; }

    /* ---- Dark mode twins (see docs/DISPLAY-MODE.md) ----------------------
       `.ms-dark-theme .card` repaints the task cards while style.css forces
       every heading/paragraph/span to #fff, so this page's own light rules
       (white profile header, pale log rows, `.text-dark` headings, soft
       badges, `.meta-chip` pills) need a dark counterpart to stay visible.
       Palette: surface #252851, deeper #323a67, border #242750, muted #b9bcd8. */
    .ms-dark-theme .task-timeline {
        border-left-color: #242750;
    }
    /* The dot's ring masks the timeline line, so it follows the page surface. */
    .ms-dark-theme .task-item::before {
        border-color: #323a67;
    }
    .ms-dark-theme .profile-header {
        background: #252851;
        box-shadow: none;
    }
    /* (0,2,0) beats style.css' `.ms-dark-theme span{color:#fff}` on specificity. */
    .ms-dark-theme .meta-chip {
        background: #323a67;
        border-color: #242750;
        color: #dfe3ff;
    }
    .ms-dark-theme .meta-chip i {
        color: #9db2ff;
    }
    .ms-dark-theme .log-entry {
        background-color: #323a67;
        border-left-color: #242750;
    }
    .ms-dark-theme .soft-surface {
        background-color: #323a67;
    }
    .ms-dark-theme span.badge-soft-success { background-color: #1f3b2a; color: #b7f0c5; border-color: #2c5b40; }
    .ms-dark-theme span.badge-soft-warning { background-color: #3a2f10; color: #ffd97a; border-color: #6b5716; }
    .ms-dark-theme span.badge-soft-danger { background-color: #3d2226; color: #ffc9cf; border-color: #6e3238; }
    .ms-dark-theme span.badge-soft-secondary { background-color: #323a67; color: #cbd0e8; border-color: #242750; }
    /* Bootstrap utilities carry `!important`, so their twins need it too. */
    .ms-dark-theme .text-dark {
        color: #ffffff !important;
    }
    .ms-dark-theme .text-muted {
        color: #b9bcd8 !important;
    }
    .ms-dark-theme .border-top {
        border-top-color: #242750 !important;
    }
    /* Week panels / summary strip / task rails. `span.week-index` and
       `span.badge-soft-*` need the element in the selector to outrank
       style.css' `color:#fff` rule for every `.ms-dark-theme span`. */
    .ms-dark-theme .week-panel-header {
        background: #323a67;
        border-bottom-color: #242750;
    }
    .ms-dark-theme .week-toggle:hover {
        background: rgba(255, 255, 255, 0.05);
    }
    .ms-dark-theme span.week-index {
        background: rgba(157, 178, 255, 0.15);
        color: #9db2ff;
    }
    .ms-dark-theme .week-chevron {
        color: #b9bcd8;
    }
    .ms-dark-theme .week-progress {
        background: #242750;
    }
    .ms-dark-theme .week-toolbar-btn {
        background: #323a67;
        border-color: #242750;
        color: #9db2ff;
    }
    .ms-dark-theme .week-toolbar-btn:hover {
        background: #3a4176;
    }
    .ms-dark-theme .btn-skip {
        background: rgba(220, 53, 69, 0.18);
        border-color: rgba(220, 53, 69, 0.45);
        color: #ff9aa5;
    }
    .ms-dark-theme .btn-skip:hover {
        background: rgba(220, 53, 69, 0.28);
        color: #ffc9cf;
    }
    .ms-dark-theme .skipped-note {
        background: #3d2226;
        color: #ffc9cf;
    }
    .ms-dark-theme span.badge-soft-info { background-color: #0e3b46; color: #a5e9f5; border-color: #1a5f70; }

    /* ---- Phone tuning ----------------------------------------------------
       Same phone-first pass as the list page (index.blade.php) so the two
       headers of the My Tasks pair stay identical on a ~360px screen. Only
       the shared header pieces need it: everything else on this page is
       already sized in rem (0.7rem - 0.95rem). Desktop is untouched. */
    @media (max-width: 767.98px) {
        .profile-header { padding: 0.85rem 0.95rem; border-left-width: 4px; margin-bottom: 0.9rem; }
        .profile-header .first-timer-name { font-size: 1.15rem; }
        .profile-header .breadcrumb,
        .profile-header .first-timer-subtitle { font-size: 0.75rem; }
        .meta-chip { font-size: 0.72rem; }
        .profile-header-actions .btn { padding: 0.35rem 0.75rem; font-size: 0.78rem; }
    }
</style>

<div class="container py-4">
    {{-- Header Section --}}
    @php
        /* Breadcrumb / empty state target: an admin goes back to the same
           guide's assignee list, a guide back to their own tasks. */
        $backUrl = $is_viewing_other
            ? route('my-tasks.index', array_filter(['guide_id' => $guide_id, 'guide_name' => $viewing_guide_name]))
            : route('my-tasks.index');
        $backLabel = $is_viewing_other ? ($viewing_guide_name ?: 'Guide') . "'s Assignees" : 'My Tasks';
    @endphp

    {{-- Header Section --}}
    <div class="profile-header">
        <div class="d-flex flex-wrap align-items-start justify-content-between">
            <div class="pr-md-4 mb-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ $backUrl }}" class="text-decoration-none text-muted">{{ $backLabel }}</a></li>
                        <li class="breadcrumb-item active text-primary">Task Management</li>
                    </ol>
                </nav>
                <h2 class="first-timer-name text-dark">{{ $firstTimer->first_name ?? 'N/A' }} {{ $firstTimer->last_name ?? '' }}</h2>
                <div class="mb-1">
                    <span class="meta-chip"><i class="fa fa-phone"></i> {{ $firstTimer->phone_number ?? 'No Phone' }}</span>
                    <span class="meta-chip"><i class="fa fa-user-tag"></i> {{ $firstTimer->attendant_type ?? 'First Timer' }}</span>
                    @if(!empty($firstTimer->created_at))
                        <span class="meta-chip"><i class="fa fa-calendar-check"></i> Joined {{ \Carbon\Carbon::parse($firstTimer->created_at)->format('M d, Y') }}</span>
                    @endif
                </div>
                @if($is_viewing_other)
                    <div class="first-timer-subtitle fw-bold text-muted mt-1">
                        <i class="fa fa-user-shield text-warning mr-1"></i>
                        Managing on behalf of <strong>{{ $viewing_guide_name ?: 'this guide' }}</strong>
                    </div>
                @else
                    <div class="first-timer-subtitle text-muted mt-1">
                        <i class="fa fa-user-check text-primary mr-1"></i> Your follow-up record
                    </div>
                @endif
            </div>
            <div class="profile-header-actions d-flex flex-wrap">
                <button type="button" class="btn btn-outline-primary shadow-sm mr-2 mb-1" data-toggle="modal" data-target="#detailsModal">
                    <i class="fa fa-info-circle mr-1"></i> Bio Details
                </button>
                <a href="https://wa.me/234{{ ltrim($firstTimer->phone_number ?? '', '0') }}" target="_blank" class="btn btn-whatsapp shadow-sm mb-1">
                    <i class="fab fa-whatsapp mr-1"></i> WhatsApp Chat
                </a>
            </div>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success')) 
        <div class="alert alert-success border-0 shadow-sm d-flex align-items-center">
            <i class="fa fa-check-circle mr-2"></i> {{ session('success') }}
        </div> 
    @endif
    @if(session('error')) 
        <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center">
            <i class="fa fa-exclamation-triangle mr-2"></i> {{ session('error') }}
        </div> 
    @endif

    {{-- Tasks List --}}
    <div class="row">
        <div class="col-lg-12">
            {{-- Follow-up weeks. Every week is stored in the JSON with a
                 `display` flag per task, so build a small model first: weeks
                 without a visible task are dropped (they used to render as an
                 empty timeline) and the first week that still needs an action
                 opens by itself. --}}
            @php
                $weekPanels = [];
                foreach ($tasks as $weekKey => $weekTasks) {
                    if (!is_array($weekTasks)) continue;

                    $visible = [];
                    foreach ($weekTasks as $taskKey => $task) {
                        if (is_array($task) && ($task['display'] ?? false)) {
                            $visible[$taskKey] = $task;
                        }
                    }
                    if (!$visible) continue;

                    $counts = ['approved' => 0, 'pending' => 0, 'skipped' => 0, 'open' => 0];
                    foreach ($visible as $task) {
                        $taskStatus = $task['status'] ?? 'Not Started';
                        if ($taskStatus == 'Approved') $counts['approved']++;
                        elseif ($taskStatus == 'Pending') $counts['pending']++;
                        elseif ($taskStatus == 'Skipped') $counts['skipped']++;
                        else $counts['open']++;
                    }

                    $weekLabel = preg_replace('/^week[_\s-]*(\d+)$/i', 'Week $1', $weekKey);
                    if (!$weekLabel) $weekLabel = ucfirst(str_replace('_', ' ', $weekKey));

                    $weekPanels[$weekKey] = [
                        'tasks' => $visible,
                        'counts' => $counts,
                        'total' => count($visible),
                        'label' => $weekLabel,
                    ];
                }

                $totalTasks = 0; $totalApproved = 0; $totalPending = 0; $totalOpen = 0; $totalSkipped = 0;
                foreach ($weekPanels as $panel) {
                    $totalTasks += $panel['total'];
                    $totalApproved += $panel['counts']['approved'];
                    $totalPending += $panel['counts']['pending'];
                    $totalOpen += $panel['counts']['open'];
                    $totalSkipped += $panel['counts']['skipped'];
                }
                $overallPct = $totalTasks ? (int) round($totalApproved / $totalTasks * 100) : 0;

                // First week that still needs an action, else the first week.
                $activeWeek = null;
                foreach ($weekPanels as $weekKey => $panel) {
                    if ($panel['counts']['open'] || $panel['counts']['pending']) {
                        $activeWeek = $weekKey;
                        break;
                    }
                }
                if ($activeWeek === null && $weekPanels) {
                    $activeWeek = array_key_first($weekPanels);
                }
            @endphp

            @if(count($weekPanels) > 0)
                {{-- At a glance: how far this follow-up has come --}}
                <div class="card summary-strip mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between">
                            <div class="mr-3">
                                <div class="fw-bold text-dark mb-1">
                                    <i class="fa fa-chart-line text-primary mr-1"></i> Follow-up Progress
                                </div>
                                <div class="small text-muted">
                                    {{ $totalApproved }} of {{ $totalTasks }} task{{ $totalTasks == 1 ? '' : 's' }} completed
                                    across {{ count($weekPanels) }} week{{ count($weekPanels) == 1 ? '' : 's' }}
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center">
                                @if($totalPending)
                                    <span class="badge badge-soft-warning mr-2 mb-1"><i class="fa fa-hourglass-half mr-1"></i> {{ $totalPending }} awaiting approval</span>
                                @endif
                                @if($totalOpen)
                                    <span class="badge badge-soft-info mr-2 mb-1">{{ $totalOpen }} to do</span>
                                @endif
                                @if($totalSkipped)
                                    <span class="badge badge-soft-danger mr-2 mb-1">{{ $totalSkipped }} skipped</span>
                                @endif
                                <button type="button" class="week-toolbar-btn mr-2 mb-1" data-week-toggle-all="open">Expand all</button>
                                <button type="button" class="week-toolbar-btn mb-1" data-week-toggle-all="close">Collapse all</button>
                            </div>
                        </div>
                        <div class="progress summary-progress mt-3 mb-0">
                            <div class="progress-bar {{ $overallPct >= 100 ? 'is-complete' : '' }}" role="progressbar" style="width: {{ $overallPct }}%" aria-valuenow="{{ $overallPct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            @endif
            @foreach($weekPanels as $weekName => $panel)
                @php
                    $isActiveWeek = ($weekName === $activeWeek);
                    $isCompleteWeek = (($panel['counts']['approved'] + $panel['counts']['skipped']) === $panel['total']);
                    $weekPct = $panel['total'] ? (int) round($panel['counts']['approved'] / $panel['total'] * 100) : 0;
                    $panelId = 'followup-week-' . $loop->iteration;
                @endphp
                <div class="card week-panel {{ $isActiveWeek ? 'is-active' : '' }} {{ $isCompleteWeek ? 'is-complete' : '' }}">
                    <div class="card-header week-panel-header p-0">
                        <button type="button" class="week-toggle" data-toggle="collapse" data-target="#{{ $panelId }}"
                                aria-expanded="{{ $isActiveWeek ? 'true' : 'false' }}" aria-controls="{{ $panelId }}">
                            <span class="d-flex align-items-center">
                                <span class="week-index">{{ $loop->iteration }}</span>
                                <span class="flex-grow-1 mr-2">
                                    <span class="week-title d-block text-dark">{{ $panel['label'] }}</span>
                                    <span class="small text-muted d-block">
                                        {{ $panel['counts']['approved'] }} of {{ $panel['total'] }} completed
                                        @if($panel['counts']['pending'])
                                            &middot; {{ $panel['counts']['pending'] }} awaiting approval
                                        @endif
                                        @if($panel['counts']['open'])
                                            &middot; {{ $panel['counts']['open'] }} to do
                                        @endif
                                    </span>
                                </span>
                                <span class="d-none d-sm-flex align-items-center mr-3">
                                    @if($isCompleteWeek)
                                        <span class="badge badge-soft-success"><i class="fa fa-check mr-1"></i> Complete</span>
                                    @elseif($panel['counts']['pending'])
                                        <span class="badge badge-soft-warning"><i class="fa fa-hourglass-half mr-1"></i> Pending</span>
                                    @else
                                        <span class="badge badge-soft-info">In progress</span>
                                    @endif
                                </span>
                                <span class="week-progress mr-3" aria-hidden="true">
                                    <span class="week-progress-bar" style="width: {{ $weekPct }}%"></span>
                                </span>
                                <i class="fa fa-chevron-down week-chevron" aria-hidden="true"></i>
                            </span>
                        </button>
                    </div>
                    <div id="{{ $panelId }}" class="collapse {{ $isActiveWeek ? 'show' : '' }}">
                        <div class="card-body">
                            <div class="task-timeline">
                                @foreach($panel['tasks'] as $taskKey => $task)
                                    @php
                                        $status = $task['status'] ?? 'Not Started';
                                        $statusClass = 'status-open';
                                        if($status == 'Approved') $statusClass = 'status-approved';
                                        elseif($status == 'Pending') $statusClass = 'status-pending';
                                        elseif($status == 'Skipped') $statusClass = 'status-skipped';
                                    @endphp

                                    <div class="task-item {{ $statusClass }}">
                                        <div class="card task-card {{ $statusClass }} shadow-sm mb-0">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start flex-wrap">
                                                    <div class="flex-grow-1 pr-2">
                                                        <h6 class="fw-bold mb-2 text-dark" style="font-size: 0.95rem;">{!! $task['text'] !!}</h6>

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
                                                            <button type="button" class="btn-skip" title="Skip this task" onclick="skipTask('{{ $tracking->tracking_id }}', '{{ $weekName }}', '{{ $taskKey }}')">
                                                                <i class="fa fa-forward mr-1"></i> Skip
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- History / Previous Attempts --}}
                                                @if(isset($task['logs']) && count($task['logs']) > 0)
                                                    <div class="mt-3">
                                                        <p class="text-uppercase fw-bold text-muted mb-2" style="font-size: 0.7rem;"><i class="fa fa-history mr-1"></i> Previous Attempts History</p>
                                                        @foreach($task['logs'] as $log)
                                                            <div class="log-entry">
                                                                <div class="d-flex justify-content-between">
                                                                    <span class="fw-bold text-dark mr-2"><i class="fa fa-angle-right text-muted mr-1"></i>{{ $log['outcome'] }}</span>
                                                                    <span class="text-muted small text-nowrap">
                                                                        <i class="fa fa-clock mr-1"></i>{{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->diffForHumans() : '' }}
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
                                                    <div class="mt-3 pt-3 border-top">
                                                        <div class="form-row">
                                                            <div class="col-md-4 mb-2">
                                                                <small class="d-block text-muted text-uppercase font-weight-bold" style="font-size: 0.7rem;">Final Outcome</small>
                                                                <span class="fw-bold text-dark">{{ $task['outcome'] ?? 'N/A' }}</span>
                                                            </div>
                                                            <div class="col-md-4 mb-2">
                                                                <small class="d-block text-muted text-uppercase font-weight-bold" style="font-size: 0.7rem;">Completed On</small>
                                                                <span class="fw-bold text-dark">{{ $task['completed_at'] ?? 'N/A' }}</span>
                                                            </div>
                                                            @if(!empty($task['comment']))
                                                                <div class="col-md-4 mb-2">
                                                                    <small class="d-block text-muted text-uppercase font-weight-bold" style="font-size: 0.7rem;">Note</small>
                                                                    <span class="italic text-dark">"{{ $task['comment'] }}"</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @elseif($status == 'Skipped')
                                                    <div class="skipped-note mt-3">
                                                        <i class="fa fa-forward mr-1"></i> This task was skipped by an Administrator.
                                                    </div>
                                                @else
                                                    {{-- Submission Form --}}
                                                    <div class="mt-3 pt-3 border-top">
                                                        <form action="{{ route('my-tasks.update-task') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="tracking_id" value="{{ $tracking->tracking_id }}">
                                                            <input type="hidden" name="week" value="{{ $weekName }}">
                                                            <input type="hidden" name="task_key" value="{{ $taskKey }}">
                                                            <input type="hidden" name="action" value="log_outcome">
                                                            @if($is_viewing_other)
                                                                {{-- Attribute the log to the guide whose record is managed --}}
                                                                <input type="hidden" name="performing_guide_id" value="{{ $guide_id }}">
                                                            @endif

                                                            <div class="form-row">
                                                                <div class="col-md-5 mb-2">
                                                                    <label class="form-label fw-bold text-muted small text-uppercase">Outcome *</label>
                                                                    <select name="outcome" class="custom-select border-primary shadow-sm" required>
                                                                        <option value="">Choose outcome...</option>
                                                                        <option value="Successful - Positive">✅ Successful - Positive (Approve)</option>
                                                                        <option value="Successful - Neutral">😐 Successful - Neutral (Pending)</option>
                                                                        <option value="Successful - Negative">❌ Successful - Negative (Pending)</option>
                                                                        <option value="Unable to Contact - No Answer">📞 No Answer</option>
                                                                        <option value="Unable to Contact - Invalid Number">❓ Invalid Number</option>
                                                                    </select>
                                                                </div>
                                                                <div class="col-md-5 mb-2">
                                                                    <label class="form-label fw-bold text-muted small text-uppercase">Internal Comment</label>
                                                                    <textarea name="comment" class="form-control" rows="2" placeholder="Add optional details..."></textarea>
                                                                </div>
                                                                <div class="col-md-2 mb-2 d-flex align-items-end">
                                                                    <button type="submit" class="btn btn-primary btn-block fw-bold shadow-sm py-2">Submit</button>
                                                                </div>
                                                            </div>
                                                            <div class="small text-muted mt-2">
                                                                <i class="fa fa-info-circle text-primary"></i> 
                                                                Note: Tasks have a 48-hour cooldown after approval before the next one unlocks.
                                                            </div>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            @if(count($weekPanels) === 0)
                <div class="card border-0 shadow-sm text-center py-5">
                    <div class="card-body">
                        <i class="fa fa-tasks fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted mb-1">{{ $is_viewing_other && !$tracking ? 'No tracking record for this first-timer under ' . ($viewing_guide_name ?: 'this guide') . '.' : 'No tasks found for this record.' }}</h5>
                        <p class="text-muted small mb-3">Follow-up tasks appear here as soon as a task is unlocked for this record.</p>
                        <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-arrow-left mr-1"></i> Back to {{ $backLabel }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- BIO DETAILS MODAL -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header soft-surface border-0">
                <h5 class="modal-title fw-bold text-dark">First Timer Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <table class="table mb-0">
                    <tbody class="small">
                        <tr><td class="text-muted pl-4 py-3">Full Name</td><td class="fw-bold pr-4 py-3 text-right">{{ ($firstTimer->first_name ?? '') . ' ' . ($firstTimer->last_name ?? '') }}</td></tr>
                        <tr><td class="text-muted pl-4 py-3">Gender</td><td class="fw-bold pr-4 py-3 text-right">{{ $firstTimer->gender ?? 'N/A' }}</td></tr>
                        <tr><td class="text-muted pl-4 py-3">Occupation</td><td class="fw-bold pr-4 py-3 text-right">{{ $firstTimer->occupation ?? 'N/A' }}</td></tr>
                        <tr><td class="text-muted pl-4 py-3">Address</td><td class="fw-bold pr-4 py-3 text-right" style="max-width: 200px; white-space: normal;">{{ $firstTimer->address ?? 'N/A' }}</td></tr>
                        <tr>
                            <td class="text-muted pl-4 py-3 border-0">Registered On</td>
                            <td class="fw-bold pr-4 py-3 text-right text-primary border-0">
                                {{ isset($firstTimer->created_at) ? \Carbon\Carbon::parse($firstTimer->created_at)->format('M d, Y') : 'N/A' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 soft-surface">
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
        @if($is_viewing_other)
        // Attribute the log to the guide whose record is being managed
        fields.push({name: 'performing_guide_id', value: '{{ $guide_id }}'});
        @endif
        
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

/* Collapsible follow-up weeks -------------------------------------------
   The week headers drive Bootstrap's collapse plugin through data
   attributes; these two toolbar buttons drive the same plugin for every
   panel. jQuery is loaded after this block, so wait for DOMContentLoaded. */
document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('[data-week-toggle-all]');

    if (!buttons.length) {
        return;
    }

    var $ = window.jQuery;

    Array.prototype.forEach.call(buttons, function (button) {
        button.addEventListener('click', function () {
            var expand = button.getAttribute('data-week-toggle-all') === 'open';

            Array.prototype.forEach.call(document.querySelectorAll('.week-panel .collapse'), function (panel) {
                if ($ && $.fn && $.fn.collapse) {
                    $(panel).collapse(expand ? 'show' : 'hide');
                    return;
                }

                if (expand !== panel.classList.contains('show')) {
                    panel.classList.toggle('show');
                }
            });
        });
    });
});
</script>
@endsection
