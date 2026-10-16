<?php

namespace Tests\Feature;

use App\Services\GuideArchiveService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Releasing a first timer without losing the guide's work.
 *
 * Covers the two admin bulk actions on `/weeklist` ("Assign all callers",
 * "Unassign all guides") and the 8-week retention rule, which now copies the
 * follow-up row into `member_tracking_followup_archive` before deleting it, so
 * `/weeklist/guide-history` can still show the last guide and the last comment.
 *
 * Deliberately no RefreshDatabase: the suite runs against the application's real
 * connection. The tests that write open a transaction in setUp() and roll it
 * back in tearDown(), so nothing they create survives the run.
 */
class FirstTimerReleaseTest extends TestCase
{
    private const CALLER_SUBGROUP = 'Touch Point 7';

    /**
     * One day in 2000, so the window holds the rows a test creates and none of
     * the real first timers.
     */
    private const WINDOW = ['start_date' => '2000-06-04', 'end_date' => '2000-06-04'];

    private const REGISTERED_AT = '2000-06-04 10:00:00';

    /** @var \App\Services\GuideArchiveService */
    private $archive;

    protected function setUp(): void
    {
        parent::setUp();

        DB::beginTransaction();

        $this->archive = app(GuideArchiveService::class);
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        parent::tearDown();
    }

    /**
     * Members carrying every field CheckProfileComplete demands, otherwise the
     * request is redirected to the profile page before it reaches the controller.
     *
     * The campus is demanded only while the church runs more than one: in single
     * campus mode CheckProfileComplete stopped asking for it and the campus_id
     * data was retired (docs/SINGLE-CAMPUS.md), so requiring it here would find
     * nobody and skip the test instead of testing the page.
     */
    private function memberQuery()
    {
        $members = \App\Models\TiuMember::query()
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone');

        if (! \App\Models\Campus::isSingle()) {
            $members->whereNotNull('campus_id');
        }

        return $members->whereExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('birthday')
                ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                ->whereNotNull('birthday.birthday');
        });
    }

    /**
     * A Super User first, so the campus filter cannot hide the test's own rows.
     */
    private function admin()
    {
        $admin = $this->memberQuery()->where('member_role', 'Super User')->first()
            ?: $this->memberQuery()->whereIn('member_role', ['Admin', 'Admins'])->first();

        if (! $admin) {
            $this->markTestSkipped('No Admin/Super User with a complete profile available.');
        }

        return $admin;
    }

    /**
     * A Worker where possible: they may open the week list, they may not release
     * or reassign the whole window in one click.
     */
    private function nonAdmin()
    {
        $member = $this->memberQuery()->whereIn('member_role', ['Worker', 'Workers'])->first()
            ?: $this->memberQuery()
                ->whereNotIn('member_role', ['Admin', 'Admins', 'Super User'])
                ->first();

        if (! $member) {
            $this->markTestSkipped('No non-admin member with a complete profile available.');
        }

        return $member;
    }

    /**
     * The "Touch Point 7" team, i.e. the pool the round-robin draws from.
     */
    private function callers()
    {
        return DB::table('tiu_member')
            ->where('subgroup', 'like', '%' . self::CALLER_SUBGROUP . '%')
            ->orderBy('first_name')
            ->get(['tiu_member_id', 'gender']);
    }

    /**
     * The gender of a first timer the caller pool can actually take: the test
     * reuses the lane a real caller sits in.
     */
    private function genderWithCallers(): string
    {
        $callers = $this->callers();

        if ($callers->isEmpty()) {
            $this->markTestSkipped('No members in the "' . self::CALLER_SUBGROUP . '" subgroup available.');
        }

        $genders = $callers->map(function ($caller) {
            return strtolower(trim((string) $caller->gender));
        });

        if ($genders->contains('male')) {
            return 'Male';
        }

        if ($genders->contains('female')) {
            return 'Female';
        }

        $this->markTestSkipped('The caller pool has no recognisable gender lane.');
    }

    /**
     * A first timer with every NOT NULL column filled in.
     */
    private function createFirstTimer(string $gender, string $registerDate, $campusId): int
    {
        $unique = (string) random_int(100000, 999999);

        return (int) DB::table('first_timer')->insertGetId([
            'first_name'       => 'Release',
            'last_name'        => 'Testcase' . $unique,
            'phone_number'     => '000' . $unique,
            'email'            => 'release' . $unique . '@example.test',
            'gender'           => $gender,
            'age'              => '25-34',
            'occupation'       => 'Tester',
            'attendant_type'   => 'Visiting',
            'attendance_count' => 1,
            'status'           => 'Assigned',
            'register_date'    => $registerDate,
            'address'          => 'Test address',
            'campus_id'        => $campusId ?: 1,
        ]);
    }

    // PART TWO

    /**
     * Give a first timer a guide, with a task log entry in the shape the app
     * writes: {"week1": {"task 1": {"text": ..., "logs": [{...}]}}}.
     */
    private function giveGuide(int $firstTimerId, int $guideId, string $registrationStamp): void
    {
        DB::table('member_tracking_followup')->insert([
            'first_timer_id'        => $firstTimerId,
            'tiu_member_id'         => $guideId,
            'followup_mode'         => 'Weekly',
            'followup_date_time'    => '12:00',
            'followup_response'     => '{}',
            'followup_rank'         => '1',
            'tiu_member_comment'    => 'Legacy comment',
            'timeStamp_registered'  => $registrationStamp,
            'followup_response_new' => json_encode([
                'week1' => [
                    'task 1' => [
                        'text' => 'Welcome call',
                        'logs' => [
                            [
                                'timestamp' => '2000-06-05 09:00:00',
                                'guide_id'  => $guideId,
                                'outcome'   => 'Reached',
                                'comment'   => 'Spoke with them and prayed together.',
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }

    private function aGuideId(): int
    {
        $guideId = DB::table('tiu_member')->orderBy('tiu_member_id')->value('tiu_member_id');

        if (! $guideId) {
            $this->markTestSkipped('No tiu_member row to act as the guide.');
        }

        return (int) $guideId;
    }

    /**
     * Only Admins and Super Users may release or reassign a whole window; the
     * buttons are not even rendered for anybody else.
     */
    public function test_the_bulk_actions_are_admin_only()
    {
        $member = $this->nonAdmin();

        $this->actingAs($member)
            ->postJson(route('weeklist.assign-callers'), self::WINDOW)
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => 'Access Denied']);

        $this->actingAs($member)
            ->postJson(route('weeklist.unassign-guides'), self::WINDOW)
            ->assertStatus(403);

        $this->actingAs($member)
            ->get(route('weeklist.guide-history'))
            ->assertRedirect(route('weeklist.index'));

        if (in_array($member->member_role, ['Worker', 'Workers'], true)) {
            $this->actingAs($member)
                ->get('/weeklist?' . http_build_query(self::WINDOW))
                ->assertOk()
                ->assertDontSee('assign-all-callers');
        }
    }

    public function test_the_admin_week_list_shows_the_bulk_buttons()
    {
        $this->actingAs($this->admin())
            ->get('/weeklist?' . http_build_query(self::WINDOW))
            ->assertOk()
            ->assertSee('Assign all callers')
            ->assertSee('Unassign all guides')
            ->assertSee('Guide history');
    }

    /**
     * "Assign all callers": one click saves the round-robin the modal shows for
     * every first timer in the window.
     */
    public function test_an_admin_can_assign_a_caller_to_every_first_timer_in_the_window()
    {
        $admin = $this->admin();
        $gender = $this->genderWithCallers();

        $firstTimerId = $this->createFirstTimer($gender, self::REGISTERED_AT, $admin->campus_id);

        $response = $this->actingAs($admin)->postJson(route('weeklist.assign-callers'), self::WINDOW);

        $response->assertOk()->assertJson(['success' => true, 'assigned' => 1, 'skipped' => 0]);

        $assignment = DB::table('caller_assignments')->where('first_timer_id', $firstTimerId)->first();

        $this->assertNotNull($assignment, 'The bulk action did not save a caller for the first timer.');
        $this->assertNotEmpty($assignment->caller_name);
        $this->assertTrue(
            $this->callers()->pluck('tiu_member_id')->map('intval')->contains((int) $assignment->caller_member_id),
            'The saved caller is not a "' . self::CALLER_SUBGROUP . '" member.'
        );
    }

    /**
     * "Unassign all guides": the guide is released (tiu_member_id = 0) and the
     * follow-up row stays, so the tasks, the log and the comment survive.
     */
    public function test_unassigning_the_guides_keeps_their_follow_up()
    {
        $admin = $this->admin();
        $guideId = $this->aGuideId();

        $firstTimerId = $this->createFirstTimer('Male', self::REGISTERED_AT, $admin->campus_id);
        $this->giveGuide($firstTimerId, $guideId, self::REGISTERED_AT);

        $response = $this->actingAs($admin)->postJson(route('weeklist.unassign-guides'), self::WINDOW);

        $response->assertOk()->assertJson(['success' => true, 'unassigned' => 1]);

        $followUp = DB::table('member_tracking_followup')->where('first_timer_id', $firstTimerId)->first();

        $this->assertNotNull($followUp, 'The bulk unassign deleted the follow-up instead of releasing the guide.');
        $this->assertSame(0, (int) $followUp->tiu_member_id, 'The guide was not released.');
        $this->assertSame('Legacy comment', $followUp->tiu_member_comment, 'The guide\'s comment was lost.');
        $this->assertNotEmpty($followUp->followup_response_new, 'The guide\'s task log was lost.');
        $this->assertSame(
            'Unassigned',
            DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status')
        );
    }

    /**
     * The 8-week rule used to delete the follow-up outright. It now archives the
     * row first, so the last guide and the last comment are still on record.
     */
    public function test_the_eight_week_rule_archives_the_follow_up_before_deleting_it()
    {
        $admin = $this->admin();
        $guideId = $this->aGuideId();

        $registeredAt = now()->subWeeks(9)->format('Y-m-d H:i:s');
        $firstTimerId = $this->createFirstTimer('Male', $registeredAt, $admin->campus_id);
        $this->giveGuide($firstTimerId, $guideId, $registeredAt);

        $result = $this->archive->unassignAtEightWeeks(false, [$firstTimerId]);
        $lines = implode("\n", $result['lines']);

        $this->assertSame(1, $result['checked'], $lines);
        $this->assertSame(1, $result['unassigned'], $lines);
        $this->assertSame(1, $result['archived'], $lines);

        $this->assertNull(
            DB::table('member_tracking_followup')->where('first_timer_id', $firstTimerId)->first(),
            'The follow-up row should have been deleted after being archived.'
        );

        $archived = DB::table('member_tracking_followup_archive')->where('first_timer_id', $firstTimerId)->first();

        $this->assertNotNull($archived, 'The follow-up was deleted without being archived.');
        $this->assertSame($guideId, (int) $archived->tiu_member_id, 'The last guide was not kept.');
        $this->assertSame('8-week auto-unassign', $archived->archive_reason);
        $this->assertNotEmpty($archived->archived_at);
        $this->assertSame('Legacy comment', $archived->tiu_member_comment);
        $this->assertNotEmpty($archived->followup_response_new, 'The task log was not copied.');

        // What the "Guide history" page reads back out of the archived row.
        $activity = GuideArchiveService::lastActivity($archived);

        $this->assertSame('Spoke with them and prayed together.', $activity['comment']);
        $this->assertSame('Reached', $activity['outcome']);
        $this->assertSame('Welcome call', $activity['task']);

        $this->assertSame(
            'Unassigned',
            DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status')
        );
    }

    /**
     * If the copy cannot be written, the release does not happen: losing the
     * guide's work is worse than keeping the guide one more week.
     */
    public function test_a_first_timer_is_not_released_when_the_archive_fails()
    {
        $admin = $this->admin();
        $guideId = $this->aGuideId();

        $registeredAt = now()->subWeeks(9)->format('Y-m-d H:i:s');
        $firstTimerId = $this->createFirstTimer('Male', $registeredAt, $admin->campus_id);
        $this->giveGuide($firstTimerId, $guideId, $registeredAt);

        // The archive table is unreachable (drift, permissions, ...).
        $service = new class extends GuideArchiveService {
            public function archiveRow(object $row, string $reason): bool
            {
                return false;
            }
        };

        $result = $service->unassignAtEightWeeks(false, [$firstTimerId]);
        $lines = implode("\n", $result['lines']);

        $this->assertSame(0, $result['unassigned'], $lines);
        $this->assertSame(0, $result['archived'], $lines);
        $this->assertSame(1, $result['skipped'], $lines);
        $this->assertStringContainsString('could not be archived', $lines);

        $followUp = DB::table('member_tracking_followup')->where('first_timer_id', $firstTimerId)->first();

        $this->assertNotNull($followUp, 'The follow-up was deleted even though it could not be archived.');
        $this->assertSame($guideId, (int) $followUp->tiu_member_id, 'The guide was released without an archived copy.');
        $this->assertSame('Legacy comment', $followUp->tiu_member_comment);
        $this->assertSame(
            'Assigned',
            DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status')
        );
    }

    /**
     * --dry-run is the safe way to see who the weekly job would release.
     */
    public function test_the_dry_run_reports_the_release_without_writing_anything()
    {
        $admin = $this->admin();
        $guideId = $this->aGuideId();

        $registeredAt = now()->subWeeks(9)->format('Y-m-d H:i:s');
        $firstTimerId = $this->createFirstTimer('Male', $registeredAt, $admin->campus_id);
        $this->giveGuide($firstTimerId, $guideId, $registeredAt);

        $result = $this->archive->unassignAtEightWeeks(true, [$firstTimerId]);

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['unassigned']);
        $this->assertSame(0, $result['archived']);

        $this->assertNotNull(
            DB::table('member_tracking_followup')->where('first_timer_id', $firstTimerId)->first(),
            'The dry run touched the live follow-up.'
        );
        $this->assertNull(DB::table('member_tracking_followup_archive')->where('first_timer_id', $firstTimerId)->first());
        $this->assertSame('Assigned', DB::table('first_timer')->where('first_timer_id', $firstTimerId)->value('status'));
    }

    /**
     * The scheduled job itself: --dry-run must reach the service through the
     * command and write nothing (this is the wiring the deploy runs).
     */
    public function test_the_command_runs_the_service_in_dry_run_mode()
    {
        $exitCode = Artisan::call('firsttimers:auto-unassign', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('DRY RUN: nothing will be written.', $output);
        $this->assertStringContainsString('Follow-ups kept in the archive: 0', $output);

        $this->assertSame(0, DB::table('member_tracking_followup_archive')->count(), 'The dry run archived a row.');
    }

    /**
     * The page that makes the archive useful: who guided them, and what the guide
     * last said.
     */
    public function test_the_guide_history_page_shows_the_archived_release()
    {
        $admin = $this->admin();
        $guideId = $this->aGuideId();

        $registeredAt = now()->subWeeks(9)->format('Y-m-d H:i:s');
        $firstTimerId = $this->createFirstTimer('Male', $registeredAt, $admin->campus_id);
        $this->giveGuide($firstTimerId, $guideId, $registeredAt);

        $this->archive->unassignAtEightWeeks(false, [$firstTimerId]);

        $firstTimer = DB::table('first_timer')->where('first_timer_id', $firstTimerId)->first();

        $this->actingAs($admin)
            ->get(route('weeklist.guide-history'))
            ->assertOk()
            ->assertSee($firstTimer->first_name . ' ' . $firstTimer->last_name)
            ->assertSee('8-week auto-unassign')
            ->assertSee('Spoke with them and prayed together.');

        // The app is Bootstrap-themed; the paginator must not fall back to the
        // framework's Tailwind markup (nothing extra renders here: there is a
        // single page, so the pagination markup is empty by design).
        $this->assertSame('pagination::bootstrap-4', Paginator::$defaultView);
    }
}
