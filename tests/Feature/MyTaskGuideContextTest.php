<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin drill-down: Task Overview (`/aoverview`) -> a guide's follow-up list
 * (`/my-tasks?guide_id=..`) -> that first timer's tasks
 * (`/my-tasks/{id}/tasks?guide_id=..`).
 *
 * `MyTaskController` resolves the tracking record with `tiu_member_id = guide_id`,
 * so every hop has to forward `guide_id`. The "Manage Tasks" button used to link
 * to `/my-tasks/{id}/tasks` without it, which made `showTasks()` look up the
 * *admin's own* record and render "No tasks found for this record.".
 *
 * Read-only on purpose: the suite runs against the application's real
 * connection, so these tests never POST (that would rewrite
 * `member_tracking_followup`).
 */
class MyTaskGuideContextTest extends TestCase
{
    /**
     * Members with the profile fields `CheckProfileComplete` demands, otherwise
     * the request is redirected to the profile page instead of the task list.
     */
    private function memberQuery()
    {
        return \App\Models\TiuMember::query()
            ->whereNotNull('marital_status')
            ->whereNotNull('age')
            ->whereNotNull('occupation')
            ->whereNotNull('next_of_kin_name')
            ->whereNotNull('next_of_kin_phone')
            ->whereNotNull('campus_id')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('birthday')
                    ->whereColumn('birthday.tiu_member_id', 'tiu_member.tiu_member_id')
                    ->whereNotNull('birthday.birthday');
            });
    }

    /**
     * An Admin / Super User, i.e. a member allowed to manage another guide.
     */
    private function admin()
    {
        $admin = $this->memberQuery()->whereIn('member_role', ['Admin', 'Super User'])->first();

        if (! $admin) {
            $this->markTestSkipped('No Admin/Super User with a complete profile available.');
        }

        return $admin;
    }

    /**
     * A follow-up record that Task Overview lists and that still has a task to
     * manage (its JSON contains a `"display": true` entry).
     */
    private function tracking()
    {
        $tracking = DB::table('member_tracking_followup as mtf')
            ->join('first_timer as ft', 'mtf.first_timer_id', '=', 'ft.first_timer_id')
            ->whereNotNull('mtf.tiu_member_id')
            ->where('mtf.tiu_member_id', '!=', '')
            ->where(function ($query) {
                $query->where('mtf.department_id', 23)->orWhereNull('mtf.department_id');
            })
            ->where('mtf.followup_response_new', 'like', '%"display":true%')
            ->select('mtf.tiu_member_id', 'mtf.first_timer_id', 'mtf.followup_response_new')
            ->first();

        if (! $tracking) {
            $this->markTestSkipped('No follow-up record with a displayed task available.');
        }

        return $tracking;
    }

    private function guideName($tiu_member_id): string
    {
        $member = DB::table('tiu_member')
            ->where('tiu_member_id', $tiu_member_id)
            ->first(['first_name', 'last_name']);

        return trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: 'Unnamed Guide';
    }

    /**
     * Mirrors the view: a task that still offers the outcome/comment form.
     */
    private function hasOpenTask($json): bool
    {
        foreach (json_decode($json, true) ?: [] as $weekTasks) {
            if (! is_array($weekTasks)) {
                continue;
            }

            foreach ($weekTasks as $task) {
                if (! is_array($task) || empty($task['display'])) {
                    continue;
                }

                if (! in_array($task['status'] ?? 'Not Started', ['Approved', 'Skipped'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The admin's list is rendered for the guide and the "Manage Tasks" button
     * forwards the guide context to the task page.
     */
    public function test_manage_tasks_link_carries_the_guide_context()
    {
        $admin = $this->admin();
        $tracking = $this->tracking();
        $guideName = $this->guideName($tracking->tiu_member_id);

        $response = $this->actingAs($admin)->get('/my-tasks?' . http_build_query([
            'guide_id' => $tracking->tiu_member_id,
            'guide_name' => $guideName,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Viewing on behalf of', false);
        $response->assertSee($guideName, false);

        // The reported defect: the button dropped both parameters.
        $taskUri = route('my-tasks.tasks', $tracking->first_timer_id);

        $this->assertMatchesRegularExpression(
            '#href="' . preg_quote($taskUri, '#') . '\?[^"]*guide_id=' . preg_quote((string) $tracking->tiu_member_id, '#') . '#',
            $response->getContent(),
            'The Manage Tasks link must carry ?guide_id= so the task page opens the guide\'s record.'
        );
    }

    /**
     * With `guide_id` the task page shows the guide's tasks (and logs the
     * admin's outcome against that guide); without it the record is the admin's
     * own, which is what made the tasks disappear.
     */
    public function test_admin_task_page_loads_the_guides_record_when_guide_id_is_present()
    {
        $admin = $this->admin();
        $tracking = $this->tracking();
        $guideName = $this->guideName($tracking->tiu_member_id);

        $query = http_build_query([
            'guide_id' => $tracking->tiu_member_id,
            'guide_name' => $guideName,
        ]);

        $withContext = $this->actingAs($admin)
            ->get('/my-tasks/' . $tracking->first_timer_id . '/tasks?' . $query);

        $withContext->assertStatus(200);
        $withContext->assertDontSee('No tasks found for this record.');
        $withContext->assertDontSee('No tracking record for this first-timer under');
        $withContext->assertSee('Managing on behalf of', false);

        if ($this->hasOpenTask($tracking->followup_response_new)) {
            // Attribution plumbing: the form posts the guide whose record is
            // managed, so the comment/update is logged against that guide.
            $withContext->assertSee('name="performing_guide_id" value="' . $tracking->tiu_member_id . '"', false);
            $withContext->assertSee("fields.push({name: 'performing_guide_id'", false);
        }

        $adminId = $admin->tiu_member_id ?? $admin->id;
        $adminOwnRecord = DB::table('member_tracking_followup')
            ->where('tiu_member_id', $adminId)
            ->where('first_timer_id', $tracking->first_timer_id)
            ->exists();

        if ((string) $adminId !== (string) $tracking->tiu_member_id && ! $adminOwnRecord) {
            $withoutContext = $this->actingAs($admin)
                ->get('/my-tasks/' . $tracking->first_timer_id . '/tasks');

            $withoutContext->assertStatus(200);
            $withoutContext->assertSee('No tasks found for this record.');
        }
    }

    /**
     * Only Admins / Super Users may open somebody else's list.
     */
    public function test_non_admins_are_always_scoped_to_their_own_tasks()
    {
        $tracking = $this->tracking();

        $member = $this->memberQuery()
            ->where(function ($query) {
                $query->whereNotIn('member_role', ['Admin', 'Super User'])
                    ->orWhereNull('member_role');
            })
            ->first();

        if (! $member) {
            $this->markTestSkipped('No non-admin member with a complete profile available.');
        }

        if ((string) ($member->tiu_member_id ?? $member->id) === (string) $tracking->tiu_member_id) {
            $this->markTestSkipped('The sampled follow-up record already belongs to that member.');
        }

        $list = $this->actingAs($member)->get('/my-tasks?' . http_build_query([
            'guide_id' => $tracking->tiu_member_id,
            'guide_name' => 'Someone Else',
        ]));

        $list->assertStatus(200);
        // `admin-viewing-banner` also appears in the always-rendered <style>
        // block, so assert on the banner markup instead.
        $list->assertDontSee('Back to Task Overview', false);
        $list->assertDontSee('Viewing on behalf of', false);

        $tasks = $this->actingAs($member)
            ->get('/my-tasks/' . $tracking->first_timer_id . '/tasks?guide_id=' . $tracking->tiu_member_id);

        $tasks->assertStatus(200);
        $tasks->assertDontSee('Managing on behalf of', false);
        $tasks->assertDontSee('name="performing_guide_id"', false);
    }

    /**
     * A stale link (first timer deleted) falls back to the same guide's list.
     */
    public function test_missing_first_timer_redirect_keeps_the_guide_context()
    {
        $admin = $this->admin();
        $tracking = $this->tracking();
        $guideName = $this->guideName($tracking->tiu_member_id);

        $missingId = (int) DB::table('first_timer')->max('first_timer_id') + 1;

        if (DB::table('first_timer')->where('first_timer_id', $missingId)->exists()) {
            $this->markTestSkipped('Could not build a first timer id that is guaranteed to be absent.');
        }

        $response = $this->actingAs($admin)->get('/my-tasks/' . $missingId . '/tasks?' . http_build_query([
            'guide_id' => $tracking->tiu_member_id,
            'guide_name' => $guideName,
        ]));

        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('/my-tasks?', $location);
        $this->assertStringContainsString('guide_id=' . $tracking->tiu_member_id, $location);
        $this->assertStringContainsString('guide_name=' . rawurlencode($guideName), $location);
    }
}
