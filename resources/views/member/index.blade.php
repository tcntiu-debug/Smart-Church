@extends('layouts.app')

@section('content')
<style>
    /* Legacy-style responsive table for mobile (matches tiu/member-view.php) */
    @media (max-width: 767px) {
        #order-listing thead {
            display: none;
        }

        #order-listing,
        #order-listing tbody,
        #order-listing tr,
        #order-listing td {
            display: block;
            width: 100%;
        }

        #order-listing tr {
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        #order-listing td {
            text-align: right;
            padding-left: 50%;
            position: relative;
            border-bottom: 1px solid #eee;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        #order-listing td:last-child {
            border-bottom: 0;
        }

        #order-listing td::before {
            content: attr(data-label);
            position: absolute;
            left: 15px;
            width: 45%;
            padding-right: 10px;
            white-space: nowrap;
            text-align: left;
            font-weight: bold;
            color: #333;
        }
    }

    .wa-icon {
        color: #25D366;
        transition: transform 0.2s;
    }

    .wa-icon:hover {
        transform: scale(1.2);
    }
</style>

<div class="ms-panel">
    <div class="ms-panel-header d-flex justify-content-between align-items-center flex-wrap">
        <h6>MEMBER'S RECORDS</h6>
        <a href="{{ route('member.register') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Register New Member
        </a>
    </div>
    <div class="ms-panel-body">
        {{-- Status Filter (matches legacy member-view.php) --}}
        <form method="GET" action="{{ route('member.index') }}" class="mb-4">
            <div class="form-row align-items-end">
                <div class="col-md-4 col-sm-12 mb-2">
                    <label for="status_filter">Filter by Status</label>
                    <select name="status_filter" id="status_filter" class="form-control">
                        <option value="All" {{ ($statusFilter ?? 'All') == 'All' ? 'selected' : '' }}>All Members</option>
                        <option value="1" {{ ($statusFilter ?? '') == '1' ? 'selected' : '' }}>Active</option>
                        <option value="2" {{ ($statusFilter ?? '') == '2' ? 'selected' : '' }}>Inactive (Disabled)</option>
                    </select>
                </div>
                <div class="col-md-4 col-sm-12">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('member.register') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table id="order-listing" class="table table-hover thead-primary w-100">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Gender</th>
                        <th>Marital&nbsp;Status</th>
                        <th>Occupation</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Register&nbsp;Date</th>
                        <th>Update</th>
                        <th>View&nbsp;Lead</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($members as $i => $member)
                    @php
                        $fullName = $member->first_name . ' ' . $member->last_name;
                        $isActivated = $member->email_verified ?? 0;
                        // Format phone for WhatsApp (matches legacy logic)
                        $rawPhone = preg_replace('/[^0-9]/', '', $member->phone_number ?? '');
                        if (substr($rawPhone, 0, 1) === '0') {
                            $waPhone = '234' . substr($rawPhone, 1);
                        } else {
                            $waPhone = $rawPhone;
                        }
                        $waMessage = "Compliments of the season. This is the covenant nation Ikorodu smart church app admin. We notice after you registered yesterday, you are yet to activate your account. Please reach out to us if you have any difficulties. Thanks";
                        $waLink = "https://wa.me/" . $waPhone . "?text=" . urlencode($waMessage);
                        $statusText = $member->status == '1' ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
                    @endphp
                    <tr>
                        <td data-label="No.">{{ $loop->iteration }}</td>
                        <td data-label="Name">{{ htmlspecialchars($fullName) }}</td>
                        <td data-label="Phone">
                            {{ htmlspecialchars($member->phone_number) }}
                            @if($isActivated == '0' && !empty($waPhone))
                                <a href="{{ $waLink }}" target="_blank" title="Send Activation Reminder" style="margin-left:5px;">
                                    <i class="fab fa-whatsapp wa-icon"></i>
                                </a>
                            @endif
                        </td>
                        <td data-label="Email">{{ htmlspecialchars($member->email ?? '—') }}</td>
                        <td data-label="Gender">{{ htmlspecialchars($member->gender ?? '—') }}</td>
                        <td data-label="Marital Status">{{ htmlspecialchars($member->marital_status ?? '—') }}</td>
                        <td data-label="Occupation">{{ htmlspecialchars($member->occupation ?? '—') }}</td>
                        <td data-label="Role">{{ htmlspecialchars($member->member_role ?? 'Member') }}</td>
                        <td data-label="Status">{!! $statusText !!}</td>
                        <td data-label="Register Date">{{ $member->date_registered ? \Carbon\Carbon::parse($member->date_registered)->format('M d, Y') : '—' }}</td>
                        <td data-label="Update">
                            <a class="media fs-14 p-2" href="{{ url('/profile?full_name=' . urlencode($fullName) . '&tiu_member_id=' . $member->tiu_member_id) }}">
                                <span><i class='fas fa-paper-plane text-success'></i> Update</span>
                            </a>
                        </td>
                        <td data-label="View Lead">
                            <a class="media fs-14 p-2" href="{{ url('/lead?full_name=' . urlencode($fullName) . '&tiu_member_id=' . $member->tiu_member_id . '&lead_phone=' . urlencode($member->phone_number ?? '')) }}">
                                <span><i class="fa fa-eye" aria-hidden="true"></i> View</span>
                            </a>
                        </td>
                        <td data-label="Delete">
                            @if($currentUserId == 1)
                                <a class="media fs-14 p-2" href="{{ url('/member/delete/' . $member->tiu_member_id) }}" onclick="return confirm('Are you sure you want to permanently delete this member?');">
                                    <span><i class="fas fa-trash-alt text-danger"></i> Delete</span>
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="13" class="text-center py-4 text-muted">No members found.</td>
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
