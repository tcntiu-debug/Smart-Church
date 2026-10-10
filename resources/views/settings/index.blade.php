@extends('layouts.app')

@section('content')
<div class="row">
    @if(session('success'))
        <div class="col-md-12">
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        </div>
    @endif
    @if(session('error'))
        <div class="col-md-12">
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        </div>
    @endif

    @if(!empty($update_message))
        <div class="col-md-12">
            <div class="alert alert-success" role="alert">{{ $update_message }}</div>
        </div>
    @endif
    @if(!empty($church_type_message))
        <div class="col-md-12">
            <div class="alert alert-success" role="alert">{{ $church_type_message }}</div>
        </div>
    @endif

    <!-- Display Mode (dark / light) -->
    <div class="col-xl-6 col-md-12">
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Display Mode</h6></div>
            <div class="ms-panel-body">
                <p>Switch the interface between light and dark. The choice is remembered on
                    this device, and your operating system preference is used until you pick one.</p>
                @include('partials.theme-toggle', ['variant' => 'button'])
            </div>
        </div>
    </div>

    <!-- First Timer View Limit -->
    <div class="col-xl-6 col-md-12">
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>First Timer View Limit</h6></div>
            <div class="ms-panel-body">
                <p>Set the default number of past days to display records for on the First Timers page.</p>
                <form method="POST" action="{{ url('/setting') }}">
                    @csrf
                    <div class="form-row">
                        <div class="col-md-8">
                            <label for="data-limit-input">Default days to display</label>
                            <input type="number" class="form-control" id="data-limit-input" name="data_limit" value="{{ $current_limit }}" min="1" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" name="update_view_limit" class="btn btn-primary w-100">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Church Type Management -->
    <div class="col-xl-6 col-md-12">
        <div class="ms-panel">
            <div class="ms-panel-header"><h6>Church Type Management</h6></div>
            <div class="ms-panel-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#add-church-type">Add Church Type</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#edit-church-type">Edit Church Type</a></li>
                </ul>
                <div class="tab-content">
                    <!-- Add Tab -->
                    <div class="tab-pane active" id="add-church-type">
                        <form method="POST" action="{{ url('/setting') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="action" value="add_church_type">
                            <div class="form-row">
                                <div class="col-md-9 mb-3">
                                    <label for="church-type-name">Church Type Name</label>
                                    <input type="text" class="form-control" id="church-type-name" name="church_type_name" placeholder="e.g., Youth Service" required>
                                </div>
                                <div class="col-md-3 d-flex align-items-end mb-3">
                                    <button type="submit" class="btn btn-primary w-100">Add</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Edit Tab -->
                    <div class="tab-pane" id="edit-church-type">
                        <form method="POST" action="{{ url('/setting') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="action" value="edit_church_type">
                            <input type="hidden" id="edit-type-id" name="church_type_id">
                            <div class="form-row">
                                <div class="col-md-4 mb-3">
                                    <label for="select-type-to-edit">Select Type to Edit</label>
                                    <select class="form-control" id="select-type-to-edit">
                                        <option value="">-- Select --</option>
                                        @foreach($all_church_types as $type)
                                            <option value="{{ $type->id }}"
                                                    data-name="{{ $type->church_type_name }}"
                                                    data-lead-id="{{ $type->church_type_lead_id }}">
                                                {{ $type->church_type_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="edit-type-name">New Name</label>
                                    <input type="text" class="form-control" id="edit-type-name" name="edit_church_type_name" required disabled>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="select-type-lead">Assign Lead</label>
                                    <select class="form-control" id="select-type-lead" name="church_type_lead_id" disabled>
                                        <option value="">-- Assign No Lead --</option>
                                        @foreach($all_leads as $lead)
                                            <option value="{{ $lead->tiu_member_id }}">
                                                {{ $lead->first_name }} {{ $lead->last_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="col-md-12">
                                    <button type="submit" id="edit-type-submit" class="btn btn-primary w-100" disabled>Update Church Type</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        // --- Church Type Edit Form Script ---
        $('#select-type-to-edit').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const typeId = selectedOption.val();
            const leadsDropdown = $('#select-type-lead');

            if (typeId) {
                const currentLeadId = selectedOption.data('lead-id');

                $('#edit-type-id').val(typeId);
                $('#edit-type-name').val(selectedOption.data('name')).prop('disabled', false);
                $('#edit-type-submit').prop('disabled', false);

                leadsDropdown.val(currentLeadId ? currentLeadId : "").prop('disabled', false);
            } else {
                $('#edit-type-id').val('');
                $('#edit-type-name').val('').prop('disabled', true);
                $('#edit-type-submit').prop('disabled', true);
                leadsDropdown.val("").prop('disabled', true);
            }
        });
    });
</script>
@endpush
