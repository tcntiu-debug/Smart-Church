@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <!-- Department Members -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">Department Members</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="department-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Marital Status</th>
                            <th>Occupation</th>
                            <th>Address</th>
                            <th>Community Name</th>
                            <th>Departments</th>
                            <th>House Fellowship</th>
                            <th>Clusters</th>
                            <th>Church Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departmentMembers as $index => $member)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $member->first_name }}</td>
                            <td>{{ $member->last_name }}</td>
                            <td>
                                <a href="https://wa.me/{{ $member->whatsapp_phone }}" target="_blank" class="text-success mr-2">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                {{ $member->phone_number }}
                            </td>
                            <td>{{ $member->gender ?? 'N/A' }}</td>
                            <td>{{ $member->age ?? 'N/A' }}</td>
                            <td>{{ $member->marital_status ?? 'N/A' }}</td>
                            <td>{{ $member->occupation ?? 'N/A' }}</td>
                            <td>{{ $member->residential_address ?? 'N/A' }}</td>
                            <td>{{ $member->community_name }}</td>
                            <td>{{ $member->department_names }}</td>
                            <td>{{ $member->hf_names }}</td>
                            <td>{{ $member->cluster_names }}</td>
                            <td>{{ $member->church_type_name }}</td>
                            <td>{{ $member->status == 1 ? 'Active' : 'Inactive' }}</td>
                        </tr>
                        @empty
                        <!-- No rows - message will show below table -->
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(count($departmentMembers) == 0)
                <div class="alert alert-info text-center mt-3">You are not a lead for any department or no members found.</div>
            @endif
        </div>
    </div>

    <!-- House Fellowship Members -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white">
            <h6 class="mb-0">House Fellowship Members</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="hf-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Marital Status</th>
                            <th>Occupation</th>
                            <th>Address</th>
                            <th>Community Name</th>
                            <th>Departments</th>
                            <th>House Fellowship</th>
                            <th>Clusters</th>
                            <th>Church Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($hfMembers as $index => $member)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $member->first_name }}</td>
                            <td>{{ $member->last_name }}</td>
                            <td>
                                <a href="https://wa.me/{{ $member->whatsapp_phone }}" target="_blank" class="text-success mr-2">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                {{ $member->phone_number }}
                            </td>
                            <td>{{ $member->gender ?? 'N/A' }}</td>
                            <td>{{ $member->age ?? 'N/A' }}</td>
                            <td>{{ $member->marital_status ?? 'N/A' }}</td>
                            <td>{{ $member->occupation ?? 'N/A' }}</td>
                            <td>{{ $member->residential_address ?? 'N/A' }}</td>
                            <td>{{ $member->community_name }}</td>
                            <td>{{ $member->department_names }}</td>
                            <td>{{ $member->hf_names }}</td>
                            <td>{{ $member->cluster_names }}</td>
                            <td>{{ $member->church_type_name }}</td>
                            <td>{{ $member->status == 1 ? 'Active' : 'Inactive' }}</td>
                        </tr>
                        @empty
                        <!-- No rows - message will show below table -->
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(count($hfMembers) == 0)
                <div class="alert alert-info text-center mt-3">No House Fellowship members found.</div>
            @endif
        </div>
    </div>

    <!-- Cluster Members -->
    <div class="card">
        <div class="card-header bg-warning text-white">
            <h6 class="mb-0">Cluster Members</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="cluster-table">
                    <thead>
                        <tr>
                            <th>S/N</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Phone</th>
                            <th>Gender</th>
                            <th>Age</th>
                            <th>Marital Status</th>
                            <th>Occupation</th>
                            <th>Address</th>
                            <th>Community Name</th>
                            <th>Departments</th>
                            <th>House Fellowship</th>
                            <th>Clusters</th>
                            <th>Church Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clusterMembers as $index => $member)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $member->first_name }}</td>
                            <td>{{ $member->last_name }}</td>
                            <td>
                                <a href="https://wa.me/{{ $member->whatsapp_phone }}" target="_blank" class="text-success mr-2">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                {{ $member->phone_number }}
                            </td>
                            <td>{{ $member->gender ?? 'N/A' }}</td>
                            <td>{{ $member->age ?? 'N/A' }}</td>
                            <td>{{ $member->marital_status ?? 'N/A' }}</td>
                            <td>{{ $member->occupation ?? 'N/A' }}</td>
                            <td>{{ $member->residential_address ?? 'N/A' }}</td>
                            <td>{{ $member->community_name }}</td>
                            <td>{{ $member->department_names }}</td>
                            <td>{{ $member->hf_names }}</td>
                            <td>{{ $member->cluster_names }}</td>
                            <td>{{ $member->church_type_name }}</td>
                            <td>{{ $member->status == 1 ? 'Active' : 'Inactive' }}</td>
                        </tr>
                        @empty
                        <!-- No rows - message will show below table -->
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(count($clusterMembers) == 0)
                <div class="alert alert-info text-center mt-3">No Cluster members found.</div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize DataTables only if tables have data rows
    if ($('#department-table tbody tr').length > 0) {
        $('#department-table').DataTable({
            responsive: true,
            scrollX: true,
            pageLength: 25
        });
    }
    
    if ($('#hf-table tbody tr').length > 0) {
        $('#hf-table').DataTable({
            responsive: true,
            scrollX: true,
            pageLength: 25
        });
    }
    
    if ($('#cluster-table tbody tr').length > 0) {
        $('#cluster-table').DataTable({
            responsive: true,
            scrollX: true,
            pageLength: 25
        });
    }
});
</script>
@endpush
@endsection