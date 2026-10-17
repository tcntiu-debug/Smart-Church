<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The Action Board (resources/views/pending-tasks/index.blade.php).
 *
 * Its "guide" link used to be written out in full, host and all
 * (https://tiu.tcnikorodu.org/dash-board?...). That was correct while the app was
 * served from that host and quietly wrong the moment it was not: after the move
 * to smartchurch.tcnikorodu.org (docs/MIGRATION-smartchurch.md, section 8) the
 * link hands a signed-in user to the hostname that is being retired.
 *
 * It is built with url('/dash-board') now, so it follows APP_URL wherever the app
 * is deployed, and the last test is the guard for the whole tree: no view,
 * controller, config or route file may learn a production hostname again.
 */
class PendingTasksBoardTest extends TestCase
{
    /**
     * Hosts the app has been served from by name. Any occurrence in a view,
     * controller, config or route file is what this file exists to catch.
     */
    private const DEPLOYMENT_HOSTS = ['tiu.tcnikorodu.org', 'dev.lit-grp.com'];

    /**
     * The board renders for a signed-in member, and the page it renders does not
     * carry a retired hostname anywhere.
     */
    public function test_the_action_board_renders_without_a_retired_host()
    {
        $viewer = $this->signedInViewer();

        if (! $viewer) {
            $this->markTestSkipped('No member with a complete profile to sign in as.');
        }

        $this->actingAs($viewer);

        $response = $this->get('/pending-tasks');

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'The Action Board answered '.$response->getStatusCode().' instead of rendering.'
        );
        $this->assertStringContainsString(
            'Pending Tasks Action Board',
            $response->getContent(),
            'The page rendered but not as the Action Board.'
        );

        foreach (self::DEPLOYMENT_HOSTS as $host) {
            $this->assertStringNotContainsString(
                $host,
                $response->getContent(),
                "The Action Board links to {$host}: a signed-in user is sent to a host the app does not answer on."
            );
        }
    }

    /**
     * The guide link is built from the configured host rather than written out,
     * which is what makes the board work on localhost, on staging and on
     * production without an edit.
     */
    public function test_the_guide_link_is_built_from_the_configured_host()
    {
        $blade = File::get(resource_path('views/pending-tasks/index.blade.php'));

        $lines = collect(preg_split('/\r\n|\n/', $blade))
            ->filter(function ($line) {
                return str_contains($line, 'dash-board');
            });

        $this->assertNotEmpty($lines->all(), 'The guide link is gone from the Action Board.');

        foreach ($lines as $line) {
            $this->assertStringNotContainsString(
                '://',
                $line,
                'The guide link is written out with a scheme and a host again: '.trim($line)
            );
        }

        $this->assertStringContainsString(
            "url('/dash-board')",
            $blade,
            "The guide link does not use url('/dash-board'), so it does not follow APP_URL."
        );
    }

    /**
     * Nothing in the application source names a host it is deployed to. The two
     * documents that do (docs/PROD-DB-DIFF.md, docs/MIGRATION-smartchurch.md) are
     * records of what production was and are deliberately not part of this scan.
     */
    public function test_no_source_file_hardcodes_a_deployment_host()
    {
        $offenders = [];

        foreach (['app', 'config', 'routes', 'resources/views'] as $directory) {
            foreach (File::allFiles(base_path($directory)) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                $contents = File::get($file->getPathname());

                foreach (self::DEPLOYMENT_HOSTS as $host) {
                    if (str_contains($contents, $host)) {
                        $offenders[] = str_replace('\\', '/', $file->getRelativePathname()).' ('.$host.')';
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These files name a host the app is deployed to; build the URL from url()/route()/config(\'app.url\') instead:'
                .PHP_EOL.'   - '.implode(PHP_EOL.'   - ', $offenders)
        );
    }

    /**
     * A member whose profile passes CheckProfileComplete (single campus mode, so
     * the campus is not required) - otherwise /pending-tasks redirects to the
     * profile page instead of rendering the board.
     */
    private function signedInViewer(): ?User
    {
        return User::query()
            ->whereIn('member_role', ['Super User', 'Admin', 'Admins', 'Worker', 'Workers'])
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('birthday')
                    ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                    ->whereNotNull('birthday.birthday');
            })
            ->first();
    }
}
