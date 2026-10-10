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
        <h6>MEMBER'S RECORDS <span class="badge badge-primary" id="member-total-badge">{{ $totalMembers }} record{{ $totalMembers == 1 ? '' : 's' }}</span></h6>
        <a href="{{ route('member.register') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> Register New Member
        </a>
    </div>
    <div class="ms-panel-body">
        {{-- Status Filter (matches legacy member-view.php) --}}
        <form method="GET" action="{{ route('member.index') }}" class="mb-4" id="member-filter-form">
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
                    <a href="{{ route('member.index') }}" class="btn btn-secondary" id="reset-status-filter">Reset</a>
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
                        <th>Department</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Register&nbsp;Date</th>
                        <th>Update</th>
                        <th>View&nbsp;Lead</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/datatables.min.js') }}"></script>
<script>
    $(document).ready(function() {
        // Labels used by the responsive (mobile) CSS: td::before { content: attr(data-label); }
        var columnLabels = ['No.', 'Name', 'Phone', 'Email', 'Gender', 'Marital Status', 'Occupation',
            'Department', 'Role', 'Status', 'Register Date', 'Update', 'View Lead', 'Delete'];

        // Server-side mode: rows, searching, sorting and paging are all handled by
        // MemberController@data, so search covers every member (not only the page on screen).
        var columns = [
            { data: 'no',             orderable: true  },
            { data: 'name',           orderable: true  },
            { data: 'phone',          orderable: true  },
            { data: 'email',          orderable: true  },
            { data: 'gender',         orderable: true  },
            { data: 'marital_status', orderable: true  },
            { data: 'occupation',     orderable: true  },
            { data: 'department',     orderable: false },
            { data: 'role',           orderable: true  },
            { data: 'status',         orderable: true  },
            { data: 'register_date',  orderable: true  },
            { data: 'update',         orderable: false, searchable: false },
            { data: 'lead',           orderable: false, searchable: false },
            { data: 'delete',         orderable: false, searchable: false }
        ];

        columns.forEach(function(column, index) {
            column.createdCell = function(td) {
                td.setAttribute('data-label', columnLabels[index]);
            };
        });

        var table = $('#order-listing').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            ajax: {
                url: "{{ route('member.data') }}",
                data: function(params) {
                    params.status_filter = $('#status_filter').val();
                }
            },
            columns: columns,
            order: [[0, 'desc']],
            "aLengthMenu": [[10, 50, 100, -1], [10, 50, 100, "All"]],
            "iDisplayLength": 10,
            "language": {
                search: "",
                processing: "Loading members...",
                emptyTable: "No members found."
            },
            drawCallback: function(settings) {
                var json = settings.json || {};
                var filtered = parseInt(json.recordsFiltered, 10);
                var total = parseInt(json.recordsTotal, 10);

                if (isNaN(filtered) || isNaN(total)) {
                    return;
                }

                var count = (filtered === total) ? total : filtered + ' of ' + total;
                $('#member-total-badge').text(count + (total === 1 ? ' record' : ' records'));
            }
        });

        // Filter by status without reloading the page (server re-queries with the filter).
        $('#member-filter-form').on('submit', function(e) {
            e.preventDefault();
            table.ajax.reload();
        });

        $('#reset-status-filter').on('click', function(e) {
            e.preventDefault();
            $('#status_filter').val('All');
            table.ajax.reload();
        });
    });
</script>
@endpush
