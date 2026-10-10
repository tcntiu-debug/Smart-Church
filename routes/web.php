<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AirtimeController;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\FirstTimerController;
use App\Http\Controllers\PendingTasksController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\AdminAssignmentController;
use App\Http\Controllers\LeadViewController;
use App\Http\Controllers\WeekListController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\ManageDepartmentController;
use App\Http\Controllers\BirthdayController;
use App\Http\Controllers\AdminFirstTimerController;
use App\Http\Controllers\AdminGalleryController;
use App\Http\Controllers\AdminAirtimeController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\WeekTaskController;
use App\Http\Controllers\SubGroupController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\TouchpointController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ChildrenChurchController;
use App\Http\Controllers\ChildCeremonyController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\AttendanceAnalysisController;
use App\Http\Controllers\NotificationController;

// ============================================================
// PUBLIC ROUTES (No authentication required)
// ============================================================

Route::get('/', [CustomAuthController::class, 'showLoginForm'])->name('login');
Route::post('/', [CustomAuthController::class, 'login'])->name('login.post');
Route::post('/logout', [CustomAuthController::class, 'logout'])->name('logout');
Route::post('/password/email', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

// Public member registration (NO LOGIN REQUIRED)
Route::get('/register', [MemberController::class, 'publicRegister'])->name('member.public-register');
Route::post('/register', [MemberController::class, 'publicStore'])->name('member.public-store');

// Public member registration - Main public form (NO LOGIN REQUIRED)
Route::get('/mregister', [MemberController::class, 'publicRegister'])->name('mregister');
Route::post('/mregister', [MemberController::class, 'publicStore'])->name('member.public.store');

// Public attendance routes
Route::get('/attendance', [AttendanceController::class, 'clockIn']);
Route::post('/attendance/search', [AttendanceController::class, 'search']);
Route::post('/attendance/submit', [AttendanceController::class, 'submit']);

// Auto-unassign route (for testing or cron job via HTTP)
Route::get('/auto-unassign', function () {
    $trackings = App\Models\MemberTrackingFollowup::where('tiu_member_id', '!=', 0)->get();
    $unassignedCount = 0;
    $today = now();

    echo "<h1>Starting Auto-Unassign Process...</h1>";

    if ($trackings->isEmpty()) {
        echo "<p style='color:green;'>No currently assigned first-timers to check. Process complete.</p>";
        return;
    }

    foreach ($trackings as $tracking) {
        $firstTimer = App\Models\FirstTimer::find($tracking->first_timer_id);
        if (!$firstTimer) { continue; }

        $registrationDate = $firstTimer->register_date ?? $firstTimer->timeStamp_registered ?? $tracking->created_at;
        if (!$registrationDate) { continue; }

        $weeksPassed = (int) floor($today->diffInDays($registrationDate) / 7);
        echo "<p>Checking FT ID: <strong>{$tracking->first_timer_id}</strong>... Weeks: <strong>{$weeksPassed}</strong></p>";

        if ($weeksPassed >= 8) {
            try {
                \DB::beginTransaction();
                $tracking->delete();
                $firstTimer->update(['status' => 'Unassigned', 'status_change_date' => now()]);
                \DB::commit();
                $unassignedCount++;
                echo "<p style='color:green;'>&raquo; SUCCESS: Unassigned FT ID: {$tracking->first_timer_id}.</p>";
            } catch (\Exception $e) {
                \DB::rollBack();
                echo "<p style='color:red;'>&raquo; ERROR: {$e->getMessage()}</p>";
            }
        } else {
            echo "<p>&raquo; Skipped. Less than 8 weeks.</p>";
        }
        echo "<hr>";
    }

    echo "<h2>Complete. Total unassigned: {$unassignedCount}</h2>";
})->name('auto-unassign');

// Test routes
Route::get('/test-db', function () {
    return "Database connected! Total members: " . \App\Models\TiuMember::count();
});

Route::get('/simple-test', function () {
    return 'Laravel is working!';
});

Route::get('/db-test', function () {
    try {
        $count = DB::table('tiu_member')->count();
        return "Database connected! Total members: " . $count;
    } catch (\Exception $e) {
        return "Database error: " . $e->getMessage();
    }
});

Route::get('/session-test', function () {
    session(['test' => 'working']);
    return "Session set: " . session('test');
});

Route::get('/standalone-test', function () {
    return '<!DOCTYPE html>
    <html>
    <head>
        <title>Standalone Test</title>
    </head>
    <body>
        <h1>Standalone Page Test</h1>
        <p>This page does not use any Laravel layout.</p>
        <p>If you see this, the issue is in your layout file.</p>
    </body>
    </html>';
});

Route::get('/simple-layout-test', function () {
    return view('simple-test');
});

// Public Data Policy Page
Route::get('/data-policy', function () {
    $hasSigned = false;
    if (auth()->check()) {
        $hasSigned = \App\Models\Policy::where('tiu_member_id', auth()->user()->tiu_member_id)
            ->where('signature', 'yes')
            ->exists();
    }
    return view('data-policy', compact('hasSigned'));
})->name('data-policy');

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::post('/data-policy/accept', function () {
    $user = auth()->user();
    if ($user) {
        // Check if already signed
        $existing = \App\Models\Policy::where('tiu_member_id', $user->tiu_member_id)
            ->where('signature', 'yes')->first();
        if (!$existing) {
            // Insert or update the policy record
            \App\Models\Policy::updateOrCreate(
                ['tiu_member_id' => $user->tiu_member_id],
                [
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'signature' => 'yes',
                    'date_signed' => now()->format('Y-m-d H:i:s'),
                ]
            );
        }
        session(['data_policy_accepted' => true]);
    }
    return redirect()->back()->with('success', 'You have accepted the Data Privacy & Acceptable Use Policy.');
})->name('policy.accept-data-policy');

Route::post('/privacy-policy/sign', function () {
    $user = auth()->user();
    if ($user) {
        session(['privacy_policy_accepted' => true]);
    }
    return redirect('/home')->with('success', 'You have accepted the Privacy Policy.');
})->name('policy.sign');

// ============================================================
// AUTHENTICATED ROUTES (Login required)
// ============================================================

Route::middleware(['auth', 'profile.complete'])->group(function () {

    // ========== HOME DASHBOARD (Landing page for all roles) ==========
    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::post('/home/prayer', [HomeController::class, 'storePrayer'])->name('home.prayer');
    Route::post('/home/suggestion', [HomeController::class, 'storeSuggestion'])->name('home.suggestion');
    Route::post('/home/attendance', [HomeController::class, 'markAttendance'])->name('home.attendance');

    // Admin - Prayer/Suggestion list management
    Route::get('/prayer-suggestion-list', [HomeController::class, 'adminList'])->name('home.admin-list');
    Route::post('/prayer-suggestion-list/{id}/status', [HomeController::class, 'updateStatus'])->name('home.update-status');

    // Dashboard & Overview
    Route::get('/aoverview', [DashboardController::class, 'adminOverview'])->name('aoverview');
    Route::get('/mytask', [DashboardController::class, 'myTask'])->name('mytask');

    // My Tasks
    Route::get('/my-tasks', [MyTaskController::class, 'index'])->name('my-tasks.index');
    Route::post('/my-tasks/update', [MyTaskController::class, 'update'])->name('my-tasks.update');
    Route::get('/my-tasks/{id}/tasks', [MyTaskController::class, 'showTasks'])->name('my-tasks.tasks');
    Route::post('/my-tasks/update-task', [MyTaskController::class, 'updateTask'])->name('my-tasks.update-task');

    // Pending Tasks
    Route::get('/pending-tasks', [PendingTasksController::class, 'index'])->name('pending-tasks.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');

    // Airtime
    Route::post('/airtime-request', [AirtimeController::class, 'request'])->name('airtime.request');
    Route::get('/airtime-admin', [AirtimeController::class, 'admin'])->name('airtime.admin');

    // First Timer
    Route::get('/ft-register', [FirstTimerController::class, 'index'])->name('first-timer.index');
    Route::post('/ft-register', [FirstTimerController::class, 'store'])->name('first-timer.store');

    // ========== MEMBER MANAGEMENT (Admin Only) ==========
    // Admin registration routes (require login)
    Route::get('/member/register', [MemberController::class, 'create'])->name('member.register');
    Route::post('/member/store', [MemberController::class, 'store'])->name('member.store');
    
    // Member listing and management
    Route::get('/mview', [MemberController::class, 'index'])->name('member.index');
    // DataTables server-side data source (must stay above /mview/{id})
    Route::get('/mview/data', [MemberController::class, 'data'])->name('member.data');
    Route::get('/mview/{id}', [MemberController::class, 'show'])->name('member.show');
    Route::match(['get', 'post'], '/member/activate/{id}', [MemberController::class, 'activate'])->name('member.activate');
    Route::match(['get', 'post'], '/member/delete/{id}', [MemberController::class, 'destroy'])->name('member.delete');

    // Attendance
    Route::get('/attendance-report', [AttendanceController::class, 'report'])->name('attendance.report');

    // Announcements
    Route::get('/admin-announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/admin-announcements/store', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::post('/admin-announcements/update', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::get('/admin-announcements/delete/{id}', [AnnouncementController::class, 'destroy'])->name('announcements.delete');
    Route::get('/notice', [AnnouncementController::class, 'noticeBoard'])->name('announcements.notice');

    // Marketplace
    Route::get('/admin-marketplace', [MarketplaceController::class, 'admin'])->name('marketplace.admin');
    Route::post('/marketplace/approve', [MarketplaceController::class, 'approve'])->name('marketplace.approve');
    Route::post('/marketplace/deactivate', [MarketplaceController::class, 'deactivate'])->name('marketplace.deactivate');
    Route::match(['get', 'post'], '/my-business', [MarketplaceController::class, 'myBusinesses'])->name('marketplace.my-businesses');
    Route::post('/my-business/store', [MarketplaceController::class, 'storeBusiness'])->name('marketplace.store-business');
    Route::get('/my-business/deactivate/{id}', [MarketplaceController::class, 'requestDeactivation'])->name('marketplace.deactivate-request');
    Route::get('/purchase', [MarketplaceController::class, 'purchase'])->name('marketplace.purchase');
    Route::get('/my-business/deactivate-request', [MarketplaceController::class, 'requestDeactivation'])->name('marketplace.deactivate-request');

    // Admin Assignments
    Route::get('/admin-assign', [AdminAssignmentController::class, 'index'])->name('admin.assignments.index');
    Route::post('/admin-assign/assign', [AdminAssignmentController::class, 'assign'])->name('admin.assignments.assign');
    Route::post('/admin-assign/unassign', [AdminAssignmentController::class, 'unassign'])->name('admin.assignments.unassign');
    Route::get('/admin-assign-suggest', [AdminAssignmentController::class, 'suggest'])->name('admin.assignments.suggest');
    Route::post('/admin-assign-generate', [AdminAssignmentController::class, 'generatePairings'])->name('admin.assignments.generate');

    // Drag & Drop Assignments
    Route::get('/admin-assign-dragdrop', [AdminAssignmentController::class, 'dragDropIndex'])->name('admin.assignments.drag-drop');
    Route::post('/admin-assign-ajax-assign', [AdminAssignmentController::class, 'ajaxAssign'])->name('admin.assignments.ajax-assign');
    Route::post('/admin-assign-ajax-unassign', [AdminAssignmentController::class, 'ajaxUnassign'])->name('admin.assignments.ajax-unassign');
    Route::get('/admin-assign-ajax-suggest', [AdminAssignmentController::class, 'ajaxSuggest'])->name('admin.assignments.ajax-suggest');

    // Lead Views
    Route::get('/lead', [LeadViewController::class, 'departmentLead'])->name('lead.department');
    Route::get('/weeklist', [WeekListController::class, 'index'])->name('weeklist.index');
    Route::post('/weeklist/assign-caller', [WeekListController::class, 'saveCallerAssignment'])->name('weeklist.assign-caller');
    Route::get('/leaddepartment', [LeadViewController::class, 'departmentLead'])->name('lead.department');
    Route::get('/ftlead', [LeadViewController::class, 'firstTimerLead'])->name('lead.first-timer');

    // New split Lead Views (Department, House Fellowship, Cluster)
    Route::get('/lead/department', [LeadViewController::class, 'departmentMembersOnly'])->name('lead.department-only');
    Route::get('/lead/house-fellowship', [LeadViewController::class, 'houseFellowshipOnly'])->name('lead.house-fellowship');
    Route::get('/lead/cluster', [LeadViewController::class, 'clusterOnly'])->name('lead.cluster');

    // Resources
    Route::get('/public', [ResourceController::class, 'publicResources'])->name('resources.public');
    Route::get('/private', [ResourceController::class, 'privateResources'])->name('resources.private');
    Route::post('/resources/store', [ResourceController::class, 'store'])->name('resources.store');
    Route::get('/resources/delete/{id}', [ResourceController::class, 'destroy'])->name('resources.delete');
    Route::get('/manage-resources', [ResourceController::class, 'admin'])->name('resources.admin');
    Route::post('/resources/upload-ajax', [ResourceController::class, 'uploadAjax'])->name('resources.upload-ajax');
    Route::post('/resources/share', [ResourceController::class, 'share'])->name('resources.share');
    Route::post('/resources/delete-ajax', [ResourceController::class, 'deleteAjax'])->name('resources.delete-ajax');

    // Admin First Timer
    Route::get('/fview', [AdminFirstTimerController::class, 'index'])->name('admin.first-timers');
    Route::get('/fviewupdate', [AdminFirstTimerController::class, 'updates'])->name('admin.first-timers-update');
    Route::get('/fthangout', [AdminFirstTimerController::class, 'hangout'])->name('admin.hangout');
    Route::get('/fthangout/data', [AdminFirstTimerController::class, 'getHangoutData'])->name('admin.hangout.data');
    Route::post('/admin/ft/hangout-register', [AdminFirstTimerController::class, 'hangoutRegister'])->name('admin.ft.hangout-register');
    Route::post('/admin/ft/perform-action', [AdminFirstTimerController::class, 'performAction'])->name('admin.ft.perform-action');
    Route::post('/admin/ft/update-details', [AdminFirstTimerController::class, 'updateDetails'])->name('admin.ft.update-details');
    Route::get('/admin/ft/chat-history', [AdminFirstTimerController::class, 'getChatHistory'])->name('admin.ft.chat');
    Route::post('/admin/ft/chat-send', [AdminFirstTimerController::class, 'submitChatMessage'])->name('admin.ft.chat-send');
    Route::get('/admin/ft/occupations', [AdminFirstTimerController::class, 'getOccupations'])->name('admin.ft.occupations');
    Route::post('/admin/ft/guides', [AdminFirstTimerController::class, 'getGuides'])->name('admin.ft.guides');

    // Gallery
    Route::get('/gallery', [AdminGalleryController::class, 'index'])->name('admin.gallery');
    Route::post('/gallery/store', [AdminGalleryController::class, 'store'])->name('admin.gallery.store');
    Route::get('/gallery/delete/{id}', [AdminGalleryController::class, 'destroy'])->name('admin.gallery.delete');

    // Admin Airtime
    Route::match(['get', 'post'], '/airtime_admin', [AdminAirtimeController::class, 'index'])->name('admin.airtime');

    // Departments Management
    Route::get('/manage-departments', [ManageDepartmentController::class, 'index'])->name('departments.index');
    Route::get('/config-departments', [ManageDepartmentController::class, 'index'])->name('departments.config');
    Route::post('/manage-departments/store', [ManageDepartmentController::class, 'store'])->name('departments.store');
    Route::post('/manage-departments/update', [ManageDepartmentController::class, 'update'])->name('departments.update');
    Route::get('/manage-departments/delete/{id}', [ManageDepartmentController::class, 'destroy'])->name('departments.delete');
    Route::get('/departments/members', [ManageDepartmentController::class, 'getMembers'])->name('departments.members');

    // Department List View (with cascading filters, chart, and member table)
    Route::get('/department-view', [ManageDepartmentController::class, 'listView'])->name('departments.list');
    Route::get('/department-view/get-departments', [ManageDepartmentController::class, 'getDepartmentsByType']);
    Route::get('/department-view/chart-data', [ManageDepartmentController::class, 'getChartData']);
    Route::get('/department-view/fetch-members', [ManageDepartmentController::class, 'fetchMembers']);

    // Community Management
    Route::get('/config-community', [CommunityController::class, 'index'])->name('community.index');
    Route::post('/config-community/store', [CommunityController::class, 'store'])->name('community.store');
    Route::get('/config-community/delete/{id}', [CommunityController::class, 'destroy'])->name('community.delete');

    // Settings
    Route::match(['get', 'post'], '/setting', [SettingController::class, 'index'])->name('settings.index');

    // Week Tasks
    Route::get('/etask', [WeekTaskController::class, 'index'])->name('week-task.index');
    Route::post('/etask/store', [WeekTaskController::class, 'store'])->name('week-task.store');
    Route::get('/etask/delete', [WeekTaskController::class, 'destroy'])->name('week-task.delete');

    // Sub Groups
    Route::get('/group', [SubGroupController::class, 'index'])->name('subgroup.index');
    Route::get('/group/fetch', [SubGroupController::class, 'fetchAssignmentData'])->name('subgroup.fetch');
    Route::post('/group/create', [SubGroupController::class, 'create'])->name('subgroup.create');
    Route::post('/group/delete', [SubGroupController::class, 'destroy'])->name('subgroup.delete');
    Route::post('/group/assign', [SubGroupController::class, 'updateAssignment'])->name('subgroup.assign');
    Route::post('/group/promote', [SubGroupController::class, 'promoteToLead'])->name('subgroup.promote');

    // Birthdays
    Route::get('/birthdays', [BirthdayController::class, 'index'])->name('birthdays.index');
    Route::get('/birthdays/filter', [BirthdayController::class, 'filter'])->name('birthdays.filter');
    Route::post('/birthdays/send-wish', [BirthdayController::class, 'sendWish'])->name('birthdays.send-wish');

    // Notifications (in-app bell)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Touchpoint
    Route::get('/mtouchpoint', [TouchpointController::class, 'index'])->name('touchpoint');

    // Charts & Reports
    Route::get('/report-barcharts', [ChartController::class, 'barcharts'])->name('charts.barcharts');
    Route::get('/report-piecharts', [ChartController::class, 'piecharts'])->name('charts.piecharts');

    // ========== MAPS ==========
    // Map view - Community Map (full Leaflet-based map with community list, markers, demographics, member listing)
    Route::get('/map-view', [MapController::class, 'communityMap'])->name('maps.community');

    // ========== ATTENDANCE ANALYSIS ==========
    // Attendance Analysis - view present/absent members per department/house-fellowship/cluster by date
    Route::get('/attendance-analysis', [AttendanceAnalysisController::class, 'index'])->name('attendance.analysis');
    
    // AJAX endpoint for fetching community members
    Route::post('/map-view/get-members', [MapController::class, 'getCommunityMembers'])->name('maps.community.members');
    
    // My Inbox (Coming Soon)
    Route::get('/my-inbox', function () {
        return view('placeholder', ['title' => 'My Inbox', 'message' => 'This page is coming soon.']);
    })->name('inbox');

    // ========== CHILD CEREMONY (Naming / Dedication) ==========
    Route::get('/child-ceremony', [ChildCeremonyController::class, 'index'])->name('child-ceremony.index');
    Route::post('/child-ceremony/store', [ChildCeremonyController::class, 'store'])->name('child-ceremony.store');
    Route::get('/child-ceremony/admin', [ChildCeremonyController::class, 'adminList'])->name('child-ceremony.admin');
    Route::get('/child-ceremony/admin/detail/{id}', [ChildCeremonyController::class, 'getDetail'])->name('child-ceremony.detail');
    Route::post('/child-ceremony/admin/mark-completed/{id}', [ChildCeremonyController::class, 'markCompleted'])->name('child-ceremony.mark-completed');
    Route::post('/child-ceremony/admin/mark-pending/{id}', [ChildCeremonyController::class, 'markPending'])->name('child-ceremony.mark-pending');

    // ========== CHILDREN CHURCH ==========
    Route::get('/children-church', [ChildrenChurchController::class, 'index'])->name('children-church.index');
    Route::get('/children-church/search', [ChildrenChurchController::class, 'search'])->name('children-church.search');
    Route::post('/children-church/mark-attendance', [ChildrenChurchController::class, 'markAttendance'])->name('children-church.mark-attendance');
    Route::post('/children-church/store', [ChildrenChurchController::class, 'store'])->name('children-church.store');
    Route::get('/children-church/today-attendance', [ChildrenChurchController::class, 'todayAttendance'])->name('children-church.today-attendance');
    Route::get('/children-church/manage', [ChildrenChurchController::class, 'manage'])->name('children-church.manage');
    Route::post('/children-church/update/{id}', [ChildrenChurchController::class, 'update'])->name('children-church.update');

});
