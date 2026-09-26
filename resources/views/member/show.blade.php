@extends('layouts.app')

@section('content')
<div class="ms-panel">
    <div class="ms-panel-header">
        <div class="d-flex justify-content-between align-items-center">
            <h6>Member Details #{{ $member->tiu_member_id }}: {{ $member->first_name }} {{ $member->last_name }}</h6>
            <a href="{{ route('member.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>
    <div class="ms-panel-body">
        <div class="row">
            <div class="col-md-3 text-center mb-4">
                @if(!empty($member->picture_part))
                    <img src="{{ asset($member->picture_part) }}" class="img-fluid rounded-circle" 
                         style="width:150px;height:150px;object-fit:cover;">
                @else
                    <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center"
                         style="width:150px;height:150px;font-size:48px;">
                        {{ strtoupper(substr($member->first_name, 0, 1)) }}{{ strtoupper(substr($member->last_name, 0, 1)) }}
                    </div>
                @endif
                <h5 class="mt-3">{{ $member->first_name }} {{ $member->last_name }}</h5>
                <span class="badge badge-{{ $member->status == 1 ? 'success' : ($member->status == 0 ? 'warning' : 'danger') }}">
                    {{ $member->status == 1 ? 'Active' : ($member->status == 0 ? 'Inactive' : 'Deleted') }}
                </span>
                <div class="mt-2">
                    <span class="badge badge-info">{{ $member->member_role ?? 'Worker' }}</span>
                </div>
            </div>
            <div class="col-md-9">
                <table class="table table-bordered">
                    <tr><th style="width:200px;">Phone Number</th><td>{{ $member->phone_number }}</td></tr>
                    <tr><th>Email</th><td>{{ $member->email ?? '—' }}</td></tr>
                    <tr><th>Gender</th><td>{{ $member->gender ?? '—' }}</td></tr>
                    <tr><th>Marital Status</th><td>{{ $member->marital_status ?? '—' }}</td></tr>
                    <tr>
                        <th>Occupation</th>
                        <td>{{ $member->occupation ?? '—' }}</td>
                    </tr>
                    <tr><th>Church Service</th><td>{{ $churchType_name ?? '—' }}</td></tr>
                    <tr><th>Campus</th><td>{{ $campus_name ?? '—' }}</td></tr>
                    <tr><th>Sub Group</th><td>{{ $member->subgroup ?? '—' }}</td></tr>
                    <tr>
                        <th>Department(s)</th>
                        <td>
                            @php
                                $deptIds = is_array($member->department_name) ? $member->department_name : (json_decode($member->department_name ?? '[]', true) ?: []);
                                $deptNames = [];
                                if(is_array($deptIds)) {
                                    foreach($deptIds as $did) {
                                        if(isset($departments[$did])) $deptNames[] = $departments[$did];
                                    }
                                }
                            @endphp
                            {{ implode(', ', $deptNames) ?: '—' }}
                        </td>
                    </tr>
                    <tr><th>Address</th><td>{{ $member->residential_address ?? '—' }}</td></tr>
                    <tr><th>Date Registered</th><td>{{ $member->date_registered ? \Carbon\Carbon::parse($member->date_registered)->format('F j, Y') : '—' }}</td></tr>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
