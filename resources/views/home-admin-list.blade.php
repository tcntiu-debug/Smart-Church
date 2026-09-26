@extends('layouts.app')

@section('content')
<style>
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-reviewed { background: #dbeafe; color: #1e40af; }
    .badge-approved { background: #d1fae5; color: #065f46; }
    .status-select { padding: 4px 8px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 13px; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Prayer Requests & Suggestions</h4>
    <span class="badge badge-secondary">{{ $items->total() }} total</span>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<!-- Filter Form -->
<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('home.admin-list') }}" class="form-inline d-flex flex-wrap gap-2 align-items-center" style="gap: 10px;">
            <div class="form-group mb-0">
                <label class="mr-2 font-weight-bold" for="typeFilter">Type:</label>
                <select name="type" id="typeFilter" class="form-control form-control-sm" style="min-width: 140px;">
                    <option value="">All Types</option>
                    <option value="prayer" {{ request('type') === 'prayer' ? 'selected' : '' }}>🙏 Prayer</option>
                    <option value="suggestion" {{ request('type') === 'suggestion' ? 'selected' : '' }}>💡 Suggestion</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="mr-2 font-weight-bold" for="statusFilter">Status:</label>
                <select name="status" id="statusFilter" class="form-control form-control-sm" style="min-width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="reviewed" {{ request('status') === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="mr-2 font-weight-bold" for="dateFrom">From:</label>
                <input type="date" name="date_from" id="dateFrom" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="form-group mb-0">
                <label class="mr-2 font-weight-bold" for="dateTo">To:</label>
                <input type="date" name="date_to" id="dateTo" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="form-group mb-0">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="{{ route('home.admin-list') }}" class="btn btn-sm btn-secondary ml-1">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Submitted By</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                    <tr>
                        <td>{{ $item->id }}</td>
                        <td>
                            @if($item->type === 'prayer')
                                <span class="badge badge-info">🙏 Prayer</span>
                            @else
                                <span class="badge badge-warning">💡 Suggestion</span>
                            @endif
                        </td>
                        <td>
                            @if($item->member)
                                {{ $item->member->first_name }} {{ $item->member->last_name }}
                                <br><small class="text-muted">{{ $item->member->email ?? '' }}</small>
                            @else
                                <em class="text-muted">Anonymous</em>
                            @endif
                        </td>
                        <td style="max-width: 300px;">
                            <div style="white-space: pre-wrap; word-break: break-word;">{{ Str::limit($item->message, 120) }}</div>
                            @if(strlen($item->message) > 120)
                                <a href="javascript:void(0)" class="text-primary small" onclick="alert(this.parentElement.querySelector('.full-msg').textContent)">Read more</a>
                                <div class="full-msg" style="display:none;">{{ $item->message }}</div>
                            @endif
                        </td>
                        <td>
                            <small>{{ $item->created_at->format('M d, Y h:i A') }}</small>
                        </td>
                        <td>
                            @if($item->status === 'pending')
                                <span class="badge badge-pending">Pending</span>
                            @elseif($item->status === 'reviewed')
                                <span class="badge badge-reviewed">Reviewed</span>
                            @else
                                <span class="badge badge-approved">Approved</span>
                            @endif
                        </td>
                        <td>
                            <form action="{{ route('home.update-status', $item->id) }}" method="POST" class="d-flex align-items-center gap-2" style="gap: 6px;">
                                @csrf
                                <select name="status" class="status-select" onchange="this.form.submit()">
                                    <option value="pending" {{ $item->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="reviewed" {{ $item->status === 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                                    <option value="approved" {{ $item->status === 'approved' ? 'selected' : '' }}>Approved</option>
                                </select>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No prayer requests or suggestions yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3 d-flex justify-content-center">
    {{ $items->links() }}
</div>
@endsection
