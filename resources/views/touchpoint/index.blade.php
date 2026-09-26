@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12">
         <nav aria-label="breadcrumb">
            <ol class="breadcrumb pl-0">
               <li class="breadcrumb-item"><a href="{{ url('/mytask') }}"><i class="material-icons">home</i> Home</a></li>
               <li class="breadcrumb-item active" aria-current="page">My Groups</li>
            </ol>
         </nav>
    </div>
    <div class="col-md-12">
        <div class="ms-panel">
            <div class="ms-panel-body">
                <div class="row">

                    <!-- ================================== -->
                    <!-- === SUB GROUP PANELS === -->
                    <!-- ================================== -->
                    <div class="col-lg-12">
                        <div class="ms-panel-header">
                            <h6>My Sub Groups</h6>
                        </div>
                        @forelse($myGroups as $group)
                            <div class="ms-panel ms-panel-fh" style="margin-bottom: 20px;">
                                <div class="ms-panel-header"><h6 class="ms-panel-title">{{ e($group['group_name']) }}</h6></div>
                                <div class="ms-panel-body">
                                    <ul class="ms-list">
                                        @php $i = 1; @endphp
                                        @foreach($group['members'] as $member)
                                            @php
                                                $displayName = \App\Http\Controllers\TouchpointController::renderMemberName($member->first_name, $member->last_name);
                                                $role = ($member->tiu_member_id == $group['lead_id']) ? 'Lead' : 'Member';
                                            @endphp
                                            <li class="ms-list-item">
                                                <div class="media-body d-flex justify-content-between align-items-center">
                                                    <span>{!! $i++ . '. ' . $displayName . ' (' . e($member->phone_number) . ')' !!}</span>
                                                    <span class="badge badge-{{ $role == 'Lead' ? 'success' : 'light' }}">{{ $role }}</span>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @empty
                            <p>You are not assigned to any subgroup yet.</p>
                        @endforelse
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
