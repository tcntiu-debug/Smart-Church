<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResourceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display public resources (member_id is NULL)
     */
    public function publicResources()
    {
        $resources = DB::table('shared_resources as r')
            ->join('resource_shares as s', 'r.id', '=', 's.resource_id')
            ->whereNull('s.member_id')
            ->select('r.*', 's.share_date')
            ->orderBy('r.upload_date', 'desc')
            ->get();

        return view('resources.public', compact('resources'));
    }

    /**
     * Display private resources (shared with logged-in user)
     */
    public function privateResources()
    {
        $userId = Auth::user()->tiu_member_id ?? Auth::user()->id;
        
        $resources = DB::table('shared_resources as r')
            ->join('resource_shares as s', 'r.id', '=', 's.resource_id')
            ->where('s.member_id', $userId)
            ->select('r.*', 's.share_date')
            ->orderBy('s.share_date', 'desc')
            ->get();

        return view('resources.private', compact('resources'));
    }

    /**
     * Store a new resource
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png|max:10240',
            'share_type' => 'required|in:public,private',
            'share_with' => 'nullable|array',
        ]);

        $userId = Auth::user()->tiu_member_id ?? Auth::user()->id;
        
        // Handle file upload
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $fileType = $file->getClientMimeType();
        $fileSize = $file->getSize();
        
        $uploadPath = public_path('uploads/resources');
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $file->move($uploadPath, $filename);
        $filePath = 'uploads/resources/' . $filename;
        
        // Insert resource
        $resourceId = DB::table('shared_resources')->insertGetId([
            'title' => $request->title,
            'description' => $request->description ?? '',
            'file_name' => $originalName,
            'file_path' => $filePath,
            'file_type' => $fileType,
            'file_size' => $fileSize,
            'uploader_id' => $userId,
            'upload_date' => now(),
        ]);
        
        // Create share record
        if ($request->share_type == 'public') {
            // Public - member_id = NULL
            DB::table('resource_shares')->insert([
                'resource_id' => $resourceId,
                'member_id' => null,
                'share_date' => now(),
            ]);
        } else {
            // Private - share with selected members
            if ($request->has('share_with') && !empty($request->share_with)) {
                foreach ($request->share_with as $memberId) {
                    DB::table('resource_shares')->insert([
                        'resource_id' => $resourceId,
                        'member_id' => $memberId,
                        'share_date' => now(),
                    ]);
                }
            } else {
                // If no members selected, share with uploader only
                DB::table('resource_shares')->insert([
                    'resource_id' => $resourceId,
                    'member_id' => $userId,
                    'share_date' => now(),
                ]);
            }
        }
        
        return redirect()->back()->with('success', 'Resource uploaded successfully!');
    }

    /**
     * Delete a resource
     */
    public function destroy($id)
    {
        $resource = DB::table('shared_resources')->where('id', $id)->first();
        
        if ($resource) {
            $filePath = public_path($resource->file_path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            DB::table('resource_shares')->where('resource_id', $id)->delete();
            DB::table('shared_resources')->where('id', $id)->delete();
        }
        
        return redirect()->back()->with('success', 'Resource deleted successfully!');
    }

    /**
     * Display the admin resource management page (upload + list + share)
     */
    public function admin()
    {
        $resources = DB::table('shared_resources as r')
            ->leftJoin('tiu_member as m', 'r.uploader_id', '=', 'm.tiu_member_id')
            ->select('r.*', 'm.first_name', 'm.last_name')
            ->orderBy('r.upload_date', 'desc')
            ->get();

        $members = DB::table('tiu_member')
            ->select('tiu_member_id', 'first_name', 'last_name')
            ->orderBy('first_name', 'asc')
            ->orderBy('last_name', 'asc')
            ->get();

        return view('resources.admin', compact('resources', 'members'));
    }

    /**
     * Ajax delete a resource
     */
    public function deleteAjax(Request $request)
    {
        $id = $request->input('resource_id');

        $resource = DB::table('shared_resources')->where('id', $id)->first();

        if (!$resource) {
            return response()->json(['status' => 'error', 'message' => 'Resource not found.']);
        }

        $filePath = public_path($resource->file_path);
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        DB::table('resource_shares')->where('resource_id', $id)->delete();
        DB::table('shared_resources')->where('id', $id)->delete();

        return response()->json(['status' => 'success', 'message' => 'Resource deleted successfully.']);
    }

    /**
     * Ajax: Update sharing settings for a resource
     */
    public function share(Request $request)
    {
        $resourceId = $request->input('resource_id');
        $shareType = $request->input('share_type', 'public');
        $memberIds = $request->input('member_ids', []);

        if (!$resourceId) {
            return response()->json(['status' => 'error', 'message' => 'Invalid Resource ID.']);
        }

        try {
            DB::beginTransaction();

            // Remove existing shares
            DB::table('resource_shares')->where('resource_id', $resourceId)->delete();

            if ($shareType === 'public') {
                DB::table('resource_shares')->insert([
                    'resource_id' => $resourceId,
                    'member_id' => null,
                    'share_date' => now(),
                ]);
            } elseif ($shareType === 'private' && !empty($memberIds)) {
                foreach ($memberIds as $memberId) {
                    DB::table('resource_shares')->insert([
                        'resource_id' => $resourceId,
                        'member_id' => $memberId,
                        'share_date' => now(),
                    ]);
                }
            }

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Sharing settings updated!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    /**
     * Ajax: Upload a resource
     */
    public function uploadAjax(Request $request)
    {
        if (!$request->hasFile('resource_file') || !$request->filled('resource_title')) {
            return response()->json(['status' => 'error', 'message' => 'Title and a valid file are required.']);
        }

        $userId = Auth::user()->tiu_member_id ?? Auth::user()->id;
        $title = trim($request->input('resource_title'));
        $description = trim($request->input('resource_description', ''));
        $file = $request->file('resource_file');

        $originalName = $file->getClientOriginalName();
        $uniqueName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $fileType = $file->getClientMimeType();
        $fileSize = $file->getSize();

        $uploadPath = public_path('uploads/resources');
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        try {
            $file->move($uploadPath, $uniqueName);
            $filePath = 'uploads/resources/' . $uniqueName;

            DB::table('shared_resources')->insert([
                'title' => $title,
                'description' => $description ?: null,
                'file_name' => $originalName,
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'uploader_id' => $userId,
                'upload_date' => now(),
            ]);

            return response()->json(['status' => 'success', 'message' => 'Resource uploaded successfully!']);
        } catch (\Exception $e) {
            // Cleanup file if DB insert fails
            if (file_exists($uploadPath . '/' . $uniqueName)) {
                unlink($uploadPath . '/' . $uniqueName);
            }
            return response()->json(['status' => 'error', 'message' => 'Upload failed: ' . $e->getMessage()]);
        }
    }
}
