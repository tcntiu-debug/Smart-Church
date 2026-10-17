<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The notice board the public site reads: GET /api/public/announcements.
 *
 * The church's Vercel site has no login, so it cannot use /notice. This endpoint
 * answers anonymously from `announcements` only - never from a member table - so
 * a leak here would expose contact details. The tests pin both halves: the
 * notices come back newest-first with the documented keys, and no member column
 * sneaks into the payload.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection. Every test writes inside a transaction opened in setUp() and
 * rolled back in tearDown().
 */
class PublicAnnouncementApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    /**
     * A notice dated far enough in the future that it is the first row in any
     * page of the feed.
     */
    private function publish(string $title, string $content, int $minutesAhead = 60): int
    {
        return (int) DB::table('announcements')->insertGetId([
            'title' => $title,
            'content' => $content,
            'created_at' => now()->addMinutes($minutesAhead),
            'campus_id' => null,
        ]);
    }

    public function test_a_guest_can_read_the_newest_notices()
    {
        $title = 'Public feed testcase '.random_int(100000, 999999);

        $id = $this->publish($title, "  The generator\n\nruns on Saturday.  ", 120);

        $response = $this->getJson('/api/public/announcements?limit=5');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.title', $title)
            ->assertJsonPath('data.0.excerpt', 'The generator runs on Saturday.');

        $payload = $response->json();

        $this->assertSame(5, $payload['meta']['limit']);
        $this->assertLessThanOrEqual(5, $payload['count']);
        $this->assertSame($payload['count'], count($payload['data']));

        // The keys the Vercel site renders. A new key is fine; a missing one is
        // a blank block on the page.
        $this->assertSame(
            ['id', 'title', 'content', 'excerpt', 'posted_at', 'posted_at_human', 'campus_id', 'campus'],
            array_keys($payload['data'][0])
        );
    }

    public function test_the_feed_never_carries_member_data()
    {
        $this->publish('No member data '.random_int(100000, 999999), 'Plain notice body.', 90);

        $payload = $this->getJson('/api/public/announcements?limit=5')->assertOk()->json();

        $allowed = ['id', 'title', 'content', 'excerpt', 'posted_at', 'posted_at_human', 'campus_id', 'campus'];

        foreach ($payload['data'] as $notice) {
            $this->assertSame([], array_diff(array_keys($notice), $allowed));
        }

        // Neither the phone nor the e-mail of the nearest member appears.
        $member = DB::table('tiu_member')
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->first(['phone_number', 'email']);

        $body = json_encode($payload['data']);

        if ($member && trim((string) $member->phone_number) !== '') {
            $this->assertStringNotContainsString((string) $member->phone_number, $body);
        }

        if ($member && trim((string) $member->email) !== '') {
            $this->assertStringNotContainsString((string) $member->email, $body);
        }
    }

    public function test_the_limit_is_validated()
    {
        $this->getJson('/api/public/announcements?limit=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);

        $this->getJson('/api/public/announcements?limit=0')
            ->assertStatus(422);
    }

    public function test_the_feed_is_safe_for_another_origin()
    {
        // config/cors.php answers api/* with a wildcard origin, which is what
        // lets the Vercel site call this endpoint from the browser.
        $response = $this->withHeaders(['Origin' => 'https://smart-church.example'])
            ->getJson('/api/public/announcements?limit=1');

        $response->assertOk();

        $this->assertSame(
            '*',
            $response->headers->get('Access-Control-Allow-Origin'),
            'The public feed must stay readable from the church website origin.'
        );
    }

    /**
     * The local test client skips the web server, but on cPanel the document
     * root's .htaccess is what forwards a URL to Laravel - and its old
     * `RewriteCond %{REQUEST_URI} !/public/` skipped every path that contained
     * that segment. This endpoint therefore never reached the front controller
     * on dev: LiteSpeed answered with its own 404 page. The guard now tests the
     * path relative to the .htaccess, so only paths that already start with
     * public/ are left alone.
     */
    public function test_the_document_root_rewrite_still_sends_this_url_to_laravel()
    {
        $htaccess = (string) file_get_contents(base_path('.htaccess'));

        // The comments quote the old condition, so only the directives count.
        $rules = implode("\n", array_filter(
            preg_split('/\r\n|\r|\n/', $htaccess),
            function ($line) {
                return strpos(ltrim($line), '#') !== 0;
            }
        ));

        $this->assertStringNotContainsString(
            '!/public/',
            $rules,
            'A condition on /public/ anywhere in the URL swallows /api/public/announcements.'
        );

        $this->assertStringContainsString(
            'RewriteRule ^public/ - [L]',
            $rules,
            'The root .htaccess must leave only paths that start with public/ alone.'
        );

        $this->getJson('/api/public/announcements?limit=1')->assertOk();
    }
}
