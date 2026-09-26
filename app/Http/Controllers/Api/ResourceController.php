<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResourceController extends Controller
{
    /**
     * Get public resources
     */
    public function publicResources(Request $request)
    {
        $resources = DB::table('shared_resources as r')
            ->join('resource_shares as s', 'r.id', '=', 's.resource_id')
            ->whereNull('s.member_id')
            ->select('r.*', 's.share_date')
            ->orderBy('r.upload_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $resources]);
    }

    /**
     * Get private resources (shared with current user)
     */
    public function privateResources(Request $request)
    {
        $userId = $request->user_id;

        $resources = DB::table('shared_resources as r')
            ->join('resource_shares as s', 'r.id', '=', 's.resource_id')
            ->where('s.member_id', $userId)
            ->select('r.*', 's.share_date')
            ->orderBy('s.share_date', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $resources]);
    }
}
