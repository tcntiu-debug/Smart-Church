<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdminGalleryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * ====================================================================
     * /gallery — Photo Gallery Management
     * ====================================================================
     * Original: tiu/manage_gallery.php
     */
    public function index()
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';
        $isAdmin = in_array($memberRole, ['Super User', 'Admin']);

        $photos = DB::table('photo_gallery')
            ->orderBy('id', 'desc')
            ->get();

        return view('admin.gallery', compact('photos', 'isAdmin'));
    }

    /**
     * Store or update gallery photo
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect()->back()->with('error', 'Access Denied');
        }

        $request->validate([
            'photo_title' => 'required|string|max:255',
            'photo_desc' => 'nullable|string',
            'img_file' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:20480',
        ]);

        $title = $request->photo_title;
        $desc = $request->photo_desc ?? '';
        $photoId = $request->photo_id;

        try {
            $finalPath = '';

            if ($request->hasFile('img_file')) {
                $file = $request->file('img_file');
                $ext = $file->getClientOriginalExtension();
                $filename = time() . '_' . rand(100, 999) . '.' . $ext;
                $file->move(public_path('gallery_uploads'), $filename);
                $finalPath = 'gallery_uploads/' . $filename;
            }

            if ($photoId) {
                $data = ['title' => $title, 'description' => $desc];
                if (!empty($finalPath)) {
                    $data['image_path'] = $finalPath;
                }
                DB::table('photo_gallery')->where('id', $photoId)->update($data);
            } else {
                DB::table('photo_gallery')->insert([
                    'title' => $title,
                    'description' => $desc,
                    'image_path' => $finalPath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return redirect()->route('admin.gallery')->with('success', 'Photo saved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete gallery photo
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $memberRole = $user->member_role ?? '';

        if (!in_array($memberRole, ['Super User', 'Admin'])) {
            return redirect()->back()->with('error', 'Access Denied');
        }

        $photo = DB::table('photo_gallery')->find($id);
        if ($photo) {
            if (!empty($photo->image_path) && file_exists(public_path($photo->image_path))) {
                unlink(public_path($photo->image_path));
            }
            DB::table('photo_gallery')->where('id', $id)->delete();
        }

        return redirect()->route('admin.gallery')->with('success', 'Photo deleted successfully.');
    }
}
