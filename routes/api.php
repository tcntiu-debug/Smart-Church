<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
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
use App\Http\Controllers\Api\FofController;
use App\Http\Controllers\Api\TransportController;
use App\Http\Controllers\Api\SubGroupController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\TouchpointController;
use App\Http\Controllers\Api\ChurchAttendanceController;
use App\Http\Controllers\Api\HomeController as ApiHomeController;
use App\Http\Controllers\Api\MapController as ApiMapController;
use App\Http\Controllers\Api\ChildrenChurchController;
use App\Http\Controllers\Api\WeekListController;

// ==========================================
// TEST ENDPOINTS
// ==========================================
Route::get('/test-db', function () {
    try {
        $count = DB::table('tiu_member')->count();
        return response()->json(['success' => true, 'message' => 'Connected!', 'members' => $count]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
});

// ==========================================
// AUTH ENDPOINTS (no token required)
// ==========================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/check-policy', [AuthController::class, 'checkPolicy']);
Route::post('/sign-policy', [AuthController::class, 'signPolicy']);
Route::post('/check-profile', [AuthController::class, 'checkProfile']);
Route::post('/update-profile', [AuthController::class, 'updateProfile']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/user', [AuthController::class, 'user']);

// ==========================================
// PROTECTED API ENDPOINTS (require token)
// ==========================================
Route::middleware(['verify.token'])->group(function () {
    
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

    // ===================== FOUNDATION OF FAITH (FOF) =====================
    Route::get('/fof/cohorts', [FofController::class, 'getCohorts']);
    Route::post('/fof/lookup', [FofController::class, 'lookup']);
    Route::post('/fof/register', [FofController::class, 'register']);
    Route::get('/fof/members', [FofController::class, 'members']);
    Route::get('/fof/students', [FofController::class, 'getStudents']);
    Route::post('/fof/mark-attendance', [FofController::class, 'markAttendance']);
    Route::get('/fof/attendance', [FofController::class, 'viewAttendance']);

    // ===================== TRANSPORT =====================
    Route::get('/transport/routes', [TransportController::class, 'getRoutes']);
    Route::get('/transport/stops', [TransportController::class, 'getStops']);
    Route::get('/transport/my-registration', [TransportController::class, 'myRegistration']);
    Route::post('/transport/register', [TransportController::class, 'saveRegistration']);
    Route::get('/transport/attendance-members', [TransportController::class, 'getAttendanceMembers']);
    Route::post('/transport/mark-attendance', [TransportController::class, 'markAttendance']);
    Route::get('/transport/attendance', [TransportController::class, 'viewAttendance']);

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
    Route::get('/map/bus-routes', [ApiMapController::class, 'getBusRoutes']);

    // ===================== CHILDREN CHURCH =====================
    Route::get('/children/search', [ChildrenChurchController::class, 'search']);
    Route::post('/children/check-in', [ChildrenChurchController::class, 'checkIn']);

    // ===================== WEEK LIST (First Timer Weekly) =====================
    Route::get('/week-list', [WeekListController::class, 'index']);
});
