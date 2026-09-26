@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-lg-10 col-md-12 mx-auto">
        <div class="ms-panel">
            <div class="ms-panel-header text-center">
                <h2>Foundation of Faith Program</h2>
                <p class="text-muted">Begin your registration journey below.</p>
            </div>
            <div class="ms-panel-body">
                @if(!empty($errorMessage))
                    <div class="alert alert-danger">{{ $errorMessage }}</div>
                @endif

                @if($formState === 'initial')
                    <h5 class="text-primary">Ready to Begin?</h5>
                    <p class="text-muted">Provide your email or phone number to check for an existing record.</p>

                    <form method="POST" action="{{ route('fof.lookup') }}">

                        @csrf
                        <div class="form-row">
                            <div class="col-md-6 mb-3">
                                <label>Email Address</label>
                                <input type="email" class="form-control" name="email" placeholder="name@example.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label>Phone Number</label>
                                <input type="tel" class="form-control" name="phone_number" placeholder="e.g. 08012345678">
                            </div>
                        </div>

                        <button type="submit" name="proceed_button" class="btn btn-primary btn-lg btn-block">
                            Proceed <i class="fa fa-arrow-right ml-2"></i>
                        </button>
                    </form>

                @endif


                @if($formState === 'full_form')
                    <form id="full-registration-form" method="post" enctype="multipart/form-data">
                        @csrf
                        @php
                            $isExistingUser = isset($userData['source']) && ($userData['source'] === 'tiu_member' || $userData['source'] === 'first_timer');
                        @endphp

                        @if($isExistingUser)
                            @php
                                // Decode existing department data for pre-selection
                                $existingDeptIds = [];
                                if (!empty($userData['department_name'])) {
                                    $deptRaw = $userData['department_name'];
                                    if (is_string($deptRaw)) {
                                        $decoded = json_decode($deptRaw, true);
                                        if (is_array($decoded)) {
                                            $existingDeptIds = $decoded;
                                        }
                                    } elseif (is_array($deptRaw)) {
                                        $existingDeptIds = $deptRaw;
                                    }
                                }
                            @endphp
                            <div class="alert alert-success text-center">
                                <h5 class="alert-heading"><i class="fa fa-check-circle mr-2"></i>Record Found!</h5>
                                <p class="mb-0">Welcome {{ $userData['first_name'] ?? '' }}. Please complete the details below to finish your registration.</p>
                            </div>
                            <input type="hidden" name="tiu_member_id" value="{{ $userData['tiu_member_id'] ?? '' }}">
                            <input type="hidden" name="first_timer_id" value="{{ $userData['first_timer_id'] ?? '' }}">
                            <input type="hidden" name="first_name" value="{{ $userData['first_name'] ?? '' }}">
                            <input type="hidden" name="last_name" value="{{ $userData['last_name'] ?? '' }}">
                            <input type="hidden" name="email" value="{{ $userData['email'] ?? '' }}">
                            <input type="hidden" name="phone_number" value="{{ $userData['phone_number'] ?? '' }}">
                            <input type="hidden" name="gender" value="{{ $userData['gender'] ?? '' }}">
                            <input type="hidden" name="marital_status" value="{{ $userData['marital_status'] ?? '' }}">
                            <input type="hidden" name="age" value="{{ $userData['age'] ?? '' }}">
                            <input type="hidden" name="occupation" value="{{ $userData['occupation'] ?? '' }}">
                            <input type="hidden" name="residential_address" value="{{ $userData['residential_address'] ?? $userData['address'] ?? '' }}">
                            <input type="hidden" id="existing-department-ids" value="{{ json_encode($existingDeptIds) }}">
                            @if(!empty($userData['picture_part']))
                                <input type="hidden" name="existing_photo_path" value="{{ $userData['picture_part'] }}">
                            @endif

                        @else
                            <div class="alert alert-info">Welcome! Please fill out the form below to register.</div>
                            <input type="hidden" name="tiu_member_id" value="">
                            <input type="hidden" name="first_timer_id" value="">
                            <input type="hidden" name="email" value="{{ $userData['email'] ?? '' }}">
                            <input type="hidden" name="phone_number" value="{{ $userData['phone_number'] ?? '' }}">

                            <div class="mb-4">
                                <h6 class="font-weight-bold">Personal Details</h6>
                                <hr class="mt-1">
                                <div class="form-row">
                                    <div class="col-md-6 mb-3">
                                        <label>First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="first_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Surname <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="last_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Email</label>
                                        <input type="email" class="form-control" value="{{ $userData['email'] ?? '' }}" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Phone</label>
                                        <input type="tel" class="form-control" value="{{ $userData['phone_number'] ?? '' }}" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Gender <span class="text-danger">*</span></label>
                                        <select class="form-control" name="gender" required>
                                            <option value="">-- Select --</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Marital Status <span class="text-danger">*</span></label>
                                        <select class="form-control" name="marital_status" required>
                                            <option value="">-- Select --</option>
                                            <option value="Single">Single</option>
                                            <option value="Married">Married</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Age Range <span class="text-danger">*</span></label>
                                        <select name="age" class="form-control" required>
                                            <option value="">-- Select --</option>
                                            <option value="Below 15">Below 15</option>
                                            <option value="15-17">15-17</option>
                                            <option value="18-24">18-24</option>
                                            <option value="25-34">25-34</option>
                                            <option value="35-44">35-44</option>
                                            <option value="45-59">45-59</option>
                                            <option value="Above 60">Above 60</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label>Occupation <span class="text-danger">*</span></label>
                                        <select name="occupation" class="form-control" required>
                                            <option value="">-- Select --</option>
                                            @foreach($occupations as $occ)
                                                <option value="{{ $occ->occ_name }}">{{ $occ->occ_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label>Residential Address <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="residential_address" placeholder="Your residential address" required>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="mb-4 bg-white p-4 rounded shadow-sm" style="border: 1px solid #e9ecef;">
                            <h5 class="font-weight-bold text-primary mb-3">Program & Service Details</h5>
                            <div class="form-row">
                                <div class="col-md-6 mb-3">
                                    <label for="bday">Birth Day</label>
                                    <select class="form-control" id="bday" name="bday">
                                        <option value="">Day</option>
                                        @for($i = 1; $i <= 31; $i++)
                                            @php $v = str_pad($i, 2, '0', STR_PAD_LEFT); @endphp
                                            <option value="{{ $v }}">{{ $v }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="bmonth">Birth Month</label>
                                    <select class="form-control" id="bmonth" name="bmonth">
                                        <option value="">Month</option>
                                        <option value="Jan">Jan</option>
                                        <option value="Feb">Feb</option>
                                        <option value="Mar">Mar</option>
                                        <option value="Apr">Apr</option>
                                        <option value="May">May</option>
                                        <option value="Jun">Jun</option>
                                        <option value="Jul">Jul</option>
                                        <option value="Aug">Aug</option>
                                        <option value="Sep">Sep</option>
                                        <option value="Oct">Oct</option>
                                        <option value="Nov">Nov</option>
                                        <option value="Dec">Dec</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Department(s) you serve in</label>
                                    <select class="form-control" name="department[]" id="department_select" multiple>
                                        @foreach($departments as $dept)
                                            <option value="{{ $dept->dept_id }}">{{ $dept->dept_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label for="cohort_id">Next Batch (Active Cohort) <span class="text-danger">*</span></label>
                                    <select class="form-control" name="cohort_id" id="cohort_id" required>
                                        <option value="">-- Select Cohort --</option>
                                        @foreach($cohorts as $cohort)
                                            <option value="{{ $cohort->cohort_id }}">{{ $cohort->cohort_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>Your SMART Request <span class="text-danger">*</span></label>
                                    <div class="p-4 rounded" style="background-color: #f0f7ff; border-left: 5px solid #0d6efd;">
                                        <p class="mb-2 font-weight-bold text-dark" style="font-size: 0.9rem;">Your SMART Request should be guided by these principles:</p>
                                        <ul class="mb-0" style="list-style-type: none; padding-left: 0;">
                                            <li class="mb-1" style="font-size: 0.85rem;"><strong style="color: #0d6efd;">1. Specific</strong> <span style="color: #212529;">– Be clear and precise about what you're asking for.</span></li>
                                            <li class="mb-1" style="font-size: 0.85rem;"><strong style="color: #0d6efd;">2. Measurable (Quantifiable)</strong> <span style="color: #212529;">– Include a number or metric so you can track progress.</span></li>
                                            <li class="mb-1" style="font-size: 0.85rem;"><strong style="color: #0d6efd;">3. Achievable</strong> <span style="color: #212529;">– Is it possible within the next 3 months?</span></li>
                                            <li class="mb-1" style="font-size: 0.85rem;"><strong style="color: #0d6efd;">4. Relevant</strong> <span style="color: #212529;">– Back it with a scripture or personal conviction.</span></li>
                                            <li class="mb-1" style="font-size: 0.85rem;"><strong style="color: #0d6efd;">5. Time Bound</strong> <span style="color: #212529;">– Set a deadline (e.g., before the end of December 2024).</span></li>
                                        </ul>
                                        <hr class="my-2" style="border-color: #cce5ff;">
                                        <p class="mb-0 font-italic text-dark" style="font-size: 0.85rem;"><strong>Example:</strong> "I want a job as a data analyst that pays ₦500,000 monthly, before the end of December 2024. (Jeremiah 29:11)"</p>
                                        <hr class="my-2" style="border-color: #cce5ff;">
                                        <div class="form-group mb-0">
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="smart_request_understand" name="smart_request_understand" value="1">
                                                <label class="custom-control-label" for="smart_request_understand">
                                                    I understand — I have read and understood the SMART Request guidelines above.
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <textarea class="form-control mt-2" name="smart_request" rows="3" placeholder="e.g. I want a job as a data analyst that pays ₦500,000 monthly, before the end of December 2024." required></textarea>
                                </div>



                                <div class="col-md-12 mb-3">
                                    <label>Commitment to the Program <span class="text-danger">*</span></label>

                                    <select class="form-control" name="commitment" required>
                                        <option value="">-- Are you willing to commit? --</option>
                                        <option value="Yes">Yes, I am willing to commit</option>
                                        <option value="No">No, I am not</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label>How did you learn about FOF? <span class="text-danger">*</span></label>
                                    <select class="form-control" name="how_heard" id="how_heard_select" required>
                                        <option value="">-- Please select --</option>
                                        <option value="WhatsApp Group">WhatsApp Group</option>
                                        <option value="Church Instagram">Church Instagram</option>
                                        <option value="Church Announcement">Church Announcement</option>
                                        <option value="One on One Interaction">One on One Interaction</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-group col-12" id="how_heard_other_wrapper" style="display: none;">
                                    <label for="how_heard_other">Please specify:</label>
                                    <input type="text" class="form-control" name="how_heard_other" id="how_heard_other">
                                </div>
                            </div>
                        </div>

                        @php $hasPhoto = $isExistingUser && !empty($userData['picture_part']); @endphp

                        @if(!$hasPhoto)
                            <div class="mb-4">
                                <h6 class="font-weight-bold">Upload Your Photo</h6>
                                <hr class="mt-1">
                                <label>Please upload a clear, recent photo. <span class="text-danger">*</span></label>
                                <div class="photo-upload-area" onclick="$('#profile_photo_input').click();">
                                    <i class="fa fa-cloud-upload-alt" style="font-size: 3rem; color: #6c757d;"></i>
                                    <p class="mb-0">Click to browse for a photo</p>
                                    <small class="form-text text-muted">JPG, PNG. Max 5MB.</small>
                                </div>
                                <input type="file" class="form-control d-none" name="profile_photo" id="profile_photo_input" accept="image/png, image/jpeg, image/jpg">
                                <div id="photo-preview-container" class="text-center mt-3" style="display:none;">
                                    <p>New Photo Preview:</p>
                                    <img id="photo_preview" src="#" alt="Your photo preview" style="max-height: 200px; border-radius: 8px; border: 1px solid #ddd;" />
                                </div>
                            </div>
                        @elseif($isExistingUser)
                            <div class="mb-4 text-center">
                                <h6 class="font-weight-bold text-muted">Photo on File</h6>
                                <img src="{{ $userData['picture_part'] }}" alt="Your photo" style="max-height: 150px; border-radius: 8px; border: 1px solid #ddd; opacity: 0.8;">
                                <p class="small text-muted mt-2"><i class="fa fa-check text-success"></i> Using your existing photo.</p>
                            </div>
                        @endif

                        <button type="submit" name="register_button" class="btn btn-primary btn-lg btn-block mt-4">
                            <i class="fa fa-check-circle mr-2"></i> Submit My Registration
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.photo-upload-area {
    border: 2px dashed #ced4da;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    cursor: pointer;
    background-color: #f8f9fa;
}
</style>
@endsection

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('#department_select').select2({
        theme: 'bootstrap-5',
        placeholder: "You can select more than one department",
        allowClear: true
    });

    // Pre-populate department dropdown for existing members
    var existingDeptInput = $('#existing-department-ids');
    if (existingDeptInput.length > 0) {
        var deptIds = JSON.parse(existingDeptInput.val() || '[]');
        if (deptIds.length > 0) {
            $('#department_select').val(deptIds).trigger('change');
        }
    }


    $('#how_heard_select').on('change', function() {
        if ($(this).val() === 'Other') {
            $('#how_heard_other_wrapper').slideDown();
            $('#how_heard_other').prop('required', true);
        } else {
            $('#how_heard_other_wrapper').slideUp();
            $('#how_heard_other').prop('required', false);
        }
    });

    $("#profile_photo_input").on("change", function() {
        if (this.files && this.files[0]) {
            const fileSize = this.files[0].size / 1024 / 1024;
            if (fileSize > 5) {
                Swal.fire('File Too Large', 'Please upload an image smaller than 5MB.', 'error');
                $(this).val('');
                $('#photo-preview-container').slideUp();
                return;
            }
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#photo_preview').attr('src', e.target.result);
                $('#photo-preview-container').slideDown();
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            $('#photo-preview-container').slideUp();
        }
    });

    $('#full-registration-form').on('submit', function(e) {
        e.preventDefault();

        // Validate SMART Request understanding checkbox
        if (!$('#smart_request_understand').is(':checked')) {
            Swal.fire({
                icon: 'warning',
                title: 'Checkbox Required',
                text: 'Please check the "I understand" box to confirm you have read and understood the SMART Request guidelines.',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        var formData = new FormData(this);
        Swal.fire({
            title: 'Submitting Registration',
            text: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '{{ route("fof.store") }}',
            type: 'POST',
            data: formData,
            dataType: 'json',
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Registration Successful!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.href = '{{ route("fof.register") }}';
                    });

                } else {
                    Swal.fire('Submission Failed', response.message, 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'An unexpected error occurred. Please try again.', 'error');
            }
        });
    });
});
</script>
@endpush
