<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The notice board, readable without a login.
 *
 * The church's public site (the Vercel build) shows the same notices the app's
 * `/notice` page shows. That page sits behind `auth`, so the site cannot scrape
 * it; this endpoint is the supported way in. It exposes published announcements
 * and nothing else - no member, phone or e-mail column is ever selected.
 *
 *   GET /api/public/announcements?limit=5
 *
 * CORS: config/cors.php already answers `api/*` with `Access-Control-Allow-Origin:
 * *`, so the Vercel origin needs no entry. The `api` middleware group rate limits
 * anonymous callers (60 requests a minute per IP).
 */
class PublicAnnouncementController extends Controller
{
    /**
     * How many notices one response may carry, and how many it carries when the
     * caller does not say.
     */
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:'.self::MAX_LIMIT,
            'campus_id' => 'nullable|integer|min:1',
        ]);

        $limit = (int) ($validated['limit'] ?? self::DEFAULT_LIMIT);

        $query = DB::table('announcements')
            ->select('id', 'title', 'content', 'created_at', 'campus_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        // The campus column is only meaningful while the church runs more than
        // one campus; in single campus mode every row carries NULL, so applying
        // the filter would always answer with an empty list.
        $campusId = (int) ($validated['campus_id'] ?? 0);

        if ($campusId > 0 && ! Campus::isSingle()) {
            $query->where('campus_id', $campusId);
        }

        $announcements = $query->limit($limit)->get();

        // One lookup for the whole page instead of one per row.
        $campusNames = $announcements->pluck('campus_id')->filter()->unique()->isEmpty()
            ? collect()
            : DB::table('campus')->pluck('cname', 'cid');

        $timezone = config('birthday.timezone', 'Africa/Lagos');

        $data = $announcements->map(function ($announcement) use ($campusNames, $timezone) {
            $postedAt = $announcement->created_at ? Carbon::parse($announcement->created_at) : null;
            $content = (string) $announcement->content;

            return [
                'id' => (int) $announcement->id,
                'title' => (string) $announcement->title,
                // Plain text, exactly what the author typed. Consumers must
                // escape it before putting it into HTML.
                'content' => $content,
                'excerpt' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $content)), 160),
                'posted_at' => $postedAt ? $postedAt->toIso8601String() : null,
                'posted_at_human' => $postedAt ? $postedAt->copy()->timezone($timezone)->format('M j, Y g:i A') : null,
                'campus_id' => $announcement->campus_id !== null ? (int) $announcement->campus_id : null,
                'campus' => $announcement->campus_id !== null
                    ? ($campusNames[$announcement->campus_id] ?? null)
                    : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'count' => $data->count(),
            'data' => $data,
            'meta' => [
                'limit' => $limit,
                'generated_at' => now()->toIso8601String(),
            ],
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
