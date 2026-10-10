@extends('layouts.app')

@section('content')
<!-- Add Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --status-blue: #3e52a3; 
        --action-orange: #f97316; /* Professional Orange */
        --action-orange-hover: #ea580c;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --bg-light: #f4f6fa;
        --border-color: #e2e8f0;
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Inter', sans-serif;
        color: var(--text-dark);
    }

    /* ---- Bootstrap 4.1.3 shims ------------------------------------------
       The header card below (and the rest of this page) uses a few utilities
       that only exist in later Bootstrap releases: `fw-bold` and
       `text-decoration-none` are not in bootstrap.min.css, which is why the
       header label used to render at normal weight. Same shim as
       tasks.blade.php. */
    .fw-bold { font-weight: 700 !important; }
    .text-decoration-none { text-decoration: none !important; }

    /* ---- Header card ----------------------------------------------------
       Visual twin of the task page's `.profile-header`
       (resources/views/my-task/tasks.blade.php), so /my-tasks and
       /my-tasks/{id}/tasks open with one header language: white card, 5px
       blue rail, breadcrumb, 1.4rem title, soft chips.
       This replaces a flat `.dashboard-header` heading that had no shell and
       no breadcrumb, so the two pages read as different apps. */
    .list-header {
        background: #fff;
        padding: 1rem 1.15rem;
        border-radius: 12px;
        margin-bottom: 1.1rem;
        border-left: 5px solid #4e73df;
        box-shadow: 0 2px 4px rgba(0,0,0,0.04);
    }
    /* style.css ships `h2 { font-size: 48px }`, hence the class-scoped size. */
    .list-header .list-title { font-size: 1.4rem; font-weight: 700; line-height: 1.25; margin-bottom: 0.35rem; word-break: break-word; }
    .list-header .breadcrumb { font-size: 0.82rem; background: transparent; padding: 0; margin-bottom: 0.3rem; }
    .list-header .list-subtitle { font-size: 0.82rem; }

    /* Count pill (header) + meta pills. Deliberately NOT `bg-light`:
       style.css redefines `.bg-light{background-color:#878793}`, which painted
       such pills dark grey and swallowed their text. Twin markup in
       tasks.blade.php. */
    .meta-chip {
        display: inline-flex; align-items: center;
        padding: 0.2rem 0.65rem; margin: 0 0.35rem 0.35rem 0;
        border-radius: 999px; border: 1px solid #dfe4f3;
        background: #f2f4fb; color: #333c5c;
        font-size: 0.78rem; font-weight: 600; line-height: 1.5; white-space: nowrap;
    }
    .meta-chip i { color: #4e73df; margin-right: 0.35rem; }
    .meta-chip.meta-chip-lg { padding: 0.45rem 0.95rem; font-size: 0.85rem; font-weight: 700; }

    /* --- The Card --- */
    .assignee-card {
        background: white;
        border-radius: 16px;
        border: 1px solid transparent;
        position: relative;
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease;
    }

    .assignee-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    }

    .index-tag {
        position: absolute;
        top: -10px;
        left: 20px;
        background: #cbd5e1;
        color: #475569;
        font-weight: 800;
        font-size: 0.7rem;
        padding: 2px 10px;
        border-radius: 4px;
        z-index: 5;
    }

    /* --- Layout Blocks ----------------------------------------------------
       FOF status and primary address are the same object - an eyebrow label
       above one value - so they now share a single shell. They used to be two
       different boxes: a white one with a solid border and a centred value,
       next to a warm yellow one with a *dashed* border and a left-aligned
       value. Two border languages and two alignments made one card read as two
       unrelated components, and the dashed yellow box borrowed an "attention"
       signal the address field never needed.
       The assignee name carries the same eyebrow but stays unboxed: it is the
       card's title, and boxing all three columns would nest box inside box. */
    .data-panel {
        background: #f7f8fd;
        border: 1px solid #e6e9f5;
        border-radius: 12px;
        padding: 12px 14px;
        height: 100%;
    }

    .small-label {
        display: block;
        font-size: 0.65rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .form-select-minimal {
        appearance: none;
        -webkit-appearance: none;
        border: none;
        background-color: transparent;
        /* Inline chevron. A borderless transparent select hides the native
           arrow, so the value read as static text instead of a control users
           can change. Must stay in sync with the orange twin in dark mode. */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none' stroke='%233e52a3' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='4 6 8 10 12 6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.15rem center;
        background-size: 15px 15px;
        font-weight: 800;
        font-size: 1.15rem;
        line-height: 1.4;
        color: var(--status-blue);
        width: 100%;
        text-align: left;
        cursor: pointer;
        padding: 0 1.5rem 0 0;
        outline: none;
    }

    /* Address value. The box, background and padding now come from
       `.data-panel`; only the field itself is styled here. */
    .address-textarea {
        display: block;
        background: transparent;
        border: none;
        font-size: 0.95rem;
        font-weight: 500;
        line-height: 1.45;
        color: #475569;
        resize: none;
        width: 100%;
        outline: none;
        margin-bottom: 6px;
    }

    .btn-save-address {
        background: var(--status-blue);
        color: white;
        border: none;
        padding: 6px 18px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        transition: background-color 0.2s;
    }

    .btn-save-address:hover {
        background: #35468c;
        color: white;
    }

    .btn-save-address:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(62, 82, 163, 0.22);
    }

    /* Orange Manage Tasks Button */
    .btn-manage-orange {
        background-color: var(--action-orange);
        color: white;
        font-weight: 700;
        padding: 14px 24px;
        border-radius: 10px;
        border: none;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-decoration: none;
        transition: background-color 0.2s;
    }

    .btn-manage-orange:hover {
        background-color: var(--action-orange-hover);
        color: white;
    }

    /* Desktop Dividers */
    @media (min-width: 992px) {
        .border-right-divider { border-right: 1px solid #f1f5f9; }
    }

    /* Tracking & Integration panel header (moved out of inline style attributes
       so the dark palette below is able to override it). */
    .tracking-panel-header {
        background: #f0f2fa;
    }
    .tracking-panel-title {
        color: var(--status-blue);
    }

    /* ---- Dark mode -------------------------------------------------------
       style.css scopes every dark rule to `ms-dark-theme`: its
       `body.ms-dark-theme, .ms-dark-theme a, .ms-dark-theme span:...` block
       forces every link/span to #fff and `.ms-dark-theme .card` repaints the
       outer assignee card #252851. The Bootstrap utilities used here
       (`.text-dark`, `.text-muted`) are `!important`, so without the
       counterparts below the page headings stay dark on the dark card - i.e.
       invisible - and the white boxes keep template-forced light text.
       Palette: surface #252851, deeper #1f2247, hover #2a2e5b, border #242750,
       accent #ff8306 (see docs/DISPLAY-MODE.md). */
    .ms-dark-theme .list-header .list-title,
    .ms-dark-theme .assignee-card .text-dark {
        color: #fff !important;
    }
    .ms-dark-theme .text-muted {
        color: #b9bcd8 !important;
    }
    .ms-dark-theme .list-header {
        background: #252851;
        box-shadow: none;
    }
    /* Beats style.css' `.ms-dark-theme span{color:#fff}` on specificity. */
    .ms-dark-theme .meta-chip {
        background: #323a67;
        border-color: #242750;
        color: #dfe3ff;
    }
    .ms-dark-theme .meta-chip i {
        color: #9db2ff;
    }
    .ms-dark-theme .tracking-panel-header {
        background: #252851;
        border-bottom: 1px solid #242750;
    }
    .ms-dark-theme .tracking-panel-title {
        color: #fff;
    }
    .ms-dark-theme .assignee-card {
        background: #252851;
        border-color: #242750;
    }
    .ms-dark-theme .index-tag {
        background: #323a67;
        color: #e7e8f5;
    }
    .ms-dark-theme .border-right-divider {
        border-right-color: #242750;
    }
    /* Inner panels sit one step above the #252851 card (same step as
       `.meta-chip`) so both labelled values stay readable as panels. */
    .ms-dark-theme .data-panel {
        background: #323a67;
        border-color: #242750;
    }
    .ms-dark-theme .small-label {
        color: #b9bcd8;
    }
    .ms-dark-theme .form-select-minimal {
        color: #ff8306;
        /* Chevron has to follow the orange value, not the light-mode blue. */
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none' stroke='%23ff8306' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='4 6 8 10 12 6'/%3E%3C/svg%3E");
    }
    .ms-dark-theme .btn-save-address {
        background: #4e73df;
    }
    .ms-dark-theme .btn-save-address:hover {
        background: #5b7fe4;
    }
    .ms-dark-theme .address-textarea {
        color: #e7e8f5;
    }
    .ms-dark-theme .address-textarea::placeholder {
        color: #9aa0c4;
    }

    /* ---- Admin drill-down: "viewing on behalf of another guide" banner -----
       Task Overview (/aoverview) opens another guide's list with ?guide_id=..;
       the banner keeps that obvious. Light rule first, dark twin right below
       (see docs/DISPLAY-MODE.md). */
    .admin-viewing-banner {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-left: 4px solid var(--action-orange);
        border-radius: 10px;
        padding: 12px 18px;
        color: #7c2d12;
        font-weight: 600;
    }

    .ms-dark-theme .admin-viewing-banner {
        background: #1f2247;
        border-color: #242750;
        border-left-color: var(--action-orange);
        color: #e7e8f5;
    }

    /* ---- Phone tuning ----------------------------------------------------
       Phones are the primary target, so the page is checked at ~360px.
       style.css' desktop h1..h6 scale is reduced for small screens at the end
       of that file; the sizes below are fixed in rem here, so they need their
       own phone twins or they stay desktop-sized. Scoped to this page so the
       desktop layout is untouched. */
    @media (max-width: 767.98px) {
        .list-header { padding: 0.85rem 0.95rem; border-left-width: 4px; margin-bottom: 0.9rem; }
        .list-header .list-title { font-size: 1.15rem; }
        .list-header .breadcrumb,
        .list-header .list-subtitle { font-size: 0.75rem; }
        .meta-chip { font-size: 0.72rem; }
        .meta-chip.meta-chip-lg { font-size: 0.76rem; padding: 0.35rem 0.7rem; }

        /* The 24px/21px bold titles below were the "text is too big" offenders:
           style.css' bare h4/h5 sizes. */
        .tracking-panel-title { font-size: 0.95rem; }
        .assignee-card h4 { font-size: 1.05rem; }
        .index-tag { left: 14px; }
        .data-panel { padding: 10px 12px; border-radius: 10px; }
        .btn-save-address { padding: 8px 18px; }
        .btn-manage-orange { padding: 11px 16px; font-size: 0.9rem; gap: 8px; }
        .admin-viewing-banner { padding: 10px 12px; font-size: 0.82rem; }
        .admin-viewing-banner .btn { font-size: 0.75rem; }

        /* Fields stay at 16px: iOS Safari zooms the whole page in when a
           focused field is smaller than that. Desktop: 1.15rem / 0.95rem. */
        .form-select-minimal,
        .address-textarea { font-size: 1rem; }
    }
</style>

<div class="container py-4">
    <!-- Header Section (card shell mirrors the task page's .profile-header) -->
    <div class="list-header">
        <div class="d-flex flex-wrap align-items-start justify-content-between">
            <div class="pr-md-4 mb-2">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/mytask') }}" class="text-decoration-none text-muted">Home</a></li>
                        <li class="breadcrumb-item active text-primary" aria-current="page">{{ $is_viewing_other ? 'Assignees' : 'My Assignees' }}</li>
                    </ol>
                </nav>
                <h2 class="list-title text-dark">{{ $is_viewing_other ? 'Managing ' . ($viewing_guide_name ?: 'this guide') . "'s Assignees" : 'My Assignees' }}</h2>
                <div class="list-subtitle text-muted">
                    <i class="fa fa-user-check text-primary mr-1"></i> Follow-up list &middot; Active assignments
                </div>
            </div>
            <div class="d-flex align-items-center">
                <span class="meta-chip meta-chip-lg mb-0">
                    <i class="fa fa-clipboard-list"></i> {{ $firstTimers->count() }} Tracking Records
                </span>
            </div>
        </div>
    </div>

    @if($is_viewing_other)
    <!-- Admin drill-down context (Task Overview -> guide -> Manage task) -->
    <div class="admin-viewing-banner mb-4">
        <div class="d-flex align-items-center flex-wrap">
            <i class="fas fa-user-shield mr-2"></i>
            <span>Viewing on behalf of <strong>{{ $viewing_guide_name ?: 'this guide' }}</strong></span>
            <a href="{{ route('aoverview') }}" class="btn btn-sm btn-outline-primary ml-auto">Back to Task Overview</a>
        </div>
    </div>
    @endif

    <!-- ==================== TRACKING & INTEGRATION PANEL ==================== -->
    @if($firstTimers->count() > 0)
    <div class="card mb-4 shadow-sm" style="border-left: 4px solid var(--status-blue);">
        <div class="card-header py-3 tracking-panel-header">
            <h5 class="mb-0 fw-bold tracking-panel-title">
                <i class="fas fa-sync-alt mr-2"></i> Tracking & Integration
                <span class="badge badge-primary ml-2">{{ $firstTimers->count() }}</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                @forelse($firstTimers as $ft)
                <div class="col-12 position-relative">
                    <div class="index-tag">{{ $loop->iteration }}</div>

                    <div class="card assignee-card shadow-sm">
                        <div class="card-body p-3 p-lg-4">
                            <div class="row align-items-center g-4">
                                
                                <!-- 1. Name Section -->
                                <div class="col-lg-3 border-right-divider">
                                    <span class="small-label">Assignee</span>
                                    <h4 class="fw-bold mb-0 text-dark">{{ $ft->first_name }} {{ $ft->last_name }}</h4>
                                </div>

                                <!-- 2. Status & Address Section -->
                                <div class="col-lg-6">
                                    <div class="row g-3">
                                        <!-- Status Block -->
                                        <div class="col-md-4">
                                            <div class="data-panel">
                                                <label class="small-label" for="fof-{{ $ft->first_timer_id }}-{{ $loop->iteration }}">FOF Status</label>
                                                <form action="{{ route('my-tasks.update') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="first_timer_id" value="{{ $ft->first_timer_id }}">
                                                    <input type="hidden" name="update_type" value="fof">
                                                    <select id="fof-{{ $ft->first_timer_id }}-{{ $loop->iteration }}" name="value" onchange="this.form.submit()" class="form-select-minimal">
                                                        <option value="No" {{ ($updatesData[$ft->first_timer_id]['foundation_of_faith'] ?? '') == 'No' ? 'selected' : '' }}>No</option>
                                                        <option value="Yes" {{ ($updatesData[$ft->first_timer_id]['foundation_of_faith'] ?? '') == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                    </select>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Address Block -->
                                        <div class="col-md-8">
                                            <form action="{{ route('my-tasks.update') }}" method="POST" class="data-panel">
                                                @csrf
                                                <input type="hidden" name="first_timer_id" value="{{ $ft->first_timer_id }}">
                                                <input type="hidden" name="update_type" value="address">
                                                
                                                <label class="small-label" for="address-{{ $ft->first_timer_id }}-{{ $loop->iteration }}">Primary Address</label>
                                                <textarea id="address-{{ $ft->first_timer_id }}-{{ $loop->iteration }}" name="value" class="address-textarea" rows="2" placeholder="Enter address details...">{{ $ft->address }}</textarea>
                                                
                                                <div class="text-right">
                                                    <button type="submit" class="btn-save-address">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Action Section -->
                                <div class="col-lg-3 text-center">
                                    <div class="ps-lg-3">
                                        {{-- Carry ?guide_id=.. so the task page
                                             loads THIS guide's tracking record --}}
                                        <a href="{{ route('my-tasks.tasks', array_filter([
                                            'id' => $ft->first_timer_id,
                                            'guide_id' => $is_viewing_other ? $guide_id : null,
                                            'guide_name' => $is_viewing_other ? $viewing_guide_name : null,
                                        ])) }}" class="btn btn-manage-orange shadow-sm">
                                            Manage Tasks
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                        </a>
                                        
                                        @if(($trackingData[$ft->first_timer_id]['pending_count'] ?? 0) > 0)
                                            <div class="mt-2 small fw-bold text-danger">
                                                ● {{ $trackingData[$ft->first_timer_id]['pending_count'] }} tasks pending
                                            </div>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 text-center py-4">
                    <p class="text-muted mb-0">No Tracking & Integration assignees.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @if($firstTimers->count() == 0)
    <div class="col-12 text-center py-5">
        <h5 class="text-muted">{{ $is_viewing_other ? 'No follow-up assignees found for ' . ($viewing_guide_name ?: 'this guide') . '.' : 'No assignees found in your list.' }}</h5>
    </div>
    @endif
</div>
@endsection