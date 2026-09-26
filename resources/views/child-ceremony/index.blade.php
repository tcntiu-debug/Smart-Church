@extends('layouts.app')

@section('content')
<style>
    .header-banner {
        background: #F97316;
        color: white;
        border-radius: 12px;
        padding: 18px 24px;
        margin-bottom: 20px;
    }
    .header-banner h6 { font-weight: 800; margin: 0; color: white; }
    .header-banner small { opacity: 0.9; color: white; }

    .type-btn {
        flex: 1;
        padding: 16px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        background: #f8fafc;
        color: #333;
        font-weight: 700;
        font-size: 0.95rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .type-btn:hover { border-color: #3e52a3; background: #eef1ff; }
    .type-btn.active {
        border-color: #3e52a3;
        background: #eef2ff;
        box-shadow: 0 0 0 3px rgba(62,82,163,0.15);
    }
    .type-btn i { display: block; font-size: 1.4rem; margin-bottom: 4px; }
    .type-btn small { display: block; color: #64748b; font-weight: 500; margin-top: 2px; font-size: 0.75rem; }

    .form-section { display: none; }
    .form-section.active { display: block; }

    .member-summary {
        background: #f0f4ff;
        border: 1px solid #d0d9ff;
        border-radius: 12px;
        padding: 14px 18px;
        margin-bottom: 20px;
    }
    .member-summary .name { font-weight: 800; color: #1e293b; }
    .member-summary .detail { font-size: 0.85rem; color: #64748b; }

    .selected-type-badge {
        display: inline-block;
        padding: 4px 14px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
    }
    .selected-type-badge.naming { background: #dbeafe; color: #1d4ed8; }
    .selected-type-badge.dedication { background: #fce7f3; color: #be185d; }

    .btn-submit-ceremony {
        background: #F97316; color: white;
        border: none; padding: 10px 36px; border-radius: 10px;
        font-weight: 700; font-size: 0.95rem;
        transition: all 0.2s;
    }
    .btn-submit-ceremony:hover { background: #e3600e; color: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(249,115,22,0.3); }
    .btn-submit-ceremony:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

    .toast-notification {
        position: fixed; top: 20px; right: 20px;
        z-index: 9999; min-width: 350px;
    }

    @media (max-width: 768px) {
        .type-selector { flex-direction: column; gap: 10px; }
    }
</style>

@php
function safeStr($val) {
    if (is_null($val)) return '';
    if (is_string($val)) return $val;
    if (is_array($val)) return implode(', ', $val);
    if (is_object($val)) return json_encode($val);
    return (string) $val;
}
$fname = safeStr($member->first_name ?? '');
$lname = safeStr($member->last_name ?? '');
$phone = safeStr($member->phone_number ?? '');
$email = safeStr($member->email ?? '');
@endphp

<div class="container py-4">

    {{-- Yellow Banner Header --}}
    <div class="header-banner">
        <h6><i class="fas fa-baby"></i> Child Dedication & Naming Ceremony</h6>
        <small>We celebrate with you on the arrival of your bundle(s) of joy. Fill the form below for proper documentation and arrangements.</small>
    </div>

    <form id="ceremonyForm" autocomplete="off" novalidate>
        @csrf

        {{-- Type Selector --}}
        <div class="ms-panel mb-4">
            <div class="ms-panel-body">
                <div class="type-selector d-flex gap-3">
                    <div class="type-btn active" data-type="naming" onclick="selectType(this, 'naming')">
                        <i class="fas fa-baby-carriage"></i>
                        Naming Ceremony
                        <small>Newborn naming ceremony</small>
                    </div>
                    <div class="type-btn" data-type="dedication" onclick="selectType(this, 'dedication')">
                        <i class="fas fa-praying-hands"></i>
                        Child Dedication
                        <small>Returning child to God's presence</small>
                    </div>
                </div>
                <input type="hidden" name="ceremony_type" id="ceremonyType" value="naming">
            </div>
        </div>

        {{-- NAMING FORM --}}
        <div class="ms-panel form-section active" id="namingForm">
            <div class="ms-panel-header">
                <h6><i class="fas fa-baby-carriage"></i> Naming Ceremony Details</h6>
            </div>
            <div class="ms-panel-body">

                <div class="member-summary">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="name">{{ $fname }} {{ $lname }}</div>
                            <div class="detail">
                                <i class="fas fa-phone-alt"></i> {{ $phone }}
                                &middot; <i class="fas fa-envelope"></i> {{ $email }}
                            </div>
                        </div>
                        <div class="selected-type-badge naming">Naming Ceremony</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label>Address for Naming Ceremony <span class="text-danger">*</span></label>
                            <textarea name="naming_address" class="form-control" required></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label>Nearest Landmarks <span class="text-danger">*</span></label>
                            <input type="text" name="naming_landmarks" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label>Child's Gender <span class="text-danger">*</span></label>
                            <select name="child_gender" class="form-control" required>
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Twins (Same Sex)">Twins (Same Sex)</option>
                                <option value="Twins (Opposite Sex)">Twins (Opposite Sex)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label>Child's Position <span class="text-danger">*</span></label>
                            <input type="text" name="child_position" class="form-control" placeholder="e.g. 1st, 2nd, 3rd" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label>Date of Delivery <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_delivery" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label>Proposed Naming Date <span class="text-danger">*</span></label>
                            <input type="date" name="proposed_naming_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label>Proposed Naming Time <span class="text-danger">*</span></label>
                            <input type="time" name="proposed_naming_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-3">
                            <label>Proposed Child's Names <span class="text-danger">*</span></label>
                            <input type="text" name="proposed_child_names" class="form-control" placeholder="e.g. David Oluwaseun" required>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold text-muted mb-3" style="font-size:0.85rem;">ADDITIONAL INFORMATION (OPTIONAL)</h6>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group mb-3">
                            <label>Parent's Background (if not fully TCN members)</label>
                            <textarea name="parent_background" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DEDICATION FORM --}}
        <div class="ms-panel form-section" id="dedicationForm">
            <div class="ms-panel-header">
                <h6><i class="fas fa-praying-hands"></i> Child Dedication Details</h6>
            </div>
            <div class="ms-panel-body">

                <div class="member-summary">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="name">{{ $fname }} {{ $lname }}</div>
                            <div class="detail">
                                <i class="fas fa-phone-alt"></i> {{ $phone }}
                                &middot; <i class="fas fa-envelope"></i> {{ $email }}
                            </div>
                        </div>
                        <div class="selected-type-badge dedication">Child Dedication</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label>Father's Name <span class="text-danger">*</span></label>
                            <input type="text" name="father_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label>Mother's Name <span class="text-danger">*</span></label>
                            <input type="text" name="mother_name" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label>Child's Name <span class="text-danger">*</span></label>
                            <input type="text" name="dedication_child_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label>Dedication Date <span class="text-danger">*</span></label>
                            <input type="date" name="dedication_date" class="form-control" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit --}}
        <div class="text-center mt-4">
            <button type="submit" class="btn-submit-ceremony" id="submitBtn">
                <i class="fas fa-paper-plane"></i> Submit Request
            </button>
        </div>
    </form>
</div>

{{-- Success Toast --}}
<div class="toast-notification" id="toastContainer" style="display:none;">
    <div class="alert alert-success alert-dismissible fade show shadow" role="alert" style="border-radius:12px;">
        <strong id="toastTitle">🎉 Success!</strong><br>
        <span id="toastMessage"></span>
        <button type="button" class="close" onclick="document.getElementById('toastContainer').style.display='none'">
            <span>&times;</span>
        </button>
    </div>
</div>

<script>
function selectType(el, type) {
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('ceremonyType').value = type;
    document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
    document.getElementById(type + 'Form').classList.add('active');
}

// Wait for jQuery to load (it's loaded at the bottom of layout)
function initCeremonyForm() {
    if (typeof jQuery === 'undefined') {
        setTimeout(initCeremonyForm, 100);
        return;
    }
    
    const $ = jQuery;
    
    $('#ceremonyForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#submitBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');
        $.ajax({
            url: '{{ route("child-ceremony.store") }}',
            type: 'POST',
            data: form.serialize(),
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    showToast('🎉 Success!', resp.message);
                    form[0].reset();
                    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
                    document.querySelector('.type-btn[data-type="naming"]').classList.add('active');
                    document.getElementById('ceremonyType').value = 'naming';
                    document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
                    document.getElementById('namingForm').classList.add('active');
                } else {
                    alert('Error: ' + resp.message);
                }
            },
            error: function(xhr) {
                let msg = 'An error occurred. Please try again.';
                if (xhr.responseJSON?.message) msg = xhr.responseJSON.message;
                else if (xhr.responseJSON?.errors) {
                    msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
                }
                alert('Error: ' + msg);
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Request');
            }
        });
    });

    function showToast(title, message) {
        const container = $('#toastContainer');
        $('#toastTitle').text(title);
        $('#toastMessage').text(message);
        container.show();
        setTimeout(function() { container.fadeOut(); }, 6000);
    }
}

initCeremonyForm();
</script>
@endsection
