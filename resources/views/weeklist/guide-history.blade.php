@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-history"></i> Guide history (released first timers)</h6>
            <a href="{{ route('weeklist.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to weekly list
            </a>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Every first timer who was released - by the 8-week auto-unassign or by a bulk
                unassign - keeps their follow-up here, so the last guide and the last comment are
                never lost.
            </p>

            <!-- Filters -->
            <form method="GET" action="{{ route('weeklist.guide-history') }}" class="mb-4">
                <div class="row align-items-end">
                    <div class="col-lg-4 col-md-6 mb-3">
                        <label for="search">First timer or guide</label>
                        <input type="text" class="form-control" name="search" id="search"
                               value="{{ $search }}" placeholder="Name contains...">
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <label for="reason">Reason</label>
                        <select class="form-control" name="reason" id="reason">
                            <option value="">All reasons</option>
                            @foreach($reasons as $reasonOption)
                                <option value="{{ $reasonOption }}" {{ $reason === $reasonOption ? 'selected' : '' }}>
                                    {{ $reasonOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <button type="submit" class="btn btn-primary mb-2">Apply</button>
                        <a href="{{ route('weeklist.guide-history') }}" class="btn btn-secondary mb-2">Reset</a>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table id="guide-history-table" class="table table-hover">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>First timer</th>
                            <th>Phone</th>
                            <th>Last guide</th>
                            <th>Released</th>
                            <th>Reason</th>
                            <th>Last task</th>
                            <th>Last comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $index => $row)
                            <tr>
                                <td>{{ $records->firstItem() + $index }}</td>
                                <td>{{ $row->ft_name !== '' ? $row->ft_name : 'First timer #' . $row->first_timer_id }}</td>
                                <td>{{ $row->ft_phone ?? '' }}</td>
                                <td>
                                    @if($row->guide_name !== '')
                                        {{ $row->guide_name }}
                                    @else
                                        <span class="text-muted">Unknown / removed member</span>
                                    @endif
                                </td>
                                <td>{{ $row->archived_at ? \Illuminate\Support\Carbon::parse($row->archived_at)->format('M d, Y H:i') : '' }}</td>
                                <td>{{ $row->archive_reason ?? '' }}</td>
                                <td class="small">{{ $row->last_task ?? '' }}</td>
                                <td class="small">
                                    @if($row->last_comment)
                                        {{ $row->last_comment }}
                                        <div class="text-muted">
                                            {{ $row->last_outcome ?? '' }}
                                            @if($row->last_logged_at)
                                                - {{ $row->last_logged_at }}
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">No comment recorded</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">
                                    Nothing has been released yet, so there is no history to show.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $records->appends(request()->query())->links() }}
        </div>
    </div>
</div>
@endsection
