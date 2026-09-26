@extends('layouts.app')

@section('content')
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Material Design Chip styling for Select2 */
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        min-height: 38px;
        padding: 2px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__rendered {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        padding: 2px 4px !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        display: inline-flex !important;
        align-items: center !important;
        background-color: #e0e0e0 !important;
        border: none !important;
        border-radius: 16px !important;
        padding: 2px 8px 2px 12px !important;
        margin: 2px !important;
        font-size: 0.8125rem !important;
        line-height: 1.5 !important;
        color: rgba(0, 0, 0, 0.87) !important;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
        transition: box-shadow 0.2s ease;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice:hover {
        box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        margin-left: 4px !important;
        font-size: 16px !important;
        color: rgba(0, 0, 0, 0.54) !important;
        cursor: pointer !important;
        order: 1 !important;
        position: relative;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: rgba(0, 0, 0, 0.87) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__clear {
        margin-top: 8px !important;
        margin-right: 4px !important;
    }
    /* Style the search field inside multi-select */
    .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
        margin-top: 6px !important;
        font-size: 0.875rem !important;
    }
    /* Material Style for single-select dropdowns */
    .select2-container--default .select2-selection--single {
        border: 1px solid #ced4da !important;
        border-radius: 0.25rem !important;
        min-height: 38px;
        padding: 0;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px !important;
        padding-left: 12px !important;
        font-size: 0.875rem !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px !important;
    }
    .photo-preview {
        max-height: 200px;
        max-width: 100%;
        border-radius: 8px;
        border: 1px solid #ddd;
        padding: 4px;
    }
</style>



<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h6>Edit Profile: {{ $tiuMember->first_name }} {{ $tiuMember->last_name }}</h6>
            
            @if(request()->has('msg'))
                <div class="alert alert-danger mt-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Action Required:</strong> {{ request()->msg }}
                </div>
            @endif
            
            <div id="success" class="alert alert-success" style="display:none;"></div>
            <div id="error" class="alert alert-danger" style="display:none;"></div>
        </div>
        <div class="card-body">
            <form id="profile-form" class="needs-validation" novalidate>
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>First name</label>
                        <input name="first_name" type="text" class="form-control" value="{{ $tiuMember->first_name }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Last name</label>
                        <input name="last_name" type="text" class="form-control" value="{{ $tiuMember->last_name }}" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input name="email" type="email" class="form-control" value="{{ $tiuMember->email }}" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Phone Number</label>
                        <input name="phone_number" type="tel" class="form-control" value="{{ $tiuMember->phone_number }}" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Gender</label>
                        <select name="gender" id="gender" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Male" {{ $tiuMember->gender == 'Male' ? 'selected' : '' }}>Male</option>
                            <option value="Female" {{ $tiuMember->gender == 'Female' ? 'selected' : '' }}>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Marital Status</label>
                        <select name="marital_status" id="marital-status" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Single" {{ $tiuMember->marital_status == 'Single' ? 'selected' : '' }}>Single</option>
                            <option value="Married" {{ $tiuMember->marital_status == 'Married' ? 'selected' : '' }}>Married</option>
                            <option value="Others" {{ $tiuMember->marital_status == 'Others' ? 'selected' : '' }}>Others</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Age Range</label>
                        <select name="age" id="age-range" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Below 15" {{ $tiuMember->age == 'Below 15' ? 'selected' : '' }}>Below 15</option>
                            <option value="15-17" {{ $tiuMember->age == '15-17' ? 'selected' : '' }}>15-17</option>
                            <option value="18-24" {{ $tiuMember->age == '18-24' ? 'selected' : '' }}>18-24</option>
                            <option value="25-34" {{ $tiuMember->age == '25-34' ? 'selected' : '' }}>25-34</option>
                            <option value="35-44" {{ $tiuMember->age == '35-44' ? 'selected' : '' }}>35-44</option>
                            <option value="45-59" {{ $tiuMember->age == '45-59' ? 'selected' : '' }}>45-59</option>
                            <option value="Above 60" {{ $tiuMember->age == 'Above 60' ? 'selected' : '' }}>Above 60</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Community</label>
                        <select name="community_id" id="community" class="form-control" required>
                            <option value="">-- Select --</option>
                            @foreach($communities as $community)
                                <option value="{{ $community->id }}" {{ $tiuMember->community_id == $community->id ? 'selected' : '' }}>
                                    {{ $community->community_name }}
                                </option>
                            @endforeach
                            <option value="other" {{ empty($tiuMember->community_id) && !empty($tiuMember->community_other) ? 'selected' : '' }}>Other (Please specify)</option>
                        </select>
                        <div id="other-community-wrapper" class="mt-2" style="display: {{ (empty($tiuMember->community_id) && !empty($tiuMember->community_other)) ? 'block' : 'none' }};">
                            <label>Please specify your community:</label>
                            <input type="text" name="community_other" class="form-control" value="{{ $tiuMember->community_other ?? '' }}">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Occupation</label>
                        <select name="occupation" id="occupation" class="form-control" required>
                            <option value="">-- Select --</option>
                            @foreach($occupations as $occ)
                                <option value="{{ $occ->occ_name }}" {{ $tiuMember->occupation == $occ->occ_name ? 'selected' : '' }}>
                                    {{ $occ->occ_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Campus</label>
                        <select name="campus_id" class="form-control">
                            <option value="">-- Select Campus --</option>
                            @foreach($campuses as $campus)
                                <option value="{{ $campus->cid }}" {{ $tiuMember->campus_id == $campus->cid ? 'selected' : '' }}>
                                    {{ $campus->cname }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @if($sessionRole == 'Super User' || $sessionRole == 'Admin')
                    <div class="col-md-6 mb-3">
                        <label>Member Role</label>
                        <select name="member_role" id="member-role" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="Super User" {{ $tiuMember->member_role == 'Super User' ? 'selected' : '' }}>Super User</option>
                            <option value="Admin" {{ $tiuMember->member_role == 'Admin' ? 'selected' : '' }}>Admin</option>
                            <option value="Member" {{ $tiuMember->member_role == 'Member' ? 'selected' : '' }}>Member</option>
                            <option value="TIU Guest" {{ $tiuMember->member_role == 'TIU Guest' ? 'selected' : '' }}>TIU Guest</option>
                            <option value="Lead" {{ $tiuMember->member_role == 'Lead' ? 'selected' : '' }}>Lead</option>
                            <option value="Worker" {{ $tiuMember->member_role == 'Worker' ? 'selected' : '' }}>Worker</option>
                        </select>
                    </div>
                    @endif
                </div>
                
                <div class="row">
                    @if($sessionRole == 'Super User' || $sessionRole == 'Admin')
                    <div class="col-md-6 mb-3">
                        <label>Touch Point</label>
                        <select name="sub_group" id="sub_group" class="form-control">
                            <option value="">-- Select --</option>
                            @foreach($subGroups as $sg)
                                <option value="{{ $sg->sub_group_name }}" {{ $tiuMember->subgroup == $sg->sub_group_name ? 'selected' : '' }}>
                                    {{ $sg->sub_group_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-md-6 mb-3">
                        <label>Church Type</label>
                        <select name="church_type_id" id="church_type_id" class="form-control" required>
                            <option value="">-- Select --</option>
                            @foreach($churchTypes as $ct)
                                <option value="{{ $ct->id }}" {{ $tiuMember->church_type_id == $ct->id ? 'selected' : '' }}>
                                    {{ $ct->church_type_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <label>Birth Day</label>
                        <select name="bday" id="bday" class="form-control">
                            <option value="">-- Select --</option>
                            @for($i = 1; $i <= 31; $i++)
                                <option value="{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}" {{ str_pad($i, 2, '0', STR_PAD_LEFT) == $bday ? 'selected' : '' }}>
                                    {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}
                                </option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Birth Month</label>
                        <select name="bmonth" id="bmonth" class="form-control">
                            <option value="">-- Select --</option>
                            @php $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']; @endphp
                            @foreach($months as $month)
                                <option value="{{ $month }}" {{ $month == $bmonth ? 'selected' : '' }}>{{ $month }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                @if($sessionRole == 'Super User' || $sessionRole == 'Admin')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Oversight Subgroup</label>
                        <select name="sub_group_oversight[]" id="sub_group_oversight" class="form-control" multiple>
                            <option value="All" {{ in_array('All', $selectedSubgroupOversight) ? 'selected' : '' }}>All</option>
                            @foreach($subGroups as $sg)
                                <option value="{{ $sg->sub_group_name }}" {{ in_array($sg->sub_group_name, $selectedSubgroupOversight) ? 'selected' : '' }}>
                                    {{ $sg->sub_group_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="disable_user" id="disable-user" class="form-check-input" value="2" {{ $tiuMember->status == '2' ? 'checked' : '' }}>
                            <label class="form-check-label">Disable User</label>
                        </div>
                    </div>
                </div>
                @endif
                
                <div class="form-group mb-3">
                    <label>My Department(s)</label>
                    <select name="departments_json[]" id="departments" class="form-control" multiple>
                        @foreach($allDepartments as $dept)
                            <option value="{{ $dept['id'] }}" {{ in_array($dept['id'], $selectedDepartmentIds) ? 'selected' : '' }}>
                                {{ $dept['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group mb-3">
                    <label>Cluster(s)</label>
                    <select name="clusters_json[]" id="clusters" class="form-control" multiple>
                        @foreach($allClusters as $cluster)
                            <option value="{{ $cluster['id'] }}" {{ in_array($cluster['id'], $selectedClusterIds) ? 'selected' : '' }}>
                                {{ $cluster['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group mb-3">
                    <label>House Fellowship(s)</label>
                    <select name="house_fellowships_json[]" id="house-fellowships" class="form-control" multiple>
                        @foreach($allHouseFellowships as $hf)
                            <option value="{{ $hf['id'] }}" {{ in_array($hf['id'], $selectedHouseFellowshipIds) ? 'selected' : '' }}>
                                {{ $hf['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group mb-3">
                    <label>Residential Address</label>
                    <textarea name="residential_address" class="form-control" rows="3">{{ $tiuMember->residential_address }}</textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Next of Kin Name</label>
                        <input name="next_of_kin_name" type="text" class="form-control" value="{{ $tiuMember->next_of_kin_name }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Next of Kin Phone</label>
                        <input name="next_of_kin_phone" type="text" class="form-control" value="{{ $tiuMember->next_of_kin_phone }}">
                    </div>
                </div>
                
                <div class="form-group mb-3">
                    <label>Upload Your Photo</label>
                    <input type="file" name="profile_photo" id="profile_photo_input" class="form-control" accept="image/png, image/jpeg, image/jpg">
                    <small class="text-muted">Accepted formats: JPG, PNG. Max size: 5MB.</small>
                    <div id="photo-preview-container" class="text-center mt-3" style="{{ empty($tiuMember->picture_part) ? 'display: none;' : '' }}">
                        <img id="photo_preview" src="{{ $tiuMember->picture_part ? asset($tiuMember->picture_part) : '#' }}" alt="Photo Preview" class="photo-preview">
                    </div>
                </div>
                
                <input type="hidden" name="tiu_member_id" value="{{ $tiuMember->tiu_member_id }}">
                
                <button type="button" class="btn btn-primary mt-4" onclick="updateProfile()">
                    <span id="loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Updating...</span>
                    <span id="button-text">Update Profile</span>
                </button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Apply Material Design chip-style multiselect to multi-select fields
    $('#departments, #clusters, #house-fellowships, #sub_group_oversight').select2({
        placeholder: function() {
            return $(this).data('placeholder') || 'Select options';
        },
        allowClear: true
    });

    // Apply select2 to single-select fields (no search bar for small lists)
    $('#community, #occupation, #gender, #marital-status, #age-range, #member-role, #sub_group, #church_type_id, #bday, #bmonth, select[name="campus_id"]').select2({
        minimumResultsForSearch: Infinity
    });
    
    function toggleOtherCommunity() {
        var selectedValue = $('#community').val();
        if (selectedValue === 'other') {
            $('#other-community-wrapper').slideDown();
        } else {
            $('#other-community-wrapper').slideUp();
        }
    }
    
    $('#community').on('change', toggleOtherCommunity);
    toggleOtherCommunity();
    
    $("#profile_photo_input").on("change", function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            $("#photo-preview-container").show();
            reader.onload = function(e) {
                $("#photo_preview").attr("src", e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });
});

function updateProfile() {
    $("#error").fadeOut();
    $("#success").fadeOut();
    
    var formData = new FormData($('#profile-form')[0]);
    formData.append('_token', '{{ csrf_token() }}');
    
    $("#loading").show();
    $("#button-text").hide();
    $('button').prop('disabled', true);
    
    $.ajax({
        type: "POST",
        url: "{{ route('profile.update') }}",
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                $("#success").html(response.message).fadeIn(1000).delay(3000).fadeOut();
                setTimeout(function() {
                    window.location.href = window.location.href;
                }, 1500);
            } else {
                $("#error").html(response.message).fadeIn(1000);
            }
        },
        error: function(xhr) {
            var message = xhr.responseJSON?.message || "An unexpected network error occurred.";
            $("#error").html("Error: " + message).fadeIn();
        },
        complete: function() {
            $("#loading").hide();
            $("#button-text").show();
            $('button').prop('disabled', false);
        }
    });
}
</script>
@endpush
