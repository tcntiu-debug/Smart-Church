@extends('layouts.app')

@section('content')
<style>
    :root {
        --primary-blue: #3e52a3;
        --accent-orange: #f97316;
        --accent-green: #10b981;
        --accent-red: #ef4444;
        --text-dark: #1e293b;
        --text-muted: #64748b;
        --bg-light: #f4f6fa;
        --border-color: #e2e8f0;
    }

    body {
        background-color: var(--bg-light);
    }

    .children-header {
        margin-bottom: 1.5rem;
    }

    .children-header h3 {
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .search-card {
        background: white;
        border-radius: 16px;
        border: 2px solid var(--border-color);
        padding: 20px 24px;
        margin-bottom: 1.5rem;
        transition: border-color 0.2s;
    }

    .search-card:focus-within {
        border-color: var(--primary-blue);
    }

    .search-card .form-control {
        border: none;
        font-size: 1.1rem;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 12px;
        font-weight: 500;
    }

    .search-card .form-control:focus {
        box-shadow: 0 0 0 3px rgba(62, 82, 163, 0.15);
        background: white;
    }

    .search-stats {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
    }

    /* Child Result Card */
    .child-card {
        background: white;
        border-radius: 14px;
        border: 1px solid var(--border-color);
        padding: 16px 20px;
        margin-bottom: 10px;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .child-card:hover {
        border-color: var(--primary-blue);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }

    .child-card .child-info {
        flex: 1;
        min-width: 0;
    }

    .child-card .child-name {
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .child-card .child-details {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 2px;
    }

    .child-card .child-details span {
        display: inline-block;
        margin-right: 15px;
    }

    .child-card .child-details i {
        margin-right: 4px;
        font-size: 0.75rem;
    }

    .badge-marked {
        background: #d1fae5;
        color: #065f46;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 4px 12px;
        border-radius: 20px;
    }

    .btn-mark {
        background: var(--accent-orange);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.2s;
        white-space: nowrap;
    }

    .btn-mark:hover {
        background: #ea580c;
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
    }

    .btn-mark:disabled {
        background: #cbd5e1;
        color: #94a3b8;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-mark.marked {
        background: var(--accent-green);
    }

    .btn-mark.marked:hover {
        background: #059669;
    }

    .btn-add-child {
        background: var(--primary-blue);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .btn-add-child:hover {
        background: #2d3f8a;
        color: white;
    }

    .no-results-card {
        background: white;
        border: 2px dashed #cbd5e1;
        border-radius: 16px;
        padding: 30px;
        text-align: center;
    }

    .no-results-card .icon-big {
        font-size: 3rem;
        color: #94a3b8;
        margin-bottom: 10px;
    }

    /* Modal styles */
    .modal-content-custom {
        border-radius: 16px;
        border: none;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
    }

    .modal-header-custom {
        background: #eab308;
        color: white;
        border-radius: 16px 16px 0 0;
        padding: 18px 24px;
        border: none;
    }

    .modal-header-custom .close {
        color: white;
        opacity: 0.8;
    }

    .modal-body-custom {
        padding: 24px;
    }

    .modal-body-custom .form-group label {
        font-weight: 700;
        font-size: 0.8rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    }

    .modal-body-custom .form-control {
        border-radius: 10px;
        border: 1px solid var(--border-color);
        padding: 10px 14px;
        font-weight: 500;
    }

    .modal-footer-custom {
        border-top: 1px solid var(--border-color);
        padding: 16px 24px;
    }

    .btn-submit-add {
        background: var(--primary-blue);
        color: white;
        border: none;
        padding: 10px 30px;
        border-radius: 10px;
        font-weight: 700;
    }

    /* Loading spinner */
    .search-spinner {
        display: none;
        text-align: center;
        padding: 20px;
    }

    .search-spinner.active {
        display: block;
    }

    /* Flash notification */
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
    }

    .attendance-counter {
        background: white;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 12px 20px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .attendance-counter .count {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--accent-green);
    }

    .attendance-counter .label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* ---- Dark mode twins (see docs/DISPLAY-MODE.md) ----------------------
       The layout only repaints Bootstrap surfaces (.card / .ms-panel) and
       style.css forces every heading/paragraph/span to #fff, so this page's
       own light rules (white search/child/empty cards on a #f4f6fa body) need
       a `.ms-dark-theme` counterpart - otherwise the text is white on white.
       Palette: surface #252851, deeper #323a67, border #242750, muted #b9bcd8. */
    body.ms-dark-theme {
        background-color: #292e5a;
    }
    .ms-dark-theme .search-card,
    .ms-dark-theme .child-card,
    .ms-dark-theme .no-results-card,
    .ms-dark-theme .attendance-counter {
        background: #252851;
        border-color: #242750;
        box-shadow: none;
    }
    .ms-dark-theme .child-card .child-name {
        color: #e7e8f5;
    }
    .ms-dark-theme .child-card .child-details,
    .ms-dark-theme .child-card .child-details span {
        color: #b9bcd8;
    }
    .ms-dark-theme .search-stats,
    .ms-dark-theme .attendance-counter .label {
        color: #b9bcd8;
    }
    .ms-dark-theme .attendance-counter .count {
        color: #10b981;
    }
    .ms-dark-theme span.badge-marked {
        background: #1f3b2a;
        color: #b7f0c5;
    }
    .ms-dark-theme .btn-mark:disabled {
        background: #323a67;
        color: #b9bcd8;
    }
    .ms-dark-theme .no-results-card .icon-big {
        color: #5c6dc0;
    }
    .ms-dark-theme .modal-body-custom .form-group label {
        color: #b9bcd8;
    }
    .ms-dark-theme .modal-body-custom .form-control,
    .ms-dark-theme .modal-footer-custom {
        border-color: #242750;
    }
    /* Bootstrap utilities carry `!important`, so their twins need it too. */
    .ms-dark-theme .text-dark {
        color: #ffffff !important;
    }
    .ms-dark-theme .text-muted {
        color: #b9bcd8 !important;
    }
</style>

<div class="container py-4">
    <!-- Header -->
    <div class="children-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h3 class="text-dark">👶 Children Church</h3>
            <p class="text-muted small fw-bold mb-0">CHECK-IN · TODAY {{ now()->format('l, F j, Y') }}</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="attendance-counter shadow-sm">
                <span class="label">Checked In Today</span>
                <span class="count" id="todayCount">0</span>
            </div>
            <button class="btn-add-child shadow-sm" onclick="showAddModal()">
                + Add New Child
            </button>
        </div>
    </div>

    <!-- Search Box -->
    <div class="search-card shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-search text-muted" style="font-size: 1.2rem;"></i>
            <input type="text" id="searchInput" class="form-control" 
                   placeholder="Search by child's name or parent/guardian name..." autocomplete="off" autofocus>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="clearSearchBtn" style="display:none;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <small class="search-stats" id="searchStats">Type at least 2 characters to search</small>
            <small class="search-stats" id="resultsCount"></small>
        </div>
    </div>

    <!-- Search Spinner -->
    <div class="search-spinner" id="searchSpinner">
        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
        <p class="text-muted mt-2">Searching...</p>
    </div>

    <!-- Results Container -->
    <div id="resultsContainer">
        <!-- Results will be dynamically inserted here -->
    </div>

    <!-- No Results / Add New -->
    <div id="noResultsMessage" style="display:none;">
        <div class="no-results-card shadow-sm">
            <div class="icon-big">🔍</div>
            <h5 class="fw-bold text-dark">No Children Found</h5>
            <p class="text-muted mb-3">
                No child found with that name in your campus.<br>
                Click the button below to add a new child record.
            </p>
            <button class="btn-add-child shadow-sm" onclick="showAddModal()">
                <i class="fas fa-plus-circle"></i> Add New Child
            </button>
        </div>
    </div>

    <!-- Empty state (before search) -->
    <div id="emptyState">
        <div class="no-results-card shadow-sm">
            <div class="icon-big">👋</div>
            <h5 class="fw-bold text-dark">children check-in</h5>
            <p class="text-muted">
                Start typing a child's name or parent's name above to search.<br>
                If the child is not registered, you can add them with the "+ Add New Child" button.
            </p>
        </div>
    </div>
</div>

<!-- Add Child Modal -->
<div class="modal fade" id="addChildModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-custom">
            <div class="modal-header-custom">
                <h5 class="modal-title fw-bold"><i class="fas fa-child"></i> Register New Child</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="addChildForm">
                @csrf
                <div class="modal-body-custom">
                    <div class="form-group mb-3">
                        <label>Child's Name <span class="text-danger">*</span></label>
                        <input type="text" name="child_name" class="form-control" 
                               placeholder="Enter child's full name" required>
                    </div>
                    <div class="form-group mb-3">
                        <label>Parent/Guardian Name</label>
                        <input type="text" name="parent_name" class="form-control" 
                               placeholder="Enter parent or guardian name">
                    </div>
                    <div class="form-group mb-3">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="form-group mb-0">
                        <label>Parent/Guardian Phone Number</label>
                        <input type="text" name="parent_phone" class="form-control" 
                               placeholder="e.g. 08012345678">
                    </div>
                    <div class="alert alert-danger mt-3" id="addFormError" style="display:none;"></div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit-add" id="addChildSubmitBtn">
                        <i class="fas fa-save"></i> Register Child
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div class="notification-toast" id="toastContainer" style="display:none;">
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong id="toastTitle">Success!</strong> <span id="toastMessage"></span>
        <button type="button" class="close" onclick="document.getElementById('toastContainer').style.display='none'">
            <span>&times;</span>
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    let searchTimeout = null;
    let currentQuery = '';

    // Real-time search on keyup
    $('#searchInput').on('keyup', function() {
        const query = $(this).val().trim();
        currentQuery = query;
        
        if (query.length < 2) {
            $('#resultsContainer').html('');
            $('#noResultsMessage').hide();
            $('#emptyState').show();
            $('#searchStats').text('Type at least 2 characters to search');
            $('#resultsCount').text('');
            $('#clearSearchBtn').hide();
            return;
        }

        $('#clearSearchBtn').show();
        $('#emptyState').hide();
        $('#searchSpinner').addClass('active');
        $('#searchStats').text('Searching...');

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300); // 300ms debounce
    });

    // Clear search
    $('#clearSearchBtn').on('click', function() {
        $('#searchInput').val('').trigger('keyup').focus();
    });

    // Mark attendance
    $(document).on('click', '.btn-mark:not(.marked):not(:disabled)', function() {
        const btn = $(this);
        const childId = btn.data('child-id');
        const childName = btn.data('child-name');

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: '{{ route("children-church.mark-attendance") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                child_id: childId
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    btn.removeClass('btn-mark').addClass('btn-mark marked');
                    btn.html('<i class="fas fa-check-circle"></i> Tag Issued');
                    btn.prop('disabled', true);
                    
                    showToast('Success!', resp.message);
                    updateTodayCount();
                } else {
                    showToast('Notice', resp.message, 'warning');
                    btn.prop('disabled', false).html('Mark Present');
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Server error. Please try again.';
                showToast('Error', msg, 'danger');
                btn.prop('disabled', false).html('Mark Present');
            }
        });
    });

    // Add child form submit
    $('#addChildForm').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const btn = $('#addChildSubmitBtn');
        const formData = form.serialize();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        $('#addFormError').hide();

        $.ajax({
            url: '{{ route("children-church.store") }}',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    // Close modal
                    $('#addChildModal').modal('hide');
                    form[0].reset();
                    
                    // Show success
                    showToast('Success!', resp.message);
                    
                    // Auto-mark attendance for the new child
                    if (resp.data) {
                        autoMarkNewChild(resp.data);
                    }
                    
                    updateTodayCount();
                } else {
                    $('#addFormError').text(resp.message).show();
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Error saving child record.';
                $('#addFormError').text(msg).show();
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-save"></i> Register Child');
            }
        });
    });

    // Auto-mark attendance for newly added child
    function autoMarkNewChild(childData) {
        $.ajax({
            url: '{{ route("children-church.mark-attendance") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                child_id: childData.child_id
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    // If we have a current search active that would show this child, refresh
                    if (currentQuery && currentQuery.length >= 2) {
                        performSearch(currentQuery);
                    }
                }
            }
        });
    }

    // Perform search AJAX
    function performSearch(query) {
        $.ajax({
            url: '{{ route("children-church.search") }}',
            type: 'GET',
            data: { q: query },
            dataType: 'json',
            success: function(resp) {
                $('#searchSpinner').removeClass('active');
                
                if (resp.count > 0) {
                    let html = '';
                    resp.data.forEach(function(child) {
                        const markedClass = child.already_marked ? 'marked' : '';
                        const markedText = child.already_marked ? 
                            '<i class="fas fa-check-circle"></i> Tag Issued' : 
                            '<i class="fas fa-check"></i> Mark Present';
                        const disabled = child.already_marked ? 'disabled' : '';
                        
                        html += `
                        <div class="child-card shadow-sm">
                            <div class="child-info">
                                <div class="child-name">
                                    ${child.child_name}
                                    ${child.already_marked ? '<span class="badge-marked"><i class="fas fa-check"></i> Checked In</span>' : ''}
                                </div>
                                <div class="child-details">
                                    ${child.parent_name ? '<span><i class="fas fa-user"></i> ' + child.parent_name + '</span>' : ''}
                                    ${child.dob ? '<span><i class="fas fa-birthday-cake"></i> ' + child.dob + '</span>' : ''}
                                    ${child.parent_phone ? '<span><i class="fas fa-phone"></i> ' + child.parent_phone + '</span>' : ''}
                                </div>
                            </div>
                            <div>
                                <button class="btn-mark ${markedClass}" 
                                        data-child-id="${child.child_id}" 
                                        data-child-name="${child.child_name}"
                                        ${disabled}>
                                    ${markedText}
                                </button>
                            </div>
                        </div>`;
                    });
                    
                    $('#resultsContainer').html(html);
                    $('#noResultsMessage').hide();
                    $('#emptyState').hide();
                    $('#searchStats').text('Showing results for "' + currentQuery + '"');
                    $('#resultsCount').text(resp.count + ' record' + (resp.count > 1 ? 's' : '') + ' found');
                } else {
                    $('#resultsContainer').html('');
                    $('#noResultsMessage').show();
                    $('#emptyState').hide();
                    $('#searchStats').text('No results found for "' + currentQuery + '"');
                    $('#resultsCount').text('');
                }
            },
            error: function() {
                $('#searchSpinner').removeClass('active');
                $('#searchStats').text('Search failed. Please try again.');
            }
        });
    }

    // Show add modal
    window.showAddModal = function() {
        $('#addChildModal').modal('show');
        $('#addFormError').hide();
        $('#addChildForm')[0].reset();
    };

    // Show toast notification
    function showToast(title, message, type) {
        type = type || 'success';
        const container = $('#toastContainer');
        const alert = container.find('.alert');
        
        alert.removeClass('alert-success alert-danger alert-warning');
        alert.addClass('alert-' + type);
        
        $('#toastTitle').text(title);
        $('#toastMessage').text(message);
        
        container.show();
        
        // Auto hide after 5 seconds
        clearTimeout(window.toastTimeout);
        window.toastTimeout = setTimeout(function() {
            container.fadeOut();
        }, 5000);
    }

    // Update today's attendance count
    function updateTodayCount() {
        $.ajax({
            url: '{{ route("children-church.today-attendance") }}',
            type: 'GET',
            dataType: 'json',
            success: function(resp) {
                $('#todayCount').text(resp.count || 0);
            }
        });
    }

    // Initial load of today's count
    updateTodayCount();

    // Refresh count every 30 seconds
    setInterval(updateTodayCount, 30000);

    // Focus search input on page load
    $('#searchInput').focus();
});
</script>
@endpush
