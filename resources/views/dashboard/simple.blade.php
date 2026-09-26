@extends('layouts.app')

@section('content')
<style>
    .dashboard-card {
        transition: transform 0.2s;
        cursor: pointer;
        border-radius: 16px;
        border: none;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .card-icon {
        font-size: 48px;
        margin-bottom: 15px;
    }
    .quick-action {
        background: #f8f9fa;
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        transition: all 0.2s;
    }
    .quick-action:hover {
        background: #e9ecef;
        transform: translateY(-3px);
    }
    .greeting-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 8px 16px;
        border-radius: 30px;
        font-size: 14px;
    }
</style>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0">Welcome back, {{ session('first_name', 'User') }}!</h3>
                    <p class="text-muted">Here's what's happening today.</p>
                </div>
                <div class="greeting-badge">
                    <i class="fas fa-calendar-alt me-2"></i>{{ date('l, F j, Y') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-primary text-white">
                <div class="card-body">
                    <div class="card-icon">📋</div>
                    <h5 class="card-title">My Tasks</h5>
                    <p class="card-text">View and manage your tasks</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-success text-white">
                <div class="card-body">
                    <div class="card-icon">📝</div>
                    <h5 class="card-title">Register First Timer</h5>
                    <p class="card-text">Add new first-time visitors</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-info text-white">
                <div class="card-body">
                    <div class="card-icon">📱</div>
                    <h5 class="card-title">Airtime Topup</h5>
                    <p class="card-text">Request weekly airtime</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card dashboard-card bg-warning text-white">
                <div class="card-body">
                    <div class="card-icon">👤</div>
                    <h5 class="card-title">My Profile</h5>
                    <p class="card-text">Update your profile</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="quick-action" onclick="window.location.href='{{ route('my-tasks.index') }}'">
                                <i class="fas fa-tasks fa-2x text-primary mb-2"></i>
                                <div>My Tasks</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="quick-action" onclick="window.location.href='{{ route('first-timer.index') }}'">
                                <i class="fas fa-user-plus fa-2x text-success mb-2"></i>
                                <div>Register First Timer</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="quick-action" onclick="window.location.href='{{ route('airtime.request') }}'">
                                <i class="fas fa-phone-alt fa-2x text-info mb-2"></i>
                                <div>Airtime Topup</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="quick-action" onclick="window.location.href='{{ route('profile.index') }}'">
                                <i class="fas fa-user-edit fa-2x text-warning mb-2"></i>
                                <div>Edit Profile</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="quick-action" onclick="window.location.href='{{ route('pending-tasks.index') }}'">
                                <i class="fas fa-clock fa-2x text-danger mb-2"></i>
                                <div>Pending Tasks</div>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="quick-action" onclick="window.location.href='{{ route('birthdays.index') }}'">
                                <i class="fas fa-birthday-cake fa-2x text-pink mb-2"></i>
                                <div>Birthdays</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity / Tour Guide -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">📊 Your Progress</h5>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-3x text-success mb-3"></i>
                    <p>Complete your tasks to earn points and move up!</p>
                    <button class="btn btn-outline-primary btn-sm" onclick="alert('Progress feature coming soon!')">
                        View Details
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">🎓 Take a Tour</h5>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-map-signs fa-3x text-info mb-3"></i>
                    <p>New to the platform? Take a guided tour to learn the ropes.</p>
                    <button class="btn btn-outline-info btn-sm" onclick="startTour()">
                        Start Tour
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function startTour() {
        let tourSteps = [
            "Welcome to the TIU Dashboard!",
            "Use the side menu to navigate to different sections.",
            "My Tasks shows first-timers assigned to you.",
            "Register First Timer adds new visitors to the system.",
            "Airtime Topup lets you request weekly airtime credit.",
            "Complete tasks to earn points and track progress!"
        ];
        
        let currentStep = 0;
        alert(tourSteps[currentStep]);
        
        // Simple tour - you can enhance this with a proper tour library
        const interval = setInterval(() => {
            currentStep++;
            if (currentStep < tourSteps.length) {
                alert(tourSteps[currentStep]);
            } else {
                clearInterval(interval);
                alert("Tour complete! You're ready to go!");
            }
        }, 3000);
    }
</script>
@endsection