@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Assignment Management</h5>
            <div>
                <a href="{{ route('admin.assignments.suggest') }}" class="btn btn-sm btn-info text-white">Suggest Assignments</a>
            </div>
        </div>
        <div class="card-body">
            <!-- Error/Success Messages -->
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <ul class="nav nav-tabs" id="assignTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="assign-tab" data-toggle="tab" href="#assign" role="tab">Assign First Timer</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="existing-tab" data-toggle="tab" href="#existing" role="tab">Unassign</a>
                </li>
            </ul>

            <div class="tab-content mt-3">
                <!-- Assign Tab -->
                <div class="tab-pane active" id="assign" role="tabpanel">
                    <div class="row">
                        <!-- Unassigned First Timers -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Unassigned First Timers <span class="badge bg-warning">{{ $unassigned->count() }}</span></h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    @if($unassigned->isEmpty())
                                        <p class="text-muted">All first timers are assigned!</p>
                                    @else
                                        <form method="POST" action="{{ route('admin.assignments.assign') }}">
                                            @csrf
                                            <div class="form-group">
                                                <label>Select First Timer:</label>
                                                <select name="first_timer_id" class="form-control" required>
                                                    <option value="">-- Select --</option>
                                                    @foreach($unassigned as $ft)
                                                        <option value="{{ $ft->first_timer_id }}">
                                                            {{ $ft->first_name }} {{ $ft->last_name }} ({{ $ft->phone_number ?? 'No Phone' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Assign to Guide:</label>
                                                <select name="guide_id" class="form-control" required>
                                                    <option value="">-- Select Guide --</option>
                                                    @foreach($guides as $guide)
                                                        <option value="{{ $guide->tiu_member_id }}">
                                                            {{ $guide->first_name }} {{ $guide->last_name }} 
                                                            @if($guide->phone_number) - {{ $guide->phone_number }} @endif
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Assign</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Guides List -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Available Guides (TIU) <span class="badge bg-success">{{ $guides->count() }}</span></h6>
                                </div>
                                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Phone</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($guides as $guide)
                                                <tr>
                                                    <td>{{ $guide->first_name }} {{ $guide->last_name }}</td>
                                                    <td>{{ $guide->phone_number ?? 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Unassign Tab -->
                <div class="tab-pane" id="existing" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h6>Existing Assignments</h6>
                        </div>
                        <div class="card-body">
                            @if($assignments->isEmpty())
                                <p class="text-muted">No assignments exist yet.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>First Timer</th>
                                                <th>Guide</th>
                                                <th>Assigned Date</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($assignments as $assignment)
                                            <tr>
                                                <td>{{ $assignment->ft_first_name }} {{ $assignment->ft_last_name }}</td>
                                                <td>{{ $assignment->guide_first_name }} {{ $assignment->guide_last_name }}</td>
                                                <td>{{ $assignment->timeStamp_registered ?? 'N/A' }}</td>
                                                <td>
                                                    <form method="POST" action="{{ route('admin.assignments.unassign') }}" 
                                                          onsubmit="return confirm('Unassign this first timer from the guide?');">
                                                        @csrf
                                                        <input type="hidden" name="tracking_id" value="{{ $assignment->tracking_id }}">
                                                        <input type="hidden" name="first_timer_id" value="{{ $assignment->first_timer_id }}">
                                                        <button type="submit" class="btn btn-sm btn-danger">Unassign</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    {{ $assignments->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
