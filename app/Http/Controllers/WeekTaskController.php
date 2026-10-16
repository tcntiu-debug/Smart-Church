<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WeekTaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the enter-task page (form + existing tasks)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect('/mytask')->with('error', 'Access Denied');
        }

        // Get user's campus_id (null while the church runs a single campus)
        $userCampusId = Campus::filterId($request->get('campus_id', $user->campus_id));

        // Get campus list for the dropdown filter
        $campuses = DB::table('campus')->orderBy('cname')->get();

        // Campus named in the header. In single campus mode there is no id to
        // resolve, so the page names the one campus the church runs instead of
        // printing "N/A" for a value that was retired on purpose.
        $currentCampus = $userCampusId ? $campuses->firstWhere('cid', $userCampusId) : null;
        $currentCampusName = $currentCampus ? $currentCampus->cname : Campus::defaultName();

        // Get departments for the dropdown (restricted to Foundation of Faith Support and Tracking and Integration)
        $departments = DB::table('department')
            ->whereIn('dept_id', [15, 23])
            ->orderBy('dept_name')
            ->get();

        // Fetch tasks filtered by campus
        $taskQuery = DB::table('task');
        if ($userCampusId) {
            $taskQuery->forCurrentCampus('campus_id', $userCampusId);
        }
        $tasks = $taskQuery->get();

        // Get department names mapping
        $deptNames = DB::table('department')
            ->whereIn('dept_id', [15, 23])
            ->pluck('dept_name', 'dept_id');

        // Group tasks by (wid + department_id) for separate panels
        $taskGroups = [];
        $deptWeekGroups = []; // grouped by department name: [dept_name => [ ['key' => ..., 'wid' => ...], ...]]
        foreach ($tasks as $task) {
            $deptId = $task->department_id ?? 'none';
            $key = $task->wid . '-' . $deptId;
            $deptName = isset($deptNames[$deptId]) ? $deptNames[$deptId] : 'General';

            if (!isset($taskGroups[$key])) {
                $taskGroups[$key] = [
                    'wid' => $task->wid,
                    'dept_id' => $deptId,
                    'dept_name' => $deptName,
                    'questions' => json_decode($task->questions, true) ?? [],
                ];

                if (!isset($deptWeekGroups[$deptName])) {
                    $deptWeekGroups[$deptName] = [];
                }
                $deptWeekGroups[$deptName][] = (object)[
                    'key' => $key,
                    'wid' => $task->wid,
                    'dept_name' => $deptName,
                ];
            }
        }

        return view('week-task.index', compact('taskGroups', 'deptWeekGroups', 'campuses', 'userCampusId', 'currentCampusName', 'departments'));
    }

    /**
     * Store/Update a task question (per department)
     */
    public function store(Request $request)
    {
        $request->validate([
            'week' => 'required|integer|min:1|max:13',
            'task' => 'required|string',
            'questiontext' => 'required|string',
            'department_id' => 'required',
        ]);

        $week = intval($request->input('week'));
        $task = $request->input('task');
        $questiontext = $request->input('questiontext');
        // The campus column is still written (the data stays usable if a second
        // campus is opened) but it can no longer come from a stale form field.
        $campus_id = Campus::writeId($request->input('campus_id', Auth::user()->campus_id));
        $department_id = $request->input('department_id');

        // Check if a record exists for this week + department
        $existing = DB::table('task')
            ->where('wid', $week)
            ->where('department_id', $department_id)
            ->first();

        if ($existing) {
            $existingQuestions = json_decode($existing->questions, true) ?? [];

            // Check if task already exists
            if (array_key_exists($task, $existingQuestions)) {
                // Update existing task text
                $existingQuestions[$task]['text'] = $questiontext;
            } else {
                // Add new task
                $existingQuestions[$task] = [
                    "text" => $questiontext,
                    "response" => null,
                    "status" => "Pending",
                    "display" => false,
                ];
            }

            // Update the record for this week + department
            DB::table('task')
                ->where('wid', $week)
                ->where('department_id', $department_id)
                ->update([
                    'questions' => json_encode($existingQuestions),
                    'campus_id' => $campus_id,
                ]);

        } else {
            // No record exists for this week + department → Insert new record
            $jsonData = [
                $task => [
                    "text" => $questiontext,
                    "response" => null,
                    "status" => "Pending",
                    "display" => true,
                ]
            ];

            DB::table('task')->insert([
                'wid' => $week,
                'campus_id' => $campus_id,
                'questions' => json_encode($jsonData),
                'department_id' => $department_id,
            ]);
        }

        return redirect('/etask?campus_id=' . $campus_id)->with('success', 'Task saved successfully.');
    }

    /**
     * Delete a question from a week (per department)
     */
    public function destroy(Request $request)
    {
        $week = $request->input('week');
        $question = $request->input('question');
        $campus_id = Campus::writeId($request->input('campus_id', Auth::user()->campus_id));
        $department_id = $request->input('department_id');

        if (!$week || !$question || !$department_id) {
            return redirect('/etask?campus_id=' . $campus_id)->with('error', 'Invalid request.');
        }

        // Row identity, not a filter: the campus here decides WHICH task row is
        // read and later rewritten. Campus::matchQuery() keeps that working with
        // the retired (NULL) campus data: the row this controller writes carries
        // no campus either, so identity has to be matched on "no campus" and not
        // on a campus that no longer exists.
        $taskRow = function () use ($week, $department_id, $campus_id) {
            return Campus::matchQuery(
                DB::table('task')
                    ->where('wid', $week)
                    ->where('department_id', $department_id),
                'campus_id',
                $campus_id
            );
        };

        $existing = $taskRow()->first();

        if (!$existing) {
            return redirect('/etask?campus_id=' . $campus_id)->with('error', 'Week not found.');
        }

        $existingQuestions = json_decode($existing->questions, true) ?? [];

        // Remove the question
        unset($existingQuestions[$question]);

        if (empty($existingQuestions)) {
            // Delete entire row if no questions left
            $taskRow()->delete();
        } else {
            // Update with remaining questions
            $taskRow()->update(['questions' => json_encode($existingQuestions)]);
        }

        return redirect('/etask?campus_id=' . $campus_id)->with('success', 'Question deleted successfully.');
    }
}
