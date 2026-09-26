@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6>Create Account</h6>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form id="member-register-form">
                        @csrf
                        
                        <!-- Personal Info -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name">First name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name">Last name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone_number">Phone Number <small id="phone_optional_text" style="display:none;">(Optional)</small></label>
                                <input type="tel" class="form-control" id="phone_number" name="phone_number" placeholder="08012345678">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email">Email <small id="email_optional_text" style="display:none;">(Optional)</small></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="example@email.com">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender">Gender <span class="text-danger">*</span></label>
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="residential_address">Residential Address <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="residential_address" name="residential_address" placeholder="Enter your full address" required>
                            </div>
                        </div>

                        <!-- Community Section -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="community">Community <span class="text-danger">*</span></label>
                                <select id="community" name="community_id" class="form-control" required>
                                    <option value="">-- Select Your Community --</option>
                                    @foreach($communities ?? [] as $community)
                                        <option value="{{ $community->id }}">{{ $community->community_name }}</option>
                                    @endforeach
                                    <option value="Other">Others</option>
                                </select>
                            </div>
                        </div>

                        <!-- Other Community Input -->
                        <div class="row" id="other_community_div" style="display:none;">
                            <div class="col-md-12 mb-3">
                                <label for="community_other">Please specify Community <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="community_other" name="community_other" placeholder="Enter your community name">
                            </div>
                        </div>

                        <!-- Campus Section -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="campus">Campus <span class="text-danger">*</span></label>
                                <select id="campus" name="campus_id" class="form-control" required>
                                    <option value="">-- Select Your Campus --</option>
                                    @foreach($campuses ?? [] as $cmp)
                                        <option value="{{ $cmp->cid }}">{{ $cmp->cname }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Church Type - Single instance -->
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="church_type">Church Type <span class="text-danger">*</span></label>
                                <select id="church_type" name="church_type_id" class="form-control" required>
                                    <option value="">-- Select Type --</option>
                                    @foreach($churchTypes ?? [] as $type)
                                        <option value="{{ $type->id }}">{{ $type->church_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Parent/Guardian Section -->
                        <div id="parent_guardian_section" style="display:none;">
                            <h5 class="mt-4">Parent / Guardian Information <small>(Optional)</small></h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="parent_guardian_name">Full Name</label>
                                    <input type="text" class="form-control" id="parent_guardian_name" name="parent_guardian_name" placeholder="Parent/Guardian Name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="parent_guardian_phone">Phone Number</label>
                                    <input type="tel" class="form-control" id="parent_guardian_phone" name="parent_guardian_phone" placeholder="Parent/Guardian Phone">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="parent_guardian_relationship">Relationship</label>
                                    <input type="text" class="form-control" id="parent_guardian_relationship" name="parent_guardian_relationship" placeholder="e.g., Mother, Father, Uncle">
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-primary w-100 mt-4" id="submit_btn" onclick="registerMember()">
                            <span id="loading" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Registering...</span>
                            <span id="button_text">Create Account</span>
                        </button>
                        <p class="text-center mt-3 mb-0">
                            Already have an account? <a href="{{ route('login') }}">Login</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// No need to load jQuery again - it's already in the layout
// SweetAlert2 is now loaded in the layout

$(document).ready(function() {
    console.log('Document ready - jQuery is working!');
    
    // Community change listener
    $('#community').off('change').on('change', function() {
        var selectedVal = $(this).val();
        var selectedText = $(this).find('option:selected').text();
        
        if (selectedVal === 'Other' || selectedText === 'Others') {
            $('#other_community_div').slideDown();
            $('#community_other').focus();
        } else {
            $('#other_community_div').slideUp();
            $('#community_other').val('');
        }
    });

    // Church type change listener
    $('#church_type').off('change').on('change', function() {
        var selectedText = $(this).find('option:selected').text();
        
        if (selectedText === 'Jesus Tribe' || selectedText === 'Children') {
            $('#parent_guardian_section').slideDown();
            $('#email, #phone_number').prop('required', false);
            $('#email_optional_text, #phone_optional_text').show();
        } else {
            $('#parent_guardian_section').slideUp();
            $('#email, #phone_number').prop('required', true);
            $('#email_optional_text, #phone_optional_text').hide();
        }
    }).trigger('change');
});

function registerMember() {
    console.log('registerMember function called');
    
    var validRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
    
    // Get Values
    var firstName = $("#first_name").val().trim();
    var lastName = $("#last_name").val().trim();
    var phoneNumber = $("#phone_number").val().trim();
    var email = $("#email").val().trim();
    var gender = $("#gender").val();
    var residentialAddress = $("#residential_address").val().trim();
    var campusId = $("#campus").val();
    var churchTypeId = $("#church_type").val();
    var churchTypeName = $("#church_type").find('option:selected').text();
    var communityId = $("#community").val();
    var communityOther = $("#community_other").val().trim();
    
    var parentGuardianName = $("#parent_guardian_name").val().trim();
    var parentGuardianPhone = $("#parent_guardian_phone").val().trim();
    var parentGuardianRelationship = $("#parent_guardian_relationship").val().trim();

    // Basic Validation
    if (!firstName || !lastName || !gender || !residentialAddress || !churchTypeId || !communityId || !campusId) {
        console.error('Validation Failed: Missing required fields', {
            firstName: !!firstName,
            lastName: !!lastName,
            gender: !!gender,
            residentialAddress: !!residentialAddress,
            churchTypeId: !!churchTypeId,
            communityId: !!communityId,
            campusId: !!campusId
        });
        Swal.fire('Error', 'Please fill out all required fields.', 'error');
        return;
    }

    // Community Others Validation
    if ($('#other_community_div').is(':visible') && communityOther.length === 0) {
        console.error('Validation Failed: Community other field required but empty');
        Swal.fire('Error', 'Please specify your community name.', 'error');
        $("#community_other").focus();
        return;
    }

    // Church Type specific validation
    if (churchTypeName === 'Jesus Tribe' || churchTypeName === 'Children') {
        if (email && !validRegex.test(email)) {
            console.error('Validation Failed: Invalid email format', { email: email });
            Swal.fire('Error', 'Invalid email address format.', 'error');
            return;
        }
        if (phoneNumber && phoneNumber.length > 0 && !/^\d{11}$/.test(phoneNumber)) {
            console.error('Validation Failed: Invalid phone number format', { phoneNumber: phoneNumber, length: phoneNumber.length });
            Swal.fire('Error', 'If you enter a phone number, it must be 11 digits.', 'error');
            return;
        }
    } else {
        if (!email) {
            console.error('Validation Failed: Email required for church type', { churchTypeName: churchTypeName });
            Swal.fire('Error', 'Email address is required for this Church Type.', 'error');
            return;
        }
        if (!validRegex.test(email)) {
            console.error('Validation Failed: Invalid email format', { email: email });
            Swal.fire('Error', 'Invalid email address.', 'error');
            return;
        }
        if (!phoneNumber) {
            console.error('Validation Failed: Phone number required for church type', { churchTypeName: churchTypeName });
            Swal.fire('Error', 'Phone Number is required for this Church Type.', 'error');
            return;
        }
        if (!/^\d{11}$/.test(phoneNumber)) {
            console.error('Validation Failed: Invalid phone number format', { phoneNumber: phoneNumber, length: phoneNumber.length });
            Swal.fire('Error', 'Phone number must be 11 digits.', 'error');
            return;
        }
    }
    
    // Prepare Submission
    $("#loading").show();
    $("#button_text").hide();
    $('#submit_btn').prop('disabled', true);
    
    var postData = {
        _token: '{{ csrf_token() }}',
        first_name: firstName,
        last_name: lastName,
        phone_number: phoneNumber,
        email: email,
        gender: gender,
        residential_address: residentialAddress,
        campus_id: campusId,
        church_type_id: churchTypeId,
        community_id: communityId === 'Other' ? null : communityId,
        community_other: communityOther,
        departments_json: JSON.stringify([]),
        clusters_json: JSON.stringify([]),
        house_fellowships_json: JSON.stringify([]),
        parent_guardian_name: parentGuardianName,
        parent_guardian_phone: parentGuardianPhone,
        parent_guardian_relationship: parentGuardianRelationship
    };
    
    console.log('Sending data:', postData);
    console.log('Request URL:', "{{ route('member.store') }}");
    
    $.ajax({
        type: "POST",
        url: "{{ route('member.public.store') }}",
        data: postData,
        dataType: 'json',
        timeout: 30000, // 30 second timeout
        success: function(response) {
            console.log('AJAX Success:', response);
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 3000,
                    showConfirmButton: false
                }).then(() => {
                    $('#member-register-form')[0].reset();
                    $('#church_type, #community, #campus, #gender').val('');
                    $('#other_community_div').hide();
                    $('#parent_guardian_section').hide();
                });
            } else {
                console.error('Server returned error:', response.message);
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.group('🔴 AJAX Error Details');
            console.error('Status:', status);
            console.error('Error Thrown:', error);
            console.error('HTTP Status Code:', xhr.status);
            console.error('Status Text:', xhr.statusText);
            console.error('Response Text:', xhr.responseText);
            console.error('Response JSON:', xhr.responseJSON);
            
            // Log request URL for debugging
            console.error('Request URL:', "{{ route('member.store') }}");
            
            // Log all response headers if available
            try {
                var headers = xhr.getAllResponseHeaders();
                console.error('Response Headers:', headers);
            } catch(e) {
                console.error('Could not get response headers');
            }
            
            // Detailed error parsing
            var errorMsg = "An unexpected network error occurred.";
            var detailedError = "";
            
            if (xhr.status === 0) {
                detailedError = "Network error - possible CORS issue or server not reachable. Check if the server is running and CORS headers are configured.";
            } else if (xhr.status === 404) {
                detailedError = "Endpoint not found. Check if route 'member.store' exists and URL is correct.";
            } else if (xhr.status === 500) {
                detailedError = "Server internal error. Check Laravel logs (storage/logs/laravel.log) for details.";
            } else if (xhr.status === 419) {
                detailedError = "CSRF token mismatch. Page may have expired. Try refreshing the page.";
            } else if (xhr.status === 422) {
                detailedError = "Validation error. Check the response for specific field errors.";
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    detailedError += "\n" + JSON.stringify(xhr.responseJSON.errors, null, 2);
                }
            } else if (status === 'timeout') {
                detailedError = "Request timed out after 30 seconds. Server might be slow or unresponsive.";
            } else if (status === 'parsererror') {
                detailedError = "Invalid JSON response from server. Check if server is returning HTML instead of JSON.";
            }
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                // Check if response is HTML (Laravel debug page)
                if (xhr.responseText.trim().startsWith('<!DOCTYPE') || xhr.responseText.trim().startsWith('<html')) {
                    detailedError += "\n\nServer returned HTML instead of JSON. Likely a PHP error or unhandled exception.";
                    // Extract error message from HTML if possible
                    var errorMatch = xhr.responseText.match(/<div class="exception-message">([^<]+)<\/div>/);
                    if (errorMatch) {
                        detailedError += "\nPHP Error: " + errorMatch[1];
                    }
                } else {
                    errorMsg = xhr.responseText.substring(0, 200);
                }
            }
            
            console.error('Parsed Error Message:', errorMsg);
            console.error('Detailed Error Info:', detailedError);
            console.groupEnd();
            
            // Show comprehensive error to user (with option to see details)
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                html: `<p>${errorMsg}</p>
                       <small style="color: #666; font-size: 12px;">Status: ${xhr.status} ${xhr.statusText}<br>
                       Check console (F12) for detailed error information.</small>`,
                confirmButtonText: 'OK'
            });
        },
        complete: function() {
            $("#loading").hide();
            $("#button_text").show();
            $('#submit_btn').prop('disabled', false);
            console.log('AJAX request completed (success or error)');
        }
    });
}
</script>
@endpush
@endsection