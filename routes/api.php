<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\FirstTimerController;
use App\Http\Controllers\AirtimeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\PendingTasksController;
use App\Http\Controllers\ProfileController;

// ==========================================
// NEW API CONTROLLERS
// ==========================================
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BirthdayController;
use App\Http\Controllers\Api\SubGroupController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\TouchpointController;
use App\Http\Controllers\Api\ChurchAttendanceController;
use App\Http\Controllers\Api\HomeController as ApiHomeController;
use App\Http\Controllers\Api\MapController as ApiMapController;
use App\Http\Controllers\Api\ChildrenChurchController;
use App\Http\Controllers\Api\PublicAnnouncementController;
use App\Http\Controllers\Api\WeekListController;

// ==========================================
// AUTH ENDPOINTS (no token required)
// ==========================================
// Only login is genuinely anonymous. Everything else on this controller reads or
// writes the member named by the request, so those routes sit behind the token
// instead - while /api/user and /api/update-profile were listed here, anyone could
// read or rewrite any member's profile with no credential at all, because the
// member id came from the query string or the body.
Route::post('/login', [AuthController::class, 'login']);

// Registration is admin-only by design: the controller answers 403 to every
// caller, so this route exposes nothing.
Route::post('/register', [AuthController::class, 'register']);

// Logout is a no-op for a stateless token (the client drops its copy); it stays
// public so a device holding a broken token can still clear its own session.
Route::post('/logout', [AuthController::class, 'logout']);

// /api/test-db used to live here: it returned the member count and, on failure,
// the raw database exception message to any anonymous caller. Use
// `php artisan tinker` or the application log when you need to smoke-test the DB.

// ==========================================
// PUBLIC READ-ONLY ENDPOINTS (no token required)
// ==========================================
// The notice board for the church's public site (the Vercel build). Unlike
// /notice in web.php this is anonymous, so it returns published announcements
// only and never touches a member row. Rate limited by the `api` group.
Route::get('/public/announcements', [PublicAnnouncementController::class, 'index']);

// ==========================================
// PROTECTED API ENDPOINTS (require token)
// ==========================================
Route::middleware(['verify.token'])->group(function () {
    
    // ===================== ACCOUNT / SESSION =====================
    // Behind the token, user_id comes from the verified claims, so these can no
    // longer be pointed at another member by passing a user_id (the guard merges
    // its claims over anything the client sends).
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/check-policy', [AuthController::class, 'checkPolicy']);
    Route::post('/sign-policy', [AuthController::class, 'signPolicy']);
    Route::post('/check-profile', [AuthController::class, 'checkProfile']);

    // ===================== MY TASKS =====================
    Route::get('/my-tasks', [MyTaskController::class, 'getApiIndex']);
    Route::get('/my-tasks/{id}/details', [MyTaskController::class, 'getApiFirstTimerDetails']);
    Route::get('/my-tasks/{id}/tasks', [MyTaskController::class, 'getApiTasks']);
    Route::post('/my-tasks/update-task', [MyTaskController::class, 'updateTaskApi']);
    Route::post('/my-tasks/update-field', [MyTaskController::class, 'updateFieldApi']);
    Route::get('/my-tasks/{id}/progress', [MyTaskController::class, 'getApiProgress']);
    
    // ===================== FIRST TIMER =====================
    Route::get('/church-types', [FirstTimerController::class, 'getChurchTypes']);
    Route::get('/occupations', [FirstTimerController::class, 'getOccupations']);
    Route::post('/register-first-timer', [FirstTimerController::class, 'store']);
    Route::get('/first-timers', [FirstTimerController::class, 'index']);
    Route::get('/first-timers/{id}', [FirstTimerController::class, 'show']);
    
    // ===================== AIRTIME =====================
    Route::post('/airtime-request', [AirtimeController::class, 'request']);
    Route::get('/airtime-history', [AirtimeController::class, 'history']);
    Route::get('/airtime-check', [AirtimeController::class, 'checkAvailability']);
    
    // ===================== PENDING TASKS =====================
    Route::get('/pending-tasks', [PendingTasksController::class, 'getApiPendingTasks']);
    Route::get('/pending-tasks/touchpoints', [PendingTasksController::class, 'getApiTouchpoints']);
    
    // ===================== PROFILE =====================
    Route::get('/profile', [ProfileController::class, 'getApiProfile']);
    Route::post('/profile/update', [ProfileController::class, 'updateApiProfile']);
    Route::get('/profile/departments', [ProfileController::class, 'getApiDepartments']);
    Route::get('/profile/communities', [ProfileController::class, 'getApiCommunities']);
    Route::get('/profile/occupations', [ProfileController::class, 'getApiOccupations']);
    Route::get('/profile/church-types', [ProfileController::class, 'getApiChurchTypes']);
    Route::get('/profile/sub-groups', [ProfileController::class, 'getApiSubGroups']);

    // ===================== ANNOUNCEMENTS =====================
    Route::get('/announcements', [AnnouncementController::class, 'index']);
    Route::post('/announcements', [AnnouncementController::class, 'store']);
    Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy']);

    // ===================== BIRTHDAYS =====================
    Route::get('/birthdays', [BirthdayController::class, 'index']);
    Route::get('/birthdays/upcoming', [BirthdayController::class, 'upcoming']);
    Route::get('/birthdays/month/{month}', [BirthdayController::class, 'filterByMonth']);
    Route::post('/birthdays/send-wish', [BirthdayController::class, 'sendWish']);

    // ===================== SUB GROUPS =====================
    Route::get('/sub-groups', [SubGroupController::class, 'index']);
    Route::get('/sub-groups/members', [SubGroupController::class, 'getMembers']);
    Route::get('/sub-groups/my-groups', [SubGroupController::class, 'myGroups']);

    // ===================== RESOURCES =====================
    Route::get('/resources/public', [ResourceController::class, 'publicResources']);
    Route::get('/resources/private', [ResourceController::class, 'privateResources']);

    // ===================== TOUCHPOINTS =====================
    Route::get('/touchpoints', [TouchpointController::class, 'index']);

    // ===================== CHURCH ATTENDANCE =====================
    Route::post('/attendance/mark', [ChurchAttendanceController::class, 'markAttendance']);
    Route::get('/attendance/today', [ChurchAttendanceController::class, 'checkToday']);
    Route::get('/attendance/records', [ChurchAttendanceController::class, 'viewAttendance']);
    Route::get('/attendance/search', [ChurchAttendanceController::class, 'search']);

    // ===================== PRAYERS / SUGGESTIONS =====================
    Route::post('/prayers', [ApiHomeController::class, 'storePrayer']);
    Route::post('/suggestions', [ApiHomeController::class, 'storeSuggestion']);

    // ===================== MAPS =====================
    Route::get('/map/communities', [ApiMapController::class, 'getCommunities']);
    Route::get('/map/community-members', [ApiMapController::class, 'getCommunityMembers']);

    // ===================== CHILDREN CHURCH =====================
    Route::get('/children/search', [ChildrenChurchController::class, 'search']);
    Route::post('/children/check-in', [ChildrenChurchController::class, 'checkIn']);

    // ===================== WEEK LIST (First Timer Weekly) =====================
    Route::get('/week-list', [WeekListController::class, 'index']);
});
