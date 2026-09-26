@extends('layouts.app')

@section('content')
<style>
    @media (max-width: 767px) {
        #ceremonies-table thead { display: none; }
        #ceremonies-table, #ceremonies-table tbody, #ceremonies-table tr, #ceremonies-table td {
            display: block; width: 100%;
        }
        #ceremonies-table tr {
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        #ceremonies-table td {
            text-align: right; padding-left: 50%;
            position: relative; border-bottom: 1px solid #eee;
            padding-top: 12px; padding-bottom: 12px;
        }
        #ceremonies-table td:last-child { border-bottom: 0; }
        #ceremonies-table td::before {
            content: attr(data-label); position: absolute; left: 15px;
            width: 45%; padding-right: 10px; white-space: nowrap;
            text-align: left; font-weight: bold; color: #333;
        }
    }

    .stat-card {
        border-radius: 12px;
        padding: 16px 20px;
        color: white;
        font-weight: 700;
    }
    .stat-card .num { font-size: 1.8rem; font-weight: 800; }
    .stat-card .lbl { font-size: 0.75rem; opacity: 0.9; text-transform: uppercase; }

    .filter-form .form-control, .filter-form select { border-radius: 8px; }

    .detail-label { font-weight: 700; color: #475569; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .detail-value { font-weight: 500; color: #1e293b; padding: 4px 0 12px 0; border-bottom: 1px solid #f1f5f9; margin-bottom: 10px; }
    .detail-card { background: #f8fafc; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
</style>

<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center flex-wrap">
        <h6>CHILD CEREMONY REQUESTS</h6>
        <a href="{{ route('child-ceremony.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-plus"></i> New Request
        </a>
    </div>

    {{-- Stats --}}
    <div class="ms-panel-body pb-0">
        <div class="row mb-3">
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-card" style="background:#3e52a3;">
                    <div class="num">{{ $namingCount }}</div>
                    <div class="lbl">Naming Ceremonies</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-card" style="background:#be185d;">
                    <div class="num">{{ $dedicationCount }}</div>
                    <div class="lbl">Child Dedications</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-card" style="background:#f59e0b;">
                    <div class="num">{{ $pendingCount }}</div>
                    <div class="lbl">Pending</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="stat-card" style="background:#10b981;">
                    <div class="num">{{ $completedCount }}</div>
                    <div class="lbl">Completed</div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('child-ceremony.admin') }}" class="filter-form mb-4">
            <div class="form-row align-items-end">
                <div class="col-md-3 mb-2">
                    <label for="type" class="small fw-bold">Ceremony Type</label>
                    <select name="type" id="type" class="form-control">
                        <option value="all" {{ request('type') == 'all' || !request('type') ? 'selected' : '' }}>All Types</option>
                        <option value="naming" {{ request('type') == 'naming' ? 'selected' : '' }}>Naming</option>
                        <option value="dedication" {{ request('type') == 'dedication' ? 'selected' : '' }}>Dedication</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="status" class="small fw-bold">Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="all" {{ request('status') == 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="search" class="small fw-bold">Search</label>
                    <input type="text" name="search" id="search" class="form-control" 
                           placeholder="Child or member name..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn btn-primary btn-block">Filter</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Table --}}
    <div class="ms-panel-body">
        <div class="table-responsive">
            <table id="ceremonies-table" class="table table-hover thead-primary w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th>Child Name(s)</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th style="min-width:130px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ceremonies as $c)
                    @php
                        $memberName = $c->member ? $c->member->first_name . ' ' . ($c->member->last_name ?? '') : 'N/A';
                        $memberPhone = $c->member->phone_number ?? '—';
                        $childName = $c->ceremony_type === 'naming' 
                            ? ($c->proposed_child_names ?? '—') 
                            : ($c->dedication_child_name ?? '—');
                        $ceremonyDate = $c->ceremony_type === 'naming'
                            ? ($c->proposed_naming_date ?? '—')
                            : ($c->dedication_date ?? '—');
                        $isCompleted = $c->status === 'completed';
                        $statusBadge = $isCompleted 
                            ? '<span class="badge badge-success">Completed</span>' 
                            : '<span class="badge badge-warning">Pending</span>';
                    @endphp
                    <tr>
                        <td data-label="#">{{ $loop->iteration + ($ceremonies->currentPage() - 1) * $ceremonies->perPage() }}</td>
                        <td data-label="Member">{{ htmlspecialchars($memberName) }}</td>
                        <td data-label="Phone">{{ htmlspecialchars($memberPhone) }}</td>
                        <td data-label="Type">
                            <span class="badge {{ $c->ceremony_type === 'naming' ? 'badge-primary' : 'badge-danger' }}">
                                {{ ucfirst($c->ceremony_type) }}
                            </span>
                        </td>
                        <td data-label="Child Name(s)">{{ htmlspecialchars($childName) }}</td>
                        <td data-label="Date">{{ $ceremonyDate }}</td>
                        <td data-label="Status">{!! $statusBadge !!}</td>
                        <td data-label="Submitted">{{ $c->created_at->format('M d, Y') }}</td>
                        <td data-label="Action">
                            <div class="d-flex gap-1">
                                <button class="btn btn-sm btn-info text-white" onclick="viewDetails({{ $c->id }})" title="View Full Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                @if($isCompleted)
                                    <button class="btn btn-sm btn-warning" onclick="markPending({{ $c->id }})" title="Re-open">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-success" onclick="markDone({{ $c->id }})" title="Mark Done">
                                        <i class="fas fa-check"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No ceremony requests found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="d-flex justify-content-center mt-3">
            {{ $ceremonies->links() }}
        </div>
    </div>
</div>

{{-- DETAIL MODAL --}}
<div class="modal fade" id="detailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:14px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="detailModalTitle">Full Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <div class="text-center py-4 text-muted">Loading...</div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for jQuery
function initAdminPage() {
    if (typeof jQuery === 'undefined') {
        setTimeout(initAdminPage, 100);
        return;
    }
    
    const $ = jQuery;

    window.viewDetails = function(id) {
        const modal = $('#detailModal');
        const body = $('#detailModalBody');
        body.html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2 text-muted">Loading details...</p></div>');
        modal.modal('show');
        
        // Fetch existing ceremony data from the table — we have all the data in $ceremonies
        // But for full details, we'll use an AJAX call
        $.ajax({
            url: '/child-ceremony/admin/detail/' + id,
            type: 'GET',
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    renderDetail(resp.data);
                } else {
                    body.html('<div class="alert alert-danger">' + resp.message + '</div>');
                }
            },
            error: function() {
                body.html('<div class="alert alert-danger">Failed to load details. Please try again.</div>');
            }
        });
    };

    function renderDetail(d) {
        const body = $('#detailModalBody');
        const isNaming = d.ceremony_type === 'naming';
        const memberName = d.member ? d.member.first_name + ' ' + (d.member.last_name || '') : 'N/A';
        const memberPhone = d.member ? d.member.phone_number : '—';
        const memberEmail = d.member ? d.member.email : '—';

        let typeBadge = isNaming 
            ? '<span class="badge badge-primary">Naming Ceremony</span>'
            : '<span class="badge badge-danger">Child Dedication</span>';
        let statusBadge = d.status === 'completed'
            ? '<span class="badge badge-success">Completed</span>'
            : '<span class="badge badge-warning">Pending</span>';
        let submittedDate = d.created_at ? new Date(d.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

        let typeFields = '';
        if (isNaming) {
            typeFields = `
                <div class="row">
                    <div class="col-md-6"><div class="detail-label">Address for Naming</div><div class="detail-value">${escHtml(d.naming_address || '—')}</div></div>
                    <div class="col-md-6"><div class="detail-label">Nearest Landmarks</div><div class="detail-value">${escHtml(d.naming_landmarks || '—')}</div></div>
                </div>
                <div class="row">
                    <div class="col-md-4"><div class="detail-label">Child's Gender</div><div class="detail-value">${escHtml(d.child_gender || '—')}</div></div>
                    <div class="col-md-4"><div class="detail-label">Child's Position</div><div class="detail-value">${escHtml(d.child_position || '—')}</div></div>
                    <div class="col-md-4"><div class="detail-label">Date of Delivery</div><div class="detail-value">${escHtml(d.date_of_delivery || '—')}</div></div>
                </div>
                <div class="row">
                    <div class="col-md-4"><div class="detail-label">Proposed Naming Date</div><div class="detail-value">${escHtml(d.proposed_naming_date || '—')}</div></div>
                    <div class="col-md-4"><div class="detail-label">Proposed Naming Time</div><div class="detail-value">${escHtml(d.proposed_naming_time || '—')}</div></div>
                    <div class="col-md-4"><div class="detail-label">Proposed Child Names</div><div class="detail-value">${escHtml(d.proposed_child_names || '—')}</div></div>
                </div>
                ${d.parent_background ? `<div class="row"><div class="col-md-12"><div class="detail-label">Parent's Background</div><div class="detail-value">${escHtml(d.parent_background)}</div></div></div>` : ''}
            `;
        } else {
            typeFields = `
                <div class="row">
                    <div class="col-md-6"><div class="detail-label">Father's Name</div><div class="detail-value">${escHtml(d.father_name || '—')}</div></div>
                    <div class="col-md-6"><div class="detail-label">Mother's Name</div><div class="detail-value">${escHtml(d.mother_name || '—')}</div></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><div class="detail-label">Child's Name</div><div class="detail-value">${escHtml(d.dedication_child_name || '—')}</div></div>
                    <div class="col-md-6"><div class="detail-label">Dedication Date</div><div class="detail-value">${escHtml(d.dedication_date || '—')}</div></div>
                </div>
            `;
        }

        body.html(`
            ${typeBadge} ${statusBadge}
            <hr>
            <div class="detail-card">
                <h6 class="fw-bold mb-3" style="font-size:0.85rem;color:#3e52a3;">MEMBER INFORMATION</h6>
                <div class="row">
                    <div class="col-md-4"><div class="detail-label">Member</div><div class="detail-value">${escHtml(memberName)}</div></div>
                    <div class="col-md-4"><div class="detail-label">Phone</div><div class="detail-value">${escHtml(memberPhone)}</div></div>
                    <div class="col-md-4"><div class="detail-label">Email</div><div class="detail-value">${escHtml(memberEmail)}</div></div>
                </div>
            </div>
            <div class="detail-card">
                <h6 class="fw-bold mb-3" style="font-size:0.85rem;color:#3e52a3;">CEREMONY DETAILS</h6>
                ${typeFields}
            </div>
            <div class="detail-card">
                <h6 class="fw-bold mb-3" style="font-size:0.85rem;color:#3e52a3;">SUBMISSION INFO</h6>
                <div class="row">
                    <div class="col-md-6"><div class="detail-label">Status</div><div class="detail-value">${statusBadge}</div></div>
                    <div class="col-md-6"><div class="detail-label">Submitted On</div><div class="detail-value">${submittedDate}</div></div>
                </div>
            </div>
        `);
    }

    function escHtml(str) {
        if (!str) return '—';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    window.markDone = function(id) {
        if (!confirm('Mark this ceremony as completed?')) return;
        $.ajax({
            url: '/child-ceremony/admin/mark-completed/' + id,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) { alert(resp.message); location.reload(); }
                else { alert('Error: ' + resp.message); }
            },
            error: function() { alert('Server error. Please try again.'); }
        });
    };

    window.markPending = function(id) {
        if (!confirm('Re-open this ceremony as pending?')) return;
        $.ajax({
            url: '/child-ceremony/admin/mark-pending/' + id,
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) { alert(resp.message); location.reload(); }
                else { alert('Error: ' + resp.message); }
            },
            error: function() { alert('Server error. Please try again.'); }
        });
    };
}

initAdminPage();
</script>
@endsection
