@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card">
            <div class="card-body d-flex align-items-center">
                <label class="mb-0 mr-3 font-weight-bold">Campus:</label>
                <span class="font-weight-normal" style="font-size: 1.1rem;">
                    @php
                        $currentCampus = $campuses->firstWhere('cid', $userCampusId);
                    @endphp
                    {{ $currentCampus ? $currentCampus->cname : 'N/A' }}
                </span>
                <input type="hidden" name="campus_id" value="{{ $userCampusId }}">
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-6 col-md-12">
        <div class="ms-panel ms-panel-fh">
            <div class="ms-panel-header">
                <h6>Select Week / Question No / Enter Question</h6>
            </div>
            <div class="ms-panel-body">
                <form class="needs-validation clearfix" novalidate method="POST" action="{{ url('/etask/store') }}">
                    @csrf
                    <input type="hidden" name="campus_id" value="{{ $userCampusId }}">
                    <div class="form-row">
                        <div class="col-xl-6 col-md-12">
                            <label for="week">Week</label>
                            <div class="input-group">
                                <select class="form-control" id="week" name="week" required>
                                    @for($i = 1; $i <= 13; $i++)
                                        <option value="{{ $i }}">{{ $i }}</option>
                                    @endfor
                                </select>
                                <div class="invalid-feedback">Please select a Week.</div>
                            </div>
                        </div>
                        <div class="col-xl-6 col-md-12">
                            <label for="task">Task No.</label>
                            <div class="input-group">
                                <select class="form-control" id="task" name="task" required>
                                    @for($i = 1; $i <= 7; $i++)
                                        <option value="task {{ $i }}">Task {{ $i }}</option>
                                    @endfor
                                </select>
                                <div class="invalid-feedback">Please select a Question.</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label for="department_id">Department</label>
                            <div class="input-group">
                                <select class="form-control" id="department_id" name="department_id" required>
                                    <option value="">-- Select Department --</option>
                                    @foreach($departments as $dept)
                                        <option value="{{ $dept->dept_id }}">{{ $dept->dept_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label for="questiontext">Question Text</label>
                            <div class="input-group">
                                <textarea rows="5" id="questiontext" name="questiontext" class="form-control" placeholder="Question Text" required></textarea>
                                <div class="invalid-feedback">Please provide a question text.</div>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary float-right" type="submit">Submit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    @forelse($deptWeekGroups as $deptName => $pairs)
        <div class="col-md-12 mb-4">
            <div class="ms-panel">
                <div class="ms-panel-header">
                    <h6>{{ $deptName }} - Week Tasks</h6>
                </div>
                <div class="ms-panel-body">
                    <div class="row">
                        @foreach($pairs as $pair)
                            <div class="col-md-3 col-sm-6 col-6 mb-2">
                                <button class="btn btn-primary" data-toggle="modal" data-target="#modal-{{ $pair->key }}">Week {{ $pair->wid }}</button>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-md-12">
            <p class="text-muted">No weeks have tasks for the selected campus. Use the form above to add tasks.</p>
        </div>
    @endforelse
</div>

<!-- Modals (unique IDs) -->
@foreach($taskGroups as $key => $group)
    <div class="modal fade" id="modal-{{ $key }}" tabindex="-1" role="dialog" aria-labelledby="modal-{{ $key }}">
        <div class="modal-dialog modal-dialog-centered modal-max" role="document">
            <div class="modal-content">
                <div class="modal-body">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h1>Questions for Week {{ $group['wid'] }} - {{ $group['dept_name'] }}</h1>
                    <ul class="ms-list">
                        @php $question_number = 1; @endphp
                        @foreach($group['questions'] as $question => $details)
                            <li class="ms-list-item media">
                                <div class="media-body">
                                    <p>{!! $question_number !!}. {!! $details['text'] ?? '' !!}
                                        <a href="{{ url('/etask/delete?week=' . $group['wid'] . '&question=' . $question . '&campus_id=' . $userCampusId . '&department_id=' . $group['dept_id']) }}" style="float:right;">
                                            <i class="fas fa-trash-alt" style="font-size: 10px"></i>
                                        </a>
                                    </p>
                                </div>
                            </li>
                            @php $question_number++; @endphp
                        @endforeach
                    </ul>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary shadow-none">Get Started</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
@endsection
