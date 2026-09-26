@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>Church Type Members</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Marital Status</th>
                            <th>Occupation</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Register Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $index => $member)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $member->first_name }} {{ $member->last_name }}</td>
                            <td>
                                <a href="https://wa.me/{{ preg_replace('/^0/', '234', $member->phone_number) }}" target="_blank" class="text-success mr-2">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                {{ $member->phone_number }}
                            </td>
                            <td>{{ $member->gender ?? 'N/A' }}</td>
                            <td>{{ $member->marital_status ?? 'N/A' }}</td>
                            <td>{{ $member->occupation ?? 'N/A' }}</td>
                            <td>{{ $member->member_role ?? 'Member' }}</td>
                            <td>
                                <span class="badge {{ $member->status == 1 ? 'badge-success' : 'badge-danger' }}">
                                    {{ $member->status == 1 ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($member->date_registered)->format('d M Y') }}</td>
                        </tr>
                        @empty
                            <tr><td colspan="9" class="text-center">
                                @if(count($leadChurchTypeIds) > 0)
                                    No members found in your assigned Church Type(s).
                                @else
                                    You are not assigned as a lead for any Church Type.
                                @endif
                            </td≯
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection