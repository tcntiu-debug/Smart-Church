@extends('layouts.app')

@section('content')
<style>
    @media (max-width: 767px) {
        #order-listing thead { display: none; }
        #order-listing, #order-listing tbody, #order-listing tr, #order-listing td {
            display: block; width: 100%;
        }
        #order-listing tr {
            margin-bottom: 1rem; border: 1px solid #ddd;
            border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        #order-listing td {
            text-align: right; padding-left: 50%;
            position: relative; border-bottom: 1px solid #eee;
            padding-top: 12px; padding-bottom: 12px;
        }
        #order-listing td:last-child { border-bottom: 0; }
        #order-listing td::before {
            content: attr(data-label); position: absolute;
            left: 15px; width: 45%; padding-right: 10px;
            white-space: nowrap; text-align: left;
            font-weight: bold; color: #333;
        }
    }
    .wa-icon { color: #25D366; transition: transform 0.2s; }
    .wa-icon:hover { transform: scale(1.2); }
</style>

<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center flex-wrap">
        <h6>DEPARTMENT MEMBERS (Lead View)</h6>
    </div>
    <div class="ms-panel-body">
        <div class="table-responsive">
            <table id="order-listing" class="table table-hover thead-primary w-100">
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
                    @forelse($members as $index => $member)
                    <tr>
                        <td data-label="S/N">{{ $index + 1 }}</td>
                        <td data-label="First Name">{{ $member->first_name }}</td>
                        <td data-label="Last Name">{{ $member->last_name }}</td>
                        <td data-label="Phone">
                            <a href="https://wa.me/{{ $member->whatsapp_phone }}" target="_blank" class="text-success mr-2">
                                <i class="fab fa-whatsapp"></i>
                            </a>
                            {{ $member->phone_number }}
                        </td>
                        <td data-label="Gender">{{ $member->gender ?? 'N/A' }}</td>
                        <td data-label="Age">{{ $member->age ?? 'N/A' }}</td>
                        <td data-label="Marital Status">{{ $member->marital_status ?? 'N/A' }}</td>
                        <td data-label="Occupation">{{ $member->occupation ?? 'N/A' }}</td>
                        <td data-label="Address">{{ $member->residential_address ?? 'N/A' }}</td>
                        <td data-label="Community Name">{{ $member->community_name }}</td>
                        <td data-label="Departments">{{ $member->department_names }}</td>
                        <td data-label="House Fellowship">{{ $member->hf_names }}</td>
                        <td data-label="Clusters">{{ $member->cluster_names }}</td>
                        <td data-label="Church Type">{{ $member->church_type_name }}</td>
                        <td data-label="Status">
                            <span class="badge {{ $member->status == 1 ? 'badge-success' : 'badge-danger' }}">
                                {{ $member->status == 1 ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="15" class="text-center py-4 text-muted">You are not a lead for any department or no members found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/datatables.min.js') }}"></script>
<script>
    $(document).ready(function() {
        $('#order-listing').DataTable({
            "aLengthMenu": [[5, 10, 15, -1], [5, 10, 15, "All"]],
            "iDisplayLength": 10,
            "language": { search: "" }
        });
    });
</script>
@endpush
