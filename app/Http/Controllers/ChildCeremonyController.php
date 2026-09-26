<?php

namespace App\Http\Controllers;

use App\Models\ChildCeremony;
use App\Models\TiuMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ChildCeremonyController extends Controller
{
    /**
     * Show the child ceremony (naming/dedication) page.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get the member record from tiu_member table
        $member = TiuMember::where('tiu_member_id', $user->tiu_member_id ?? $user->id)->first();
        
        return view('child-ceremony.index', compact('member'));
    }

    /**
     * Store a new child ceremony record.
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Log the incoming request
            Log::info('Child Ceremony - Incoming request', [
                'all_data' => $request->all(),
                'user_id' => $user->tiu_member_id ?? $user->id,
                'user_email' => $user->email ?? 'unknown'
            ]);

            $memberId = $user->tiu_member_id ?? $user->id;
            
            if (!$memberId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You must be logged in as a valid member to submit this form.'
                ], 400);
            }

            $request->validate([
                'ceremony_type' => 'required|in:naming,dedication',
            ]);

            // Get member's campus_id
            $member = TiuMember::where('tiu_member_id', $memberId)->first();
            $campusId = $member ? $member->campus_id : null;

            $data = [
                'member_id'     => $memberId,
                'campus_id'     => $campusId,
                'ceremony_type' => $request->ceremony_type,
            ];

            // Naming fields
            if ($request->ceremony_type === 'naming') {
                $request->validate([
                    'naming_address'       => 'required|string',
                    'naming_landmarks'     => 'required|string',
                    'child_gender'         => 'required|string',
                    'child_position'       => 'required|string',
                    'date_of_delivery'     => 'required|date',
                    'proposed_naming_date' => 'required|date',
                    'proposed_naming_time' => 'required|string',
                    'proposed_child_names' => 'required|string',
                ]);

                $data = array_merge($data, [
                    'naming_address'       => $request->naming_address,
                    'naming_landmarks'     => $request->naming_landmarks,
                    'child_gender'         => $request->child_gender,
                    'child_position'       => $request->child_position,
                    'date_of_delivery'     => $request->date_of_delivery,
                    'proposed_naming_date' => $request->proposed_naming_date,
                    'proposed_naming_time' => $request->proposed_naming_time,
                    'proposed_child_names' => $request->proposed_child_names,
                    'parent_background'    => $request->parent_background ?? '',
                ]);
            }

            // Dedication fields
            if ($request->ceremony_type === 'dedication') {
                $request->validate([
                    'father_name'          => 'required|string',
                    'mother_name'          => 'required|string',
                    'dedication_child_name'=> 'required|string',
                    'dedication_date'      => 'required|date',
                ]);

                $data = array_merge($data, [
                    'father_name'          => $request->father_name,
                    'mother_name'          => $request->mother_name,
                    'dedication_child_name'=> $request->dedication_child_name,
                    'dedication_date'      => $request->dedication_date,
                ]);
            }

            // Create the record
            $record = ChildCeremony::create($data);
            
            Log::info('Child Ceremony - Created successfully', ['id' => $record->id, 'type' => $request->ceremony_type]);

            return response()->json([
                'success' => true,
                'message' => 'Your ' . ucfirst($request->ceremony_type) . ' ceremony request has been submitted successfully! We will contact you shortly.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            Log::warning('Child Ceremony - Validation failed', ['errors' => $ve->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode('; ', array_map(function($errs) {
                    return implode(', ', $errs);
                }, $ve->errors()))
            ], 422);
        } catch (\Exception $e) {
            Log::error('Child Ceremony Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error submitting form: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: List all ceremony requests with filtering.
     * Non-Super-User admins see only records from their campus.
     */
    public function adminList(Request $request)
    {
        $user = Auth::user();
        $memberRole = session('member_role', 'Guest');
        $isSuperUser = ($memberRole == 'Super User');

        // Get the authenticated member's campus_id
        $adminMember = TiuMember::where('tiu_member_id', $user->tiu_member_id ?? $user->id)->first();
        $adminCampusId = $adminMember ? $adminMember->campus_id : null;

        $query = ChildCeremony::with('member')
            ->orderBy('created_at', 'desc');

        // Non-Super Users see only their campus records
        if (!$isSuperUser && $adminCampusId) {
            $query->where('campus_id', $adminCampusId);
        }

        // Filter by ceremony type
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('ceremony_type', $request->type);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Search by member name or child name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('proposed_child_names', 'LIKE', "%{$search}%")
                  ->orWhere('dedication_child_name', 'LIKE', "%{$search}%")
                  ->orWhere('father_name', 'LIKE', "%{$search}%")
                  ->orWhere('mother_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('member', function ($mq) use ($search) {
                      $mq->where('first_name', 'LIKE', "%{$search}%")
                         ->orWhere('last_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $ceremonies = $query->paginate(15)->appends($request->query());

        // Stats counts also filtered by campus for non-super users
        $baseStats = ChildCeremony::query();
        if (!$isSuperUser && $adminCampusId) {
            $baseStats->where('campus_id', $adminCampusId);
        }
        $namingCount = (clone $baseStats)->where('ceremony_type', 'naming')->count();
        $dedicationCount = (clone $baseStats)->where('ceremony_type', 'dedication')->count();
        $pendingCount = (clone $baseStats)->where('status', 'pending')->count();
        $completedCount = (clone $baseStats)->where('status', 'completed')->count();

        return view('child-ceremony.admin', compact('ceremonies', 'namingCount', 'dedicationCount', 'pendingCount', 'completedCount'));
    }

    /**
     * Admin: Mark a ceremony as completed.
     */
    public function markCompleted($id)
    {
        $ceremony = ChildCeremony::findOrFail($id);

        try {
            $ceremony->update(['status' => 'completed']);

            return response()->json([
                'success' => true,
                'message' => 'Ceremony marked as completed successfully!'
            ]);
        } catch (\Exception $e) {
            Log::error('Mark Ceremony Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating ceremony: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Mark a ceremony as pending (undo).
     */
    public function markPending($id)
    {
        $ceremony = ChildCeremony::findOrFail($id);

        try {
            $ceremony->update(['status' => 'pending']);

            return response()->json([
                'success' => true,
                'message' => 'Ceremony re-opened as pending.'
            ]);
        } catch (\Exception $e) {
            Log::error('Mark Ceremony Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating ceremony: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Get full details of a ceremony request (AJAX).
     */
    public function getDetail($id)
    {
        try {
            $ceremony = ChildCeremony::with('member')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $ceremony->toArray()
            ]);
        } catch (\Exception $e) {
            Log::error('Get Ceremony Detail Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching details: ' . $e->getMessage()
            ], 500);
        }
    }
}
