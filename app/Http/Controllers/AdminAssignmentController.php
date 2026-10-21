<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdminAssignmentController extends Controller
{
    /**
     * Writes the "a first timer is now yours to follow up" alerts (item 11).
     *
     * @var \App\Services\NotificationService
     */
    private $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;

        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $userCampusId = $user->campus_id ?? 0;

        // Authorize
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/aoverview')->with('error', 'Access Denied');
        }

        $search = $request->get('search', '');
        $status = $request->get('status', '');

        // Get unassigned first-timers
        $unassignedQuery = DB::table('first_timer')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 'Not Assigned');
            })
            ->forCurrentCampus('campus_id', $userCampusId)
            ->orderBy('first_name', 'asc');

        if (!empty($search)) {
            $unassignedQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $unassigned = $unassignedQuery->get();

        // Get all active TIU members (guides)
        $guides = DB::table('tiu_member')
            ->where('status', 1)
            ->forCurrentCampus('campus_id', $userCampusId)
            ->whereNotIn('member_role', ['Super User', 'Admin'])
            ->orderBy('first_name', 'asc')
            ->get();

        // Get assignments with guide + first timer info
        $assignmentsQuery = DB::table('member_tracking_followup as mtf')
            ->join('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
            ->join('tiu_member as tm', 'mtf.tiu_member_id', '=', 'tm.tiu_member_id')
            ->forCurrentCampus('ft.campus_id', $userCampusId)
            ->select(
                'mtf.*',
                'ft.first_name as ft_first_name',
                'ft.last_name as ft_last_name',
                'ft.phone_number as ft_phone',
                'ft.status as ft_status',
                'ft.first_timer_id',
                'tm.first_name as guide_first_name',
                'tm.last_name as guide_last_name'
            )
            ->orderBy('tm.first_name', 'asc')
            ->orderBy('ft.first_name', 'asc');

        if (!empty($search)) {
            $assignmentsQuery->where(function ($q) use ($search) {
                $q->where('ft.first_name', 'like', "%{$search}%")
                  ->orWhere('ft.last_name', 'like', "%{$search}%")
                  ->orWhere('tm.first_name', 'like', "%{$search}%")
                  ->orWhere('tm.last_name', 'like', "%{$search}%");
            });
        }

        if (!empty($status) && $status === 'completed') {
            $assignmentsQuery->where('mtf.followup_rank', '>', 1);
        } elseif (!empty($status) && $status === 'active') {
            $assignmentsQuery->where('mtf.followup_rank', 1);
        }

        $assignments = $assignmentsQuery->paginate(100)->appends(request()->query());

        return view('admin.assignments.index', compact('unassigned', 'guides', 'assignments', 'search', 'status'));
    }

    /**
     * AJAX: Drag-drop assignment (mirrors assign_timer.php)
     */
    public function ajaxAssign(Request $request)
    {
        $request->validate([
            'Guild_name'       => 'required|integer',
            'first_timer_id'   => 'required|integer',
            'full_name'        => 'required|string',
            'status'           => 'required|string',
        ]);

        try {
            $guideId      = $request->Guild_name;
            $firstTimerId = $request->first_timer_id;
            $ftFullName   = $request->full_name;
            $currentStatus = $request->status;

            // Check if already assigned
            $existing = DB::table('member_tracking_followup')
                ->where('first_timer_id', $firstTimerId)
                ->exists();

            $newStatus = $existing ? 'Reassigned' : 'Assigned';

            // Who followed this first timer up before, so saving for the same
            // guide again does not send a second alert (item 11).
            $previousGuideId = $existing
                ? (int) DB::table('member_tracking_followup')->where('first_timer_id', $firstTimerId)->value('tiu_member_id')
                : 0;

            if (!$existing) {
                // NEW ASSIGNMENT — fetch Week 1 tasks from task table (filtered by campus)
                $followupResponseJson = '{}';
                $userCampusId = Auth::user()->campus_id ?? 0;
                $taskRow = DB::table('task')
                    ->where('wid', 1)
                    ->forCurrentCampus('campus_id', $userCampusId)
                    ->value('questions');
                if ($taskRow) {
                    $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                    $decoded = json_decode($rawJson, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        foreach ($decoded as &$td) {
                            if (is_array($td)) $td['logs'] = [];
                        }
                        unset($td);
                        $followupResponseJson = json_encode(['week1' => $decoded]);
                    }
                }

                $assignment = [
                    'first_timer_id'        => $firstTimerId,
                    'tiu_member_id'         => $guideId,
                    'followup_rank'         => 1,
                    'timeStamp_registered'  => now(),
                    'followup_response_new' => $followupResponseJson,
                ];

                // Rows written before it existed rely on the column default;
                // production may not have one, so only write it when it is there.
                if ($this->hasColumn('member_tracking_followup', 'department_id')) {
                    $assignment['department_id'] = self::DEPARTMENT_ID;
                }

                DB::table('member_tracking_followup')->insert($assignment);
            } else {
                // RE-ASSIGNMENT
                $currentJson = DB::table('member_tracking_followup')
                    ->where('first_timer_id', $firstTimerId)
                    ->value('followup_response_new');

                if (strlen($currentJson ?? '') < 10) {
                    // Broken JSON → rebuild (filtered by campus)
                    $userCampusId = Auth::user()->campus_id ?? 0;
                    $taskRow = DB::table('task')
                        ->where('wid', 1)
                        ->forCurrentCampus('campus_id', $userCampusId)
                        ->value('questions');
                    if ($taskRow) {
                        $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                        $decoded = json_decode($rawJson, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            foreach ($decoded as &$td) {
                                if (is_array($td)) $td['logs'] = [];
                            }
                            unset($td);
                            $followupResponseJson = json_encode(['week1' => $decoded]);
                            DB::table('member_tracking_followup')
                                ->where('first_timer_id', $firstTimerId)
                                ->update([
                                    'tiu_member_id'         => $guideId,
                                    'followup_rank'         => 1,
                                    'timeStamp_registered'  => now(),
                                    'followup_response_new' => $followupResponseJson,
                                ]);
                        }
                    }
                } else {
                    DB::table('member_tracking_followup')
                        ->where('first_timer_id', $firstTimerId)
                        ->update([
                            'tiu_member_id'        => $guideId,
                            'followup_rank'        => 1,
                            'timeStamp_registered' => now(),
                        ]);
                }
            }

            // Update first_timer status
            DB::table('first_timer')
                ->where('first_timer_id', $firstTimerId)
                ->update(['status' => $newStatus, 'status_change_date' => now()]);

            // Item 11: the guide is told about the first timer they now follow up.
            $notified = (int) $guideId !== $previousGuideId
                ? $this->notifyGuide($guideId, $ftFullName, $firstTimerId)
                : false;

            return response()->json([
                'status'   => 'success',
                'notified' => $notified,
                'message'  => "$ftFullName has been successfully $newStatus.".($notified ? ' The guide has been notified.' : ''),
            ]);
        } catch (\Exception $e) {
            Log::error('ajaxAssign error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Drag-drop unassignment (mirrors unassign_timer.php)
     */
    public function ajaxUnassign(Request $request)
    {
        $request->validate(['first_timer_id' => 'required|integer']);

        try {
            $firstTimerId = $request->first_timer_id;

            DB::table('member_tracking_followup')
                ->where('first_timer_id', $firstTimerId)
                ->update(['tiu_member_id' => 0]);

            DB::table('first_timer')
                ->where('first_timer_id', $firstTimerId)
                ->update(['status' => 'Unassigned', 'status_change_date' => now()]);

            $timerData = DB::table('first_timer')
                ->where('first_timer_id', $firstTimerId)
                ->first();

            return response()->json([
                'status'     => 'success',
                'message'    => 'First-timer has been unassigned.',
                'timer_data' => $timerData,
            ]);
        } catch (\Exception $e) {
            Log::error('ajaxUnassign error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Suggest assignments (mirrors suggest_assignments.php)
     */
    public function ajaxSuggest(Request $request)
    {
        try {
            $guideGender     = $request->get('guide_gender', '');
            $guideChurchType = $request->get('guide_church_type', '');
            $guideOccupation = $request->get('guide_occupation', '');

            $ftGender     = $request->get('ft_gender', '');
            $ftChurchType = $request->get('ft_church_type', '');

            $user = Auth::user();
            $userCampusId = $user->campus_id ?? session('campus_id', 0);

            // Guides pool
            $guideQuery = DB::table('tiu_member as tm')
                ->leftJoin('member_tracking_followup as mtf', 'tm.tiu_member_id', '=', 'mtf.tiu_member_id')
                ->where('tm.department_name', 'LIKE', '%"23"%')
                ->forCurrentCampus('tm.campus_id', $userCampusId)
                ->select(
                    'tm.tiu_member_id',
                    'tm.first_name',
                    'tm.last_name',
                    'tm.gender',
                    'tm.church_type_id',
                    DB::raw('COUNT(mtf.tracking_id) as active_assignments'),
                    DB::raw('SUM(CASE WHEN mtf.timeStamp_registered < DATE_SUB(NOW(), INTERVAL 6 WEEK) THEN 1 ELSE 0 END) as old_assignments')
                );

            if (!empty($guideGender))     $guideQuery->where('tm.gender', $guideGender);
            if (!empty($guideChurchType)) $guideQuery->where('tm.church_type_id', $guideChurchType);
            if (!empty($guideOccupation)) $guideQuery->where('tm.occupation', $guideOccupation);

            $guidesPool = $guideQuery->groupBy('tm.tiu_member_id', 'tm.first_name', 'tm.last_name', 'tm.gender', 'tm.church_type_id')
                ->orderBy('tm.first_name')
                ->get();

            if ($guidesPool->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No available guides match the current filters.']);
            }

            // Unassigned first timers
            $ftQuery = DB::table('first_timer')
                ->whereIn('status', ['Unassigned', 'Not Assigned'])
                ->forCurrentCampus('campus_id', $userCampusId)
                ->whereNotIn('first_timer_id', function ($q) {
                    $q->select('first_timer_id')->from('member_tracking_followup')->where('tiu_member_id', '>', 0);
                });

            if (!empty($ftGender))     $ftQuery->where('gender', $ftGender);
            if (!empty($ftChurchType)) $ftQuery->where('church_type_id', $ftChurchType);

            $unassignedTimers = $ftQuery->get(['first_timer_id', 'first_name', 'last_name', 'gender', 'church_type_id', 'status']);

            if ($unassignedTimers->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No unassigned first-timers match the current filters.']);
            }

            // Matching algorithm
            $suggestions = [];
            $guideWorkload = $guidesPool->pluck('active_assignments', 'tiu_member_id')->toArray();

            foreach ($unassignedTimers as $ft) {
                $bestScore = -1;
                $bestGuideId = null;

                foreach ($guidesPool as $guide) {
                    $score = 0;
                    $workload = $guideWorkload[$guide->tiu_member_id] ?? 0;

                    if ($workload == 0) $score += 100;
                    elseif ($workload == 1) $score += 50;
                    elseif ($workload == 2) $score += 25;
                    else $score += max(0, 10 - $workload);

                    if (!empty($ft->gender) && $ft->gender === $guide->gender) $score += 30;
                    if (!empty($ft->church_type_id) && $ft->church_type_id == $guide->church_type_id) $score += 20;
                    if (($guide->old_assignments ?? 0) > 0) $score -= 15;

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestGuideId = $guide->tiu_member_id;
                    }
                }

                if ($bestGuideId !== null) {
                    $bestGuide = $guidesPool->firstWhere('tiu_member_id', $bestGuideId);
                    $suggestions[] = [
                        'first_timer_id'      => $ft->first_timer_id,
                        'first_timer_name'    => $ft->first_name . ' ' . $ft->last_name,
                        'first_timer_status'  => $ft->status,
                        'suggested_guide_id'  => $bestGuideId,
                        'suggested_guide_name'=> $bestGuide->first_name . ' ' . $bestGuide->last_name,
                        'score'               => $bestScore,
                    ];
                    $guideWorkload[$bestGuideId]++;
                }
            }

            return response()->json(['success' => true, 'suggestions' => $suggestions]);
        } catch (\Exception $e) {
            Log::error('ajaxSuggest error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function assign(Request $request)
    {
        $request->validate([
            'first_timer_id' => 'required|integer',
            'guide_id' => 'required|integer',
        ]);

        try {
            $ftId = $request->first_timer_id;
            $guideId = $request->guide_id;
            $userCampusId = Auth::user()->campus_id ?? 0;

            // Check if already assigned
            $existing = DB::table('member_tracking_followup')
                ->where('first_timer_id', $ftId)
                ->exists();

            if ($existing) {
                return redirect()->back()->with('error', 'First timer already assigned!');
            }

            // Generate the followup response JSON from task table (filtered by campus)
            $followupResponse = $this->generateDefaultFollowupResponse();
            $taskRow = DB::table('task')
                ->where('wid', 1)
                ->forCurrentCampus('campus_id', $userCampusId)
                ->value('questions');
            if ($taskRow) {
                $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                $decoded = json_decode($rawJson, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as &$td) {
                        if (is_array($td)) $td['logs'] = [];
                    }
                    unset($td);
                    $followupResponse = ['week1' => $decoded];
                }
            }

            DB::table('member_tracking_followup')->insert([
                'tiu_member_id' => $guideId,
                'first_timer_id' => $ftId,
                'followup_rank' => 1,
                'followup_response_new' => json_encode($followupResponse),
                'timeStamp_registered' => now(),
            ]);

            // Update first timer status
            DB::table('first_timer')
                ->where('first_timer_id', $ftId)
                ->update(['status' => 'Assigned']);

            // Item 11: the guide hears about the first timer they now follow up.
            $firstTimer = DB::table('first_timer')
                ->where('first_timer_id', $ftId)
                ->first(['first_name', 'last_name']);

            $notified = $this->notifyGuide($guideId, $this->personName($firstTimer, $ftId), $ftId);

            return redirect()->back()->with(
                'success',
                'First timer assigned successfully!'.($notified ? ' The guide has been notified.' : '')
            );
        } catch (\Exception $e) {
            Log::error('Assign error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function unassign(Request $request)
    {
        $request->validate([
            'tracking_id' => 'required|integer',
        ]);

        try {
            $tracking = DB::table('member_tracking_followup')
                ->where('tracking_id', $request->tracking_id)
                ->first();

            if ($tracking) {
                DB::table('first_timer')
                    ->where('first_timer_id', $tracking->first_timer_id)
                    ->update(['status' => 'Not Assigned']);

                DB::table('member_tracking_followup')
                    ->where('tracking_id', $request->tracking_id)
                    ->delete();
            }

            return redirect()->back()->with('success', 'Assignment removed!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * The department every follow-up on this page belongs to.
     *
     * `member_tracking_followup` is the Tracking & Integration table: its rows
     * are written with `department_id` 23 and every first timer the church
     * registers lands in the same pool. The page used to carry a department
     * dropdown defaulting to 23; picking anything else rendered an empty page -
     * and could crash it, because `$assignedMembers` and `$unassignedMembers`
     * were only built inside the "is it 23?" branch. The selector, its query and
     * that branch are gone.
     */
    private const DEPARTMENT_ID = 23;

    /**
     * How many unassigned first timers one render ships.
     *
     * The department carries hundreds of them and every one is a draggable
     * card, so the pool is paged: the newest `POOL_PAGE` are rendered and the
     * rest are one link away. `?pool_limit=N` widens the window without losing
     * the filters.
     */
    private const POOL_PAGE = 60;

    /**
     * Guides on the left (each one a drop zone holding the first timers it
     * follows up) and the unassigned pool on the right (which is also the drop
     * zone that unassigns). Both halves answer to the filters in their column.
     */
    public function dragDropIndex(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->member_role ?? '', ['Super User', 'Admin'])) {
            return redirect('/home')->with('error', 'Access Denied');
        }

        $userCampusId = $user->campus_id ?? session('campus_id', 0);

        // Read back into the form and kept in the URL, so a filtered page can
        // be refreshed, bookmarked or shared without losing its filters.
        $filters = [
            'guide_search'      => trim((string) $request->get('guide_search', '')),
            'guide_gender'      => trim((string) $request->get('guide_gender', '')),
            'guide_church_type' => trim((string) $request->get('guide_church_type', '')),
            'guide_occupation'  => trim((string) $request->get('guide_occupation', '')),
            'ft_search'         => trim((string) $request->get('ft_search', '')),
            'ft_start_date'     => trim((string) $request->get('ft_start_date', '')),
            'ft_end_date'       => trim((string) $request->get('ft_end_date', '')),
            'ft_gender'         => trim((string) $request->get('ft_gender', '')),
            'ft_church_type'    => trim((string) $request->get('ft_church_type', '')),
            'ft_guest_type'     => trim((string) $request->get('ft_guest_type', '')),
        ];

        // The two boxes are independent, and each one only answers for its own
        // column: the guide box narrows the guides (and therefore which cards
        // are on the page at all), the first timer box narrows the unassigned
        // pool. A first timer filter must never remove a card from under a
        // guide - the guide still follows that person up.
        $guideFiltersActive = $this->anyFilterActive($filters, 'guide_');
        $timerFiltersActive = $this->anyFilterActive($filters, 'ft_');

        $poolLimit = max(self::POOL_PAGE, (int) $request->get('pool_limit', self::POOL_PAGE));

        // --- GUIDES: the department roster ---
        $guideBase = DB::table('tiu_member')
            ->where('department_name', 'LIKE', '%"'.self::DEPARTMENT_ID.'"%')
            ->forCurrentCampus('campus_id', $userCampusId);

        // How many guides the box would show unfiltered. The panel prints
        // "12 of 59" so a narrowed list cannot be mistaken for the department.
        $guideTotal = (clone $guideBase)->count();

        $guideQuery = clone $guideBase;

        if ($filters['guide_search'] !== '') {
            $search = $filters['guide_search'];
            $guideQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                  ->orWhere('last_name', 'like', '%'.$search.'%')
                  ->orWhere('phone_number', 'like', '%'.$search.'%');
            });
        }
        if ($filters['guide_gender'] !== '') {
            $guideQuery->where('gender', $filters['guide_gender']);
        }
        if ($filters['guide_church_type'] !== '') {
            $guideQuery->where('church_type_id', $filters['guide_church_type']);
        }
        if ($filters['guide_occupation'] !== '') {
            $guideQuery->where('occupation', $filters['guide_occupation']);
        }

        $guides = $guideQuery
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get([
                'tiu_member_id', 'first_name', 'last_name', 'gender', 'age',
                'occupation', 'church_type_id', 'subgroup',
            ]);

        // --- THE FIRST TIMERS EACH GUIDE ALREADY FOLLOWS UP ---
        $assignedByGuide = collect();

        if ($guides->isNotEmpty()) {
            // No ft_* here on purpose: what a guide follows up does not change
            // because somebody searched the unassigned pool on the right.
            $assignedByGuide = $this->assignedQuery($guides->pluck('tiu_member_id')->all())
                ->select('ft.*', 'mtf.tiu_member_id')
                ->orderBy('ft.first_name')
                ->orderBy('ft.last_name')
                ->get()
                // One card per first timer, even if it ever got two open rows.
                ->unique('first_timer_id')
                ->groupBy('tiu_member_id');
        }

        $guidesArray = [];
        foreach ($guides as $guide) {
            $guideId = $guide->tiu_member_id;

            $guidesArray[$guideId] = [
                'details'      => $guide,
                'first_timers' => $assignedByGuide->get($guideId, collect())->all(),
            ];
        }


        // --- UNASSIGNED FIRST TIMERS: the pool / the unassign drop zone ---
        $poolQuery = DB::table('first_timer')
            ->where(function ($q) {
                $q->whereIn('status', ['Unassigned', 'Not Assigned'])
                  ->orWhereNull('status');
            })
            ->forCurrentCampus('campus_id', $userCampusId)
            // A first timer with a live follow-up row is somebody's card, even if
            // its status column still says otherwise.
            ->whereNotIn('first_timer_id', function ($q) {
                $q->select('first_timer_id')
                  ->from('member_tracking_followup')
                  ->where('tiu_member_id', '>', 0);
            });

        $this->scopeToFirstTimerFilters($poolQuery, $filters);

        $poolTotal = (clone $poolQuery)->count();

        $unassignedMembers = $poolQuery
            ->orderByDesc('register_date')
            ->orderByDesc('first_timer_id')
            ->limit($poolLimit)
            ->get();

        $poolShowing = $unassignedMembers->count();

        $morePoolUrl = $poolShowing < $poolTotal
            ? route('admin.assignments.drag-drop', array_merge($request->query(), [
                'pool_limit' => $poolLimit + self::POOL_PAGE,
            ]))
            : null;

        // --- FILTER DROPDOWNS ---
        $churchTypes = [];
        try {
            $churchTypes = DB::table('church_type')
                ->orderBy('church_type_name')
                ->get(['id', 'church_type_name']);
        } catch (\Throwable $e) {
            // A missing lookup table must not take the page down: the church type
            // filter then simply has nothing to offer.
            Log::warning('dragDropIndex: church_type unavailable - '.$e->getMessage());
        }

        $occupations = DB::table('tiu_member')
            ->where('department_name', 'LIKE', '%"'.self::DEPARTMENT_ID.'"%')
            ->forCurrentCampus('campus_id', $userCampusId)
            ->whereNotNull('occupation')
            ->where('occupation', '!=', '')
            ->distinct()
            ->orderBy('occupation')
            ->pluck('occupation');

        $guestTypeOptions = [
            'Visiting',
            'New To TCN (Will Join TCN)',
            'New To TCN (May Join TCN)',
            'TCN Member (Relocating to Ikd)',
            'TCN Member (In Transit)',
            'TCN Member (Other Center)',
        ];

        return view('admin.assignments.drag-drop', [
            'guidesArray'        => $guidesArray,
            'guideTotal'         => $guideTotal,
            'unassignedMembers'  => $unassignedMembers,
            'poolTotal'          => $poolTotal,
            'poolShowing'        => $poolShowing,
            'morePoolUrl'        => $morePoolUrl,
            'filters'            => $filters,
            'guideFiltersActive' => $guideFiltersActive,
            'timerFiltersActive' => $timerFiltersActive,
            'departmentName'     => $this->departmentLabel(),
            'churchTypes'        => $churchTypes,
            'occupations'        => $occupations,
            'guestTypeOptions'   => $guestTypeOptions,
        ]);
    }

    /**
     * The follow-ups a set of guides holds, joined back to the first timer.
     *
     * @param  array<int, mixed>  $guideIds
     * @return \Illuminate\Database\Query\Builder
     */
    private function assignedQuery(array $guideIds)
    {
        $query = DB::table('first_timer as ft')
            ->join('member_tracking_followup as mtf', 'ft.first_timer_id', '=', 'mtf.first_timer_id')
            ->whereIn('mtf.tiu_member_id', $guideIds)
            // Unassigning only zeroes the guide id (ajaxUnassign), so a 0 is a
            // removed assignment, not a guide.
            ->where('mtf.tiu_member_id', '>', 0)
            // An integrated first timer has finished the journey: not a card.
            ->where(function ($q) {
                $q->whereNull('ft.status')->orWhere('ft.status', '!=', 'Integrated');
            });

        if ($this->hasColumn('member_tracking_followup', 'department_id')) {
            // Production predates the column default, so an imported row can sit
            // at 0 although it was created for this department.
            $query->where(function ($q) {
                $q->where('mtf.department_id', self::DEPARTMENT_ID)
                  ->orWhere('mtf.department_id', 0)
                  ->orWhereNull('mtf.department_id');
            });
        }

        return $query;
    }

    /**
     * Which columns of the export this database actually has.
     *
     * @var array<string, bool>
     */
    private static $columnCache = [];

    /**
     * `Schema::hasColumn()` throws when the table itself is missing, and the
     * production database is not guaranteed to carry every column the export
     * has (docs/PROD-DB-DIFF.md). Neither may take the page down.
     */
    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (!array_key_exists($key, self::$columnCache)) {
            try {
                self::$columnCache[$key] = Schema::hasColumn($table, $column);
            } catch (\Throwable $e) {
                self::$columnCache[$key] = false;
            }
        }

        return self::$columnCache[$key];
    }

    /**
     * The "First timers" box in the right column.
     *
     * It narrows the unassigned pool only. The cards under a guide are that
     * guide's follow-ups and are not touched by this box - the two sides of the
     * page are independent, which is what the church asked for.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, string>  $filters
     * @return \Illuminate\Database\Query\Builder
     */
    private function scopeToFirstTimerFilters($query, array $filters)
    {
        $search = $filters['ft_search'] ?? '';

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', '%'.$search.'%')
                  ->orWhere('last_name', 'like', '%'.$search.'%');
            });
        }

        // The two dates are independent on purpose: one date alone reads as
        // "registered since" or "registered up to", which is what the church
        // asked for; requiring both hid every card until two dates were picked.
        if (($filters['ft_start_date'] ?? '') !== '') {
            $query->whereDate('register_date', '>=', $filters['ft_start_date']);
        }
        if (($filters['ft_end_date'] ?? '') !== '') {
            $query->whereDate('register_date', '<=', $filters['ft_end_date']);
        }
        if (($filters['ft_gender'] ?? '') !== '') {
            $query->where('gender', $filters['ft_gender']);
        }
        if (($filters['ft_church_type'] ?? '') !== '') {
            $query->where('church_type_id', $filters['ft_church_type']);
        }
        if (($filters['ft_guest_type'] ?? '') !== '') {
            $query->where('attendant_type', $filters['ft_guest_type']);
        }

        return $query;
    }

    /**
     * Is any filter of one box filled in?
     *
     * `guide_*` belongs to the left column, `ft_*` to the right one, and the
     * view prints its "12 of 59 / show all" hints off these two answers rather
     * than off a single "the page is filtered" flag - a first timer search must
     * not make the guides look narrowed.
     *
     * @param  array<string, string>  $filters
     */
    private function anyFilterActive(array $filters, string $prefix): bool
    {
        foreach ($filters as $key => $value) {
            if ($value !== '' && strpos($key, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The department's display name, with the church's own wording as fallback.
     */
    private function departmentLabel(): string
    {
        try {
            $name = DB::table('department')
                ->where('dept_id', self::DEPARTMENT_ID)
                ->value('dept_name');
        } catch (\Throwable $e) {
            $name = null;
        }

        return $name ?: 'Tracking and Integration';
    }

    public function suggest()
    {
        $user = Auth::user();
        $userCampusId = $user->campus_id ?? 0;

        // Get unassigned first timers
        $unassigned = DB::table('first_timer')
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', 'Not Assigned');
            })
            ->forCurrentCampus('campus_id', $userCampusId)
            ->orderBy('first_name', 'asc')
            ->get();

        // Get guides with assignment counts
        $guides = DB::table('tiu_member')
            ->leftJoin('member_tracking_followup', 'tiu_member.tiu_member_id', '=', 'member_tracking_followup.tiu_member_id')
            ->where('tiu_member.status', 1)
            ->forCurrentCampus('tiu_member.campus_id', $userCampusId)
            ->select(
                'tiu_member.tiu_member_id',
                'tiu_member.first_name',
                'tiu_member.last_name',
                'tiu_member.subgroup',
                DB::raw('COUNT(member_tracking_followup.tracking_id) as assignment_count')
            )
            ->groupBy('tiu_member.tiu_member_id', 'tiu_member.first_name', 'tiu_member.last_name', 'tiu_member.subgroup')
            ->orderBy('assignment_count', 'asc')
            ->get();

        return view('admin.assignments.suggest', compact('unassigned', 'guides'));
    }

    public function generatePairings(Request $request)
    {
        $request->validate([
            'guide_id' => 'required|integer',
            'first_timer_ids' => 'required|array',
            'first_timer_ids.*' => 'integer',
        ]);

        try {
            $guideId = $request->guide_id;
            $ftIds = $request->first_timer_ids;
            $userCampusId = Auth::user()->campus_id ?? 0;

            // Collected so the guide gets one summary alert, not one per person.
            $assignedNames = [];
            $assignedIds = [];

            foreach ($ftIds as $ftId) {
                $existing = DB::table('member_tracking_followup')
                    ->where('first_timer_id', $ftId)
                    ->exists();

                if (!$existing) {
                    // Use campus-filtered tasks
                    $followupResponse = $this->generateDefaultFollowupResponse();
                    $taskRow = DB::table('task')
                        ->where('wid', 1)
                        ->forCurrentCampus('campus_id', $userCampusId)
                        ->value('questions');
                    if ($taskRow) {
                        $rawJson = html_entity_decode($taskRow, ENT_QUOTES | ENT_HTML5);
                        $decoded = json_decode($rawJson, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            foreach ($decoded as &$td) {
                                if (is_array($td)) $td['logs'] = [];
                            }
                            unset($td);
                            $followupResponse = ['week1' => $decoded];
                        }
                    }

                    DB::table('member_tracking_followup')->insert([
                        'tiu_member_id' => $guideId,
                        'first_timer_id' => $ftId,
                        'followup_rank' => 1,
                        'followup_response_new' => json_encode($followupResponse),
                        'timeStamp_registered' => now(),
                    ]);

                    DB::table('first_timer')
                        ->where('first_timer_id', $ftId)
                        ->update(['status' => 'Assigned']);

                    $assignedNames[] = $this->personName(
                        DB::table('first_timer')->where('first_timer_id', $ftId)->first(['first_name', 'last_name']),
                        $ftId
                    );
                    $assignedIds[] = (int) $ftId;
                }
            }

            // Item 11: one alert for the whole batch.
            $notified = $this->notifyGuide(
                $guideId,
                $this->notifications->names($assignedNames, 3),
                $assignedIds[0] ?? null,
                count($assignedNames)
            );

            return redirect()->back()->with(
                'success',
                'Pairings generated successfully!'.($notified ? ' The guide has been notified.' : '')
            );
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Tell a guide that one or more first timers are now theirs to follow up
     * (item 11). The row feeds both the navbar bell and the browser alert.
     *
     * @param  mixed  $guideId
     * @param  string  $firstTimerNames  Summary, e.g. "Ada, Bola and 3 others"
     * @param  mixed  $firstTimerId  Opened by the bell / the browser alert
     * @param  int  $howMany
     */
    private function notifyGuide($guideId, string $firstTimerNames, $firstTimerId = null, int $howMany = 1): bool
    {
        $guideId = (int) $guideId;

        if ($guideId <= 0 || $howMany < 1) {
            return false;
        }

        $title = $howMany > 1
            ? "You have {$howMany} new first timers to follow up"
            : 'You have a new first timer to follow up';

        return $this->notifications->notify(
            $guideId,
            NotificationService::TYPE_GUIDE_ASSIGNMENT,
            $title,
            $firstTimerNames !== '' ? $firstTimerNames : null,
            ['url' => '/my-tasks', 'first_timer_id' => $firstTimerId],
            (int) (Auth::user()->campus_id ?? 0) ?: null
        );
    }

    /**
     * "Ada Bola" for a first_timer row, or "First timer #12" when unnamed.
     *
     * @param  mixed  $row
     * @param  mixed  $firstTimerId
     */
    private function personName($row, $firstTimerId = null): string
    {
        $row = is_object($row) ? $row : (object) [];
        $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));

        return $name !== '' ? $name : 'First timer #'.(int) $firstTimerId;
    }

    private function generateDefaultFollowupResponse()
    {
        $weeks = [
            'Week 1' => [
                'task_1' => ['text' => 'Pray for the first-timer', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Call the first-timer within 24 hours', 'display' => false, 'status' => 'Pending', 'logs' => []],
                'task_3' => ['text' => 'Send a welcome SMS', 'display' => false, 'status' => 'Pending', 'logs' => []],
                'task_4' => ['text' => 'Invite to next service', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
            'Week 2' => [
                'task_1' => ['text' => 'Follow up call - check on well-being', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Share a Bible verse', 'display' => false, 'status' => 'Pending', 'logs' => []],
                'task_3' => ['text' => 'Invite to house fellowship', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
            'Week 3' => [
                'task_1' => ['text' => 'Visit or call the first-timer', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Discuss spiritual growth', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
            'Week 4' => [
                'task_1' => ['text' => 'Final follow-up for the month', 'display' => true, 'status' => 'Pending', 'logs' => []],
                'task_2' => ['text' => 'Encourage FOF registration', 'display' => false, 'status' => 'Pending', 'logs' => []],
            ],
        ];

        return $weeks;
    }
}
