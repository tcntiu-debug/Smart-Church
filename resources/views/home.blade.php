@extends('layouts.app')

@section('content')
<style>
    .dashboard-welcome {
        background: linear-gradient(135deg, #F97316 0%, #FB923C 100%);
        border-radius: 16px;
        padding: 24px;
        color: #fff;
        margin-bottom: 24px;
    }
    .dashboard-welcome .greeting {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    .dashboard-welcome .role-badge {
        display: inline-block;
        background: rgba(255,255,255,0.25);
        padding: 4px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    .dashboard-welcome .last-login {
        font-size: 13px;
        opacity: 0.85;
        margin-top: 6px;
    }
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #000;
        margin-bottom: 14px;
        margin-top: 6px;
    }
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    .action-card {
        background: #fff;
        border-radius: 16px;
        padding: 16px 8px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: transform 0.15s, box-shadow 0.15s;
        cursor: pointer;
        text-decoration: none;
        display: block;
        color: #333;
    }
    .action-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        text-decoration: none;
        color: #333;
    }
    .action-card .action-icon {
        font-size: 32px;
        margin-bottom: 8px;
        display: block;
    }
    .action-card .action-title {
        font-size: 12px;
        font-weight: 500;
        color: #444;
    }
    .menu-overview {
        background: #fff;
        border-radius: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow: hidden;
    }
    .menu-row {
        display: flex;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.15s;
        text-decoration: none;
        color: #000;
    }
    .menu-row:hover {
        background: #fafafa;
        text-decoration: none;
        color: #000;
    }
    .menu-row:last-child {
        border-bottom: 0;
    }
    .menu-row-icon {
        font-size: 22px;
        margin-right: 16px;
    }
    .menu-row-text {
        font-size: 15px;
        font-weight: 500;
        flex: 1;
    }
    .menu-row-arrow {
        font-size: 16px;
        color: #F97316;
        font-weight: 600;
    }

    /* Modal styles */
    .modal-content-modern {
        border-radius: 24px;
        border: 0;
        box-shadow: 0 20px 60px rgba(0,0,0,0.15);
    }
    .modal-header-modern {
        border-bottom: 0;
        text-align: center;
        padding: 24px 24px 0;
    }
    .modal-title-modern {
        font-size: 22px;
        font-weight: 700;
    }
    .modal-subtitle {
        font-size: 14px;
        color: #666;
        margin-top: 4px;
    }
    .modal-body-modern {
        padding: 20px 24px;
    }
    .modal-footer-modern {
        border-top: 0;
        padding: 0 24px 24px;
        display: flex;
        gap: 12px;
    }
    .modal-textarea {
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        padding: 12px;
        min-height: 120px;
        font-size: 14px;
        width: 100%;
        resize: vertical;
    }
    .modal-textarea:focus {
        outline: none;
        border-color: #F97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
    }
    .btn-submit {
        background: #F97316;
        border: 0;
        color: #fff;
        font-weight: 600;
        padding: 10px 24px;
        border-radius: 12px;
        flex: 1;
        cursor: pointer;
        transition: background 0.15s;
    }
    .btn-submit:hover {
        background: #e5670e;
        color: #fff;
    }
    .btn-cancel {
        background: #f5f5f5;
        border: 0;
        color: #666;
        font-weight: 600;
        padding: 10px 24px;
        border-radius: 12px;
        flex: 1;
        cursor: pointer;
    }
    .btn-cancel:hover {
        background: #e8e8e8;
    }
    .help-option {
        display: flex;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.15s;
    }
    .help-option:hover {
        background: #fafafa;
    }
    .help-option:last-child {
        border-bottom: 0;
    }
    .help-option-icon {
        font-size: 24px;
        margin-right: 16px;
    }
    .help-option-text {
        flex: 1;
        font-size: 16px;
        font-weight: 500;
        color: #000;
    }
    .help-option-arrow {
        font-size: 16px;
        color: #F97316;
        font-weight: 600;
    }

    @media (max-width: 576px) {
        .quick-actions-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
</style>

<div class="dashboard-welcome">
    <div class="d-flex justify-content-between align-items-center">
        <div class="greeting">{{ $greeting }}, {{ $user->first_name }}!</div>
        <span class="role-badge">{{ $user->member_role ?? 'Member' }}</span>
    </div>
    @if($lastLogin)
        <div class="last-login">Last Login: {{ \Carbon\Carbon::parse($lastLogin->date_logged_in)->format('M d, Y h:i A') }}</div>
    @else
        <div class="last-login">Last Login: N/A</div>
    @endif
</div>

<!-- Quick Actions -->
<div class="section-title">Quick Actions</div>
<div class="quick-actions-grid">
    <a href="{{ url('/profile') }}" class="action-card">
        <span class="action-icon">👤</span>
        <span class="action-title">Profile</span>
    </a>
    <a href="{{ url('/notice') }}" class="action-card">
        <span class="action-icon">📢</span>
        <span class="action-title">Announcement</span>
    </a>
    <a href="{{ url('/my-business') }}" class="action-card">
        <span class="action-icon">💼</span>
        <span class="action-title">My Business</span>
    </a>
    <a href="{{ url('/purchase') }}" class="action-card">
        <span class="action-icon">💰</span>
        <span class="action-title">Purchase</span>
    </a>
    <a href="{{ url('/ft-register') }}" class="action-card">
        <span class="action-icon">📝</span>
        <span class="action-title">Register First Timer</span>
    </a>
    <a href="{{ route('child-ceremony.index') }}" class="action-card">
        <span class="action-icon">🍼</span>
        <span class="action-title">Child Ceremony</span>
    </a>
    <a href="{{ url('/bus-route') }}" class="action-card">
        <span class="action-icon">🚌</span>
        <span class="action-title">Bus Route</span>
    </a>
    <a href="{{ url('/birthdays') }}" class="action-card">
        <span class="action-icon">🎂</span>
        <span class="action-title">Birthdays</span>
    </a>
    <a href="{{ url('/mregister') }}" class="action-card">
        <span class="action-icon">📋</span>
        <span class="action-title">Member Register</span>
    </a>
</div>

<!-- Quick Links -->
<div class="section-title">Quick Links</div>
<div class="menu-overview">
    <!-- Prayer Request -->
    <a href="javascript:void(0)" class="menu-row" data-toggle="modal" data-target="#prayerModal">
        <span class="menu-row-icon">🙏</span>
        <span class="menu-row-text">Send your prayer request</span>
        <span class="menu-row-arrow">→</span>
    </a>

    <!-- Church Attendance -->
    <a href="javascript:void(0)" class="menu-row" id="markAttendanceBtn">
        <span class="menu-row-icon">⛪</span>
        <span class="menu-row-text">I am in church today</span>
        <span class="menu-row-arrow">→</span>
    </a>

    <!-- Suggestion -->
    <a href="javascript:void(0)" class="menu-row" data-toggle="modal" data-target="#suggestionModal">
        <span class="menu-row-icon">💡</span>
        <span class="menu-row-text">Drop your Suggestion</span>
        <span class="menu-row-arrow">→</span>
    </a>
</div>

<!-- Prayer Request Modal -->
<div class="modal fade" id="prayerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-modern">
            <div class="modal-header-modern">
                <h5 class="modal-title-modern">Send Prayer Request</h5>
                <p class="modal-subtitle">Share your prayer request with us</p>
            </div>
            <form action="{{ route('home.prayer') }}" method="POST">
                @csrf
                <div class="modal-body-modern">
                    <textarea name="message" class="modal-textarea" placeholder="Type your prayer request here..." rows="5" required></textarea>
                </div>
                <div class="modal-footer-modern">
                    <button type="button" class="btn-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Suggestion Modal -->
<div class="modal fade" id="suggestionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-modern">
            <div class="modal-header-modern">
                <h5 class="modal-title-modern">Drop Your Suggestion</h5>
                <p class="modal-subtitle">Help us improve Smart Church</p>
            </div>
            <form action="{{ route('home.suggestion') }}" method="POST">
                @csrf
                <div class="modal-body-modern">
                    <textarea name="message" class="modal-textarea" placeholder="Type your suggestion here..." rows="5" required></textarea>
                </div>
                <div class="modal-footer-modern">
                    <button type="button" class="btn-cancel" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-submit">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Attendance Confirmation Modal -->
<div class="modal fade" id="attendanceConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content modal-content-modern">
            <div class="modal-header-modern">
                <h5 class="modal-title-modern">Church Attendance</h5>
                <p class="modal-subtitle">This action registers your attendance for today. Continue?</p>
            </div>
            <div class="modal-footer-modern">
                <button type="button" class="btn-cancel" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn-submit" id="confirmAttendanceBtn">Continue</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function() {
    // Mark Attendance
    $('#markAttendanceBtn').on('click', function(e) {
        e.preventDefault();
        $('#attendanceConfirmModal').modal('show');
    });

    $('#confirmAttendanceBtn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: '{{ route("home.attendance") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#attendanceConfirmModal').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: response.message
                    });
                }
            },
            error: function(xhr) {
                var msg = 'Server error. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: msg
                });
            },
            complete: function() {
                btn.prop('disabled', false).html('Continue');
            }
        });
    });

    // Handle form submissions via AJAX for prayer and suggestion
    $('form[action*="prayer"], form[action*="suggestion"]').on('submit', function(e) {
        // Let the form submit normally to the server (works with redirect)
        // This allows the success/error flash messages to work
    });
});
</script>
@endpush
