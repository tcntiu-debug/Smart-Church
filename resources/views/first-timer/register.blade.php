@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">
            <h5>First Timer Register</h5>
        </div>
        <div class="card-body">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            
            <form id="ft-form" method="POST">
                @csrf
                <div id="success-message" class="alert alert-success" style="display:none;"></div>
                @if($userCampusName)
                <div class="alert alert-info mb-3">
                    <i class="fas fa-university"></i> <strong>Your Campus:</strong> {{ $userCampusName }}
                    <input type="hidden" name="campus_id" value="{{ $userCampusId }}">
                </div>
                @endif
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
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" class="form-control" id="phone_number" name="phone_number" placeholder="08012345678">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="gender">Gender <span class="text-danger">*</span></label>
                        <select class="form-control" id="gender" name="gender" required>
                            <option value="">select</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="age">Age Range <span class="text-danger">*</span></label>
                        <select class="form-control" id="age" name="age" required>
                            <option value="">select</option>
                            <option value="Below 15">Below 15</option>
                            <option value="15-17">15-17</option>
                            <option value="18-24">18-24</option>
                            <option value="25-34">25-34</option>
                            <option value="35-44">35-44</option>
                            <option value="45-59">45-59</option>
                            <option value="Above 60">Above 60</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="marital_status">Marital Status</label>
                        <select class="form-control" id="marital_status" name="marital_status">
                            <option value="">select</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="occupation">Occupation</label>
                        <select id="occupation" name="occupation" class="form-control">
                            <option value="">-- Select --</option>
                            @foreach($occupations as $occ)
                                <option value="{{ $occ->occ_name }}">{{ $occ->occ_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="guest_type">Guest Type <span class="text-danger">*</span></label>
                        <select class="form-control" id="guest_type" name="guest_type" required>
                            <option value="">select</option>
                            <option value="Visiting">Visiting</option>
                            <option value="New To TCN (Will Join TCN)">New To TCN (Will Join TCN)</option>
                            <option value="New To TCN (May Join TCN)">New To TCN (May Join TCN)</option>
                            <option value="TCN Member (Relocating to Ikd)">TCN Member (Relocating to Ikd)</option>
                            <option value="TCN Member (In Transit)">TCN Member (In Transit)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="how_did_you_hear">How did you hear about us? <span class="text-danger">*</span></label>
                        <select class="form-control" id="how_did_you_hear" name="how_did_you_hear" required>
                            <option value="">select</option>
                            <option value="TV">TV</option>
                            <option value="Online/Social Media">Online/Social Media</option>
                            <option value="Family/Friends">Family/Friends</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="address">Residential Address <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="address" name="address" placeholder="Enter full address" rows="3" required></textarea>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="born_again">Are you born again? <span class="text-danger">*</span></label>
                        <select class="form-control" id="born_again" name="born_again" required>
                            <option value="">select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="water_baptism">I want water Baptism <span class="text-danger">*</span></label>
                        <select class="form-control" id="water_baptism" name="water_baptism" required>
                            <option value="">select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="holy_ghost_baptism">I want Holy Ghost Baptism <span class="text-danger">*</span></label>
                        <select class="form-control" id="holy_ghost_baptism" name="holy_ghost_baptism" required>
                            <option value="">select</option>
                            <option value="Yes">Yes</option>
                            <option value="No">No</option>
                        </select>
                    </div>
                </div>
                
                <!-- Admin Section -->
                <div class="admin-section-divider">
                    <span><b>For Church Admin Use</b></span>
                </div>
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="church_type_id">Church Type <span class="text-danger">*</span></label>
                        <select id="church_type_id" name="church_type_id" class="form-control" required>
                            <option value="">-- Select Church Type --</option>
                            @foreach($churchTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->church_type_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <!-- Parent/Guardian Section -->
                <div id="parent-guardian-section" style="display:none;">
                    <h5 class="mt-3">Parent / Guardian Information <small>(Optional)</small></h5>
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
                
                <div id="error-message" class="alert alert-danger" style="display:none;"></div>
                
                
                <button type="submit" id="submit-btn" class="btn btn-primary w-100 mt-3">
                    <span id="loading-spinner" style="display:none;"><i class="fas fa-spinner fa-spin"></i> Registering...</span>
                    <span id="btn-text">SUBMIT</span>
                </button>
                
                <p class="mb-0 mt-3 text-center">
                    <a class="btn-link" href="{{ route('my-tasks.index') }}">Home</a>
                </p>
            </form>
        </div>
    </div>
</div>

<style>
    .admin-section-divider {
        width: 100%;
        text-align: center;
        border-bottom: 1px solid #ccc;
        line-height: 0.1em;
        margin: 20px 0 30px;
    }
    .admin-section-divider span {
        background: #fff;
        padding: 0 10px;
        color: #555;
        font-size: 0.9em;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Show/hide parent section based on church type
    $('#church_type_id').on('change', function() {
        var selectedText = $(this).find('option:selected').text().trim();
        var isChildOrJT = (selectedText === 'Jesus Tribe' || selectedText === 'Children');
        
        if (isChildOrJT) {
            $('#parent-guardian-section').slideDown();
        } else {
            $('#parent-guardian-section').slideUp();
        }
    });
    
    // Form submission
    $('#ft-form').on('submit', function(e) {
        e.preventDefault();
        
        // Hide previous messages
        $('#error-message').hide();
        $('#success-message').hide();
        
        // Show loading
        $('#loading-spinner').show();
        $('#btn-text').hide();
        $('#submit-btn').prop('disabled', true);
        
        $.ajax({
            url: '{{ route("first-timer.store") }}',
            method: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    $('#success-message').html(response.message).fadeIn();
                    $('#ft-form')[0].reset();
                    $('#church_type_id').val('').trigger('change');
                    $('#parent-guardian-section').hide();
                    
                    // Scroll to success message
                    $('html, body').animate({ scrollTop: 0 }, 'slow');
                    
                    // Hide success after 4 seconds
                    setTimeout(function() {
                        $('#success-message').fadeOut();
                    }, 4000);
                }
            },
            error: function(xhr) {
                var errorMsg = 'An error occurred. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    if (typeof xhr.responseJSON.message === 'object') {
                        errorMsg = Object.values(xhr.responseJSON.message).join(', ');
                    } else {
                        errorMsg = xhr.responseJSON.message;
                    }
                }
                $('#error-message').html(errorMsg).fadeIn();
                $('html, body').animate({ scrollTop: $('#error-message').offset().top - 100 }, 'slow');
            },
            complete: function() {
                $('#loading-spinner').hide();
                $('#btn-text').show();
                $('#submit-btn').prop('disabled', false);
            }
        });
    });
});
</script>
@endsection