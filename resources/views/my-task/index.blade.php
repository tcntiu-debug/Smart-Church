@extends('layouts.app')

@section('content')
<!-- Add Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --status-blue: #3e52a3; 
        --action-orange: #f97316; /* Professional Orange */
        --action-orange-hover: #ea580c;
        --accent-yellow: #fcd34d; 
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

    /* --- Header Section --- */
    .dashboard-header {
        margin-bottom: 2rem;
        padding: 0 0.5rem;
    }

    /* Smaller Header requested */
    .dashboard-header h3 {
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 0;
    }

    .badge-total {
        background: white;
        color: var(--text-dark);
        border: 1px solid var(--border-color);
        font-weight: 700;
        padding: 8px 18px;
        border-radius: 50px;
        font-size: 0.85rem;
    }

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

    /* --- Layout Blocks --- */
    .rating-style-box {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
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
        border: none;
        font-weight: 800;
        font-size: 1.3rem;
        color: var(--status-blue);
        background-color: transparent;
        width: 100%;
        text-align: center;
        cursor: pointer;
        padding: 0;
        outline: none;
    }

    /* Address Box with Save Button underneath */
    .address-dashed-box {
        border: 1px dashed var(--accent-yellow);
        background: #fffdf5;
        border-radius: 12px;
        padding: 12px 16px;
        height: 100%;
    }

    .address-textarea {
        background: transparent;
        border: none;
        font-size: 0.95rem;
        font-weight: 500;
        color: #475569;
        resize: none;
        width: 100%;
        outline: none;
        margin-bottom: 8px;
    }

    .btn-save-address {
        background: var(--status-blue);
        color: white;
        border: none;
        padding: 4px 15px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        transition: opacity 0.2s;
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
</style>

<div class="container py-5">
    <!-- Header Section -->
    <div class="dashboard-header d-flex align-items-center justify-content-between">
        <div>
            <h3 class="text-dark">My Assignees</h3>
            <p class="text-muted small fw-bold mb-0">FOLLOW-UP LIST · ACTIVE ASSIGNMENTS</p>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <div class="badge-total shadow-sm">
                {{ $firstTimers->count() }} Tracking Records
            </div>
            <div class="badge-total shadow-sm" style="border-color: #f97316;">
                {{ $fofFirstTimers->count() }} FOF Records
            </div>
        </div>
    </div>

    <!-- ==================== TRACKING & INTEGRATION PANEL ==================== -->
    @if($firstTimers->count() > 0)
    <div class="card mb-4 shadow-sm" style="border-left: 4px solid var(--status-blue);">
        <div class="card-header py-3" style="background: #f0f2fa;">
            <h5 class="mb-0 fw-bold" style="color: var(--status-blue);">
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
                        <div class="card-body p-4 p-lg-4">
                            <div class="row align-items-center g-4">
                                
                                <!-- 1. Name Section -->
                                <div class="col-lg-3 border-right-divider">
                                    <h4 class="fw-bold mb-0 text-dark">{{ $ft->first_name }} {{ $ft->last_name }}</h4>
                                </div>

                                <!-- 2. Status & Address Section -->
                                <div class="col-lg-6">
                                    <div class="row g-3">
                                        <!-- Status Block -->
                                        <div class="col-md-4">
                                            <div class="rating-style-box">
                                                <label class="small-label">FOF STATUS</label>
                                                <form action="{{ route('my-tasks.update') }}" method="POST">
                                                    @csrf
                                                    <input type="hidden" name="first_timer_id" value="{{ $ft->first_timer_id }}">
                                                    <input type="hidden" name="update_type" value="fof">
                                                    <select name="value" onchange="this.form.submit()" class="form-select-minimal">
                                                        <option value="No" {{ ($updatesData[$ft->first_timer_id]['foundation_of_faith'] ?? '') == 'No' ? 'selected' : '' }}>No</option>
                                                        <option value="Yes" {{ ($updatesData[$ft->first_timer_id]['foundation_of_faith'] ?? '') == 'Yes' ? 'selected' : '' }}>Yes</option>
                                                    </select>
                                                </form>
                                            </div>
                                        </div>

                                        <!-- Address Block -->
                                        <div class="col-md-8">
                                            <form action="{{ route('my-tasks.update') }}" method="POST" class="address-dashed-box">
                                                @csrf
                                                <input type="hidden" name="first_timer_id" value="{{ $ft->first_timer_id }}">
                                                <input type="hidden" name="update_type" value="address">
                                                
                                                <label class="small-label">PRIMARY ADDRESS</label>
                                                <textarea name="value" class="address-textarea" rows="2" placeholder="Enter address details...">{{ $ft->address }}</textarea>
                                                
                                                <div class="text-start">
                                                    <button type="submit" class="btn-save-address">Save</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Action Section -->
                                <div class="col-lg-3 text-center">
                                    <div class="ps-lg-3">
                                        <a href="{{ route('my-tasks.tasks', $ft->first_timer_id) }}" class="btn btn-manage-orange shadow-sm">
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

    <!-- ==================== FOUNDATION OF FAITH SUPPORT PANEL ==================== -->
    @if($fofFirstTimers->count() > 0)
    <div class="card mb-4 shadow-sm" style="border-left: 4px solid var(--action-orange);">
        <div class="card-header py-3" style="background: #fff7ed;">
            <h5 class="mb-0 fw-bold" style="color: var(--action-orange);">
                <i class="fas fa-pray mr-2"></i> Foundation of Faith Support
                <span class="badge badge-warning ml-2" style="background: var(--action-orange); color: white;">{{ $fofFirstTimers->count() }}</span>
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                @forelse($fofFirstTimers as $fof)
                <div class="col-12 position-relative">
                    <div class="index-tag" style="background: #fed7aa; color: #9a3412;">{{ $loop->iteration }}</div>

                    <div class="card assignee-card shadow-sm">
                        <div class="card-body p-4 p-lg-4">
                            <div class="row align-items-center g-4">
                                
                                <!-- 1. Name + Phone Section -->
                                <div class="col-lg-4 border-right-divider">
                                    <h4 class="fw-bold mb-1 text-dark">{{ $fof->first_name }} {{ $fof->last_name }}</h4>
                                    <p class="mb-0 small text-muted">
                                        <i class="fas fa-phone-alt mr-1"></i> {{ $fof->phone_number ?? 'N/A' }}
                                    </p>
                                </div>

                                <!-- 2. Gender & Cohort Info -->
                                <div class="col-lg-5">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="rating-style-box">
                                                <label class="small-label">GENDER</label>
                                                <span class="fw-bold" style="font-size: 1.1rem;">{{ $fof->gender ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="rating-style-box">
                                                <label class="small-label">COHORT</label>
                                                <span class="fw-bold" style="font-size: 1.1rem;">{{ $fof->cohort_id ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Action Section -->
                                <div class="col-lg-3 text-center">
                                    <div class="ps-lg-3">
                                        <a href="{{ route('my-tasks.tasks', $fof->id) }}" class="btn btn-manage-orange shadow-sm" style="background: var(--status-blue);">
                                            Manage Tasks
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                                        </a>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 text-center py-4">
                    <p class="text-muted mb-0">No FOF Support assignees.</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    @if($firstTimers->count() == 0 && $fofFirstTimers->count() == 0)
    <div class="col-12 text-center py-5">
        <h5 class="text-muted">No assignees found in your list.</h5>
    </div>
    @endif
</div>
@endsection