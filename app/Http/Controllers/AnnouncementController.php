<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        
        $announcements = DB::table('announcements')
            ->where('campus_id', $campusId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('announcements.index', compact('announcements'));
    }

    public function noticeBoard()
    {
        $user = Auth::user();
        $campusId = $user->campus_id ?? 0;
        
        $announcements = DB::table('announcements')
            ->where('campus_id', $campusId)
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

        DB::table('announcements')->insert([
            'title' => $request->title,
            'content' => $request->content ?? '',
            'created_at' => now(),
            'campus_id' => $campusId,
        ]);

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