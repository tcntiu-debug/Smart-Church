<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The "Bio Details" modal (`#detailsModal`) on `/my-tasks/{id}/tasks`.
 *
 * `first_timer` has no `created_at` column, so the modal's "Registered On" row
 * always printed "N/A" although the table carries the timestamp in
 * `register_date`. The modal now reads that column (defensively, because it is
 * a user-entered varchar) and shows the guest type and the age range next to it;
 * the header chips reuse the same values.
 *
 * Read-only like the other My Tasks tests: the suite runs against the
 * application's real connection, so these tests never POST.
 */
class MyTaskProfileModalTest extends TestCase
{
    /**
     * Members the profile-completeness middleware lets through (same query as
     * `MyTaskGuideContextTest`).
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
     * A first timer carrying all three fields the modal prints, with a
     * `register_date` that really parses (the column is a free-text varchar, so
     * newer rows are scanned until one qualifies).
     */
    private function firstTimer()
    {
        $candidates = DB::table('first_timer')
            ->whereNotNull('register_date')
            ->where('register_date', '!=', '')
            ->whereNotNull('age')
            ->where('age', '!=', '')
            ->whereNotNull('attendant_type')
            ->where('attendant_type', '!=', '')
            ->orderByDesc('first_timer_id')
            ->limit(50)
            ->get();

        $firstTimer = null;

        foreach ($candidates as $candidate) {
            $age = strtolower(trim((string) $candidate->age));

            if (strtotime($candidate->register_date) !== false
                && ! in_array($age, ['select', 'n/a', 'none', '-'], true)) {
                $firstTimer = $candidate;
                break;
            }
        }

        if (! $firstTimer) {
            $this->markTestSkipped('No first timer with a parseable register_date, age range and guest type available.');
        }

        return $firstTimer;
    }

    /**
     * Opens the task timeline of a first timer as a member with a complete
     * profile and returns the rendered response.
     */
    private function timeline($firstTimer = null)
    {
        $member = $this->memberQuery()->first();

        if (! $member) {
            $this->markTestSkipped('No member with a complete profile available to open the task timeline.');
        }

        $firstTimer = $firstTimer ?: $this->firstTimer();

        $response = $this->actingAs($member)->get('/my-tasks/' . $firstTimer->first_timer_id . '/tasks');

        $response->assertStatus(200);

        return [$response, $firstTimer];
    }

    /**
     * A first timer that answered the two optional background questions the
     * modal now prints. Both columns are nullable, so the newest row carrying
     * answers is used instead of assuming every record has them.
     */
    private function firstTimerWithBackground()
    {
        $firstTimer = DB::table('first_timer')
            ->whereNotNull('marital_status')
            ->where('marital_status', '!=', '')
            ->whereNotNull('how_did_you_hear')
            ->where('how_did_you_hear', '!=', '')
            ->orderByDesc('first_timer_id')
            ->first();

        if (! $firstTimer) {
            $this->markTestSkipped('No first timer with a marital status and a "how did you hear" answer available.');
        }

        return $firstTimer;
    }

    /**
     * The three fields the modal was missing, each formatted from the row that
     * was requested (the registration date in the same `M d, Y h:i A` shape the
     * admin and lead tables use for that column).
     */
    public function test_bio_details_modal_shows_registration_date_guest_type_and_age_range()
    {
        [$response, $firstTimer] = $this->timeline();

        $response->assertSee('Age Range');
        $response->assertSee('Guest Type');
        $response->assertSee('Registered On');

        $response->assertSee(\Carbon\Carbon::parse($firstTimer->register_date)->format('M d, Y h:i A'));
        $response->assertSee($firstTimer->age);
        $response->assertSee($firstTimer->attendant_type);

        // The registration row is a tile now, not the old right-aligned cell.
        $response->assertSee('<i class="fa fa-calendar-check"></i>Registered On</span>', false);
        $response->assertDontSee('Registered On</td>', false);

        // ...and it sits beside Full Name, so the name and the join date share
        // the first line of the card instead of the date closing the card.
        $this->assertMatchesRegularExpression(
            '/Full Name<\/span>\s*<span class="profile-tile-value">.*?<\/span>\s*<\/div>\s*<\/div>\s*'
            . '<div class="col-6">\s*<div class="profile-tile">\s*'
            . '<span class="profile-tile-label"><i class="fa fa-calendar-check"><\/i>Registered On<\/span>/s',
            $response->getContent()
        );

        $phone = trim((string) $firstTimer->phone_number);

        if ($phone !== '') {
            $response->assertSee('href="tel:' . $phone . '"', false);
        }
    }

    /**
     * The background questions from the registration form ("Marital Status" and
     * "How did you hear about us?") were stored on `first_timer` but never shown
     * in the Bio Details modal, so a guide had to open the admin table to see
     * them. They are tiles next to Guest Type / above Address now.
     */
    public function test_bio_details_modal_shows_marital_status_and_how_did_you_hear()
    {
        $firstTimer = $this->firstTimerWithBackground();

        [$response] = $this->timeline($firstTimer);

        $response->assertSee('<i class="fa fa-heart"></i>Marital Status</span>', false);
        $response->assertSee('<i class="fa fa-bullhorn"></i>How Did You Hear</span>', false);

        $response->assertSee(
            '<span class="profile-tile-value">' . e($firstTimer->marital_status) . '</span>',
            false
        );
        $response->assertSee(
            '<span class="profile-tile-value">' . e($firstTimer->how_did_you_hear) . '</span>',
            false
        );
    }

    /**
     * The header chips read the same facts at a glance: "Joined <date>" (which
     * never rendered while the page looked for `created_at`) plus the age range.
     */
    public function test_header_chips_show_joined_date_and_age_range()
    {
        [$response, $firstTimer] = $this->timeline();

        $response->assertSee(
            'Joined ' . \Carbon\Carbon::parse($firstTimer->register_date)->format('M d, Y'),
            false
        );
        $response->assertSee('<i class="fa fa-birthday-cake"></i> ' . $firstTimer->age, false);
    }
}
