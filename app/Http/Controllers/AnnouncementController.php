<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    /**
     * @var \App\Services\NotificationService
     */
    protected $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;

        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        
        $announcements = DB::table('announcements')
            ->forCurrentCampus('campus_id', $campusId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('announcements.index', compact('announcements'));
    }

    public function noticeBoard()
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        
        $announcements = DB::table('announcements')
            ->forCurrentCampus('campus_id', $campusId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('announcements.notice-board', compact('announcements'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        $storedCampusId = Campus::writeId($campusId);

        $id = DB::table('announcements')->insertGetId([
            'title' => $request->title,
            'content' => $request->content ?? '',
            'created_at' => now(),
            'campus_id' => $storedCampusId,
        ]);

        // Publishing a notice rings every member's bell (the bell links back to
        // /notice). Editing one does not: an update must not shout twice.
        $this->notifications->announce((int) $id, (string) $request->title, $request->content, $storedCampusId);

        return redirect()->back()->with('success', 'Announcement posted successfully!');
    }

    public function update(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
        ]);

        DB::table('announcements')
            ->where('id', $request->id)
            ->update([
                'title' => $request->title,
                'content' => $request->content ?? '',
            ]);

        return redirect()->back()->with('success', 'Announcement updated!');
    }

    public function destroy($id)
    {
        DB::table('announcements')->where('id', $id)->delete();
        return redirect()->back()->with('success', 'Announcement deleted!');
    }
}