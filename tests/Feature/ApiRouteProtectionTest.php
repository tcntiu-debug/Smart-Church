<?php

namespace Tests\Feature;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\VerifyApiToken;
use App\Services\ApiTokenService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Route-level authorisation for /api/*.
 *
 * ApiTokenMiddlewareTest proves who the caller is; this proves which routes are
 * reachable without a token. The account endpoints used to sit in the anonymous
 * group and took the member id from the request, so an unauthenticated caller
 * could read or rewrite any member's profile.
 */
class ApiRouteProtectionTest extends TestCase
{
    public function test_account_endpoints_reject_anonymous_callers()
    {
        $endpoints = [
            ['GET', '/api/user'],
            ['POST', '/api/update-profile'],
            ['POST', '/api/check-profile'],
            ['POST', '/api/check-policy'],
            ['POST', '/api/sign-policy'],
        ];

        foreach ($endpoints as $endpoint) {
            list($method, $uri) = $endpoint;

            $response = $method === 'GET'
                ? $this->getJson($uri)
                : $this->postJson($uri, ['user_id' => $this->aMemberId()]);

            $response->assertStatus(401);
            $this->assertSame(
                'token_missing',
                $response->json('code'),
                "{$method} {$uri} is reachable without a token."
            );
        }
    }

    public function test_the_debug_endpoint_is_gone()
    {
        $this->getJson('/api/test-db')->assertStatus(404);
    }

    public function test_an_anonymous_caller_can_still_sign_in()
    {
        // No token and a wrong password: the point is that the route is reachable
        // and that the answer comes from the credentials, not from the token guard.
        $response = $this->postJson('/api/login', [
            'email' => 'nobody-'.time().'@example.test',
            'password' => 'wrong',
        ]);

        $response->assertStatus(401);
        $this->assertNotSame('token_missing', $response->json('code'));
    }

    public function test_the_user_endpoint_reports_the_member_the_token_names()
    {
        $memberId = $this->aMemberId();

        if (!$memberId) {
            $this->markTestSkipped('No tiu_member row to authenticate as.');
        }

        $token = app(ApiTokenService::class)->issue($memberId)['token'];

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user');

        $response->assertOk();
        $this->assertSame((string) $memberId, (string) $response->json('data.id'));
    }

    public function test_the_user_endpoint_ignores_a_user_id_from_the_request()
    {
        $memberId = $this->aMemberId();

        if (!$memberId) {
            $this->markTestSkipped('No tiu_member row to authenticate as.');
        }

        $otherId = DB::table('tiu_member')
            ->where('tiu_member_id', '!=', $memberId)
            ->value('tiu_member_id');

        if (!$otherId) {
            $this->markTestSkipped('Needs a second tiu_member row.');
        }

        $token = app(ApiTokenService::class)->issue($memberId)['token'];

        // Before the guard merged its claims, ?user_id= chose whose profile came back.
        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/user?user_id='.$otherId);

        $response->assertOk();
        $this->assertSame((string) $memberId, (string) $response->json('data.id'));
        $this->assertNotSame((string) $otherId, (string) $response->json('data.id'));
    }

    public function test_update_profile_rejects_a_writeless_anonymous_call()
    {
        // Only the rejection is asserted on purpose: a call that got through would
        // write to the live member row, and this suite only ever reads.
        $response = $this->postJson('/api/update-profile', [
            'user_id' => $this->aMemberId(),
            'marital_status' => 'Single',
            'age' => 30,
            'occupation' => 'Test',
            'next_of_kin_name' => 'Test',
            'next_of_kin_phone' => '000',
            'birthday' => '01 Jan 1990',
        ]);

        $response->assertStatus(401);
        $this->assertSame('token_missing', $response->json('code'));
    }

    /**
     * The two endpoints the mobile Action Board calls. Both used to answer
     * {"message":"Unauthenticated."} to a caller holding a valid token, because
     * PendingTasksController::__construct put the session guard on its API
     * methods and Authenticate - being in $middlewarePriority - ran first.
     */
    public function test_a_valid_token_reaches_the_pending_tasks_endpoints()
    {
        config(['api.token.key' => 'feature-test-signing-key']);

        $memberId = $this->aMemberId();

        if (!$memberId) {
            $this->markTestSkipped('No tiu_member row to authenticate as.');
        }

        $headers = ['Authorization' => 'Bearer '.app(ApiTokenService::class)->issue($memberId)['token']];

        $board = $this->withHeaders($headers)->getJson('/api/pending-tasks');

        $board->assertOk()->assertJson(['success' => true]);
        $this->assertSame(count($board->json('data')), $board->json('total'));
        $this->assertNotNull($board->json('campus_id'), 'The screen prints data.campus_id.');

        // Every card renders these, so a missing key is a blank field in the app.
        $keys = [
            'tracking_id', 'ft_first_name', 'ft_last_name', 'ft_phone', 'attendant_type',
            'week_label', 'next_task', 'latest_pending_log',
            'guide_first_name', 'guide_last_name', 'guide_phone', 'guide_touchpoint',
        ];

        foreach ($board->json('data') as $row) {
            foreach ($keys as $key) {
                $this->assertArrayHasKey($key, $row, "Task row is missing {$key}.");
            }
        }

        $touchpoints = $this->withHeaders($headers)->getJson('/api/pending-tasks/touchpoints');

        $touchpoints->assertOk()->assertJson(['success' => true]);
        $this->assertIsArray($touchpoints->json('data'));

        foreach ($touchpoints->json('data') as $row) {
            // The picker draws one item per row from tp.subgroup.
            $this->assertArrayHasKey('subgroup', $row);
        }
    }

    /**
     * Authenticate implements AuthenticatesRequests, and that interface sits in
     * the framework's $middlewarePriority, so the session guard runs before the
     * group's verify.token no matter what order routes/api.php lists them in. Any
     * token-protected /api route that also carries 'auth' is therefore
     * unreachable with a token: the 401 comes from the session guard first, which
     * is what made /api/pending-tasks look like a token problem.
     */
    public function test_no_token_protected_api_route_also_carries_the_session_guard()
    {
        $router = app('router');
        $checked = 0;

        foreach ($router->getRoutes() as $route) {
            if (strpos($route->uri(), 'api/') !== 0) {
                continue;
            }

            $middleware = $router->gatherRouteMiddleware($route);

            if (!in_array(VerifyApiToken::class, $middleware, true)) {
                continue; // Anonymous by design: login, register, and friends.
            }

            $checked++;

            $this->assertNotContains(
                Authenticate::class,
                $middleware,
                implode('|', $route->methods()).' '.$route->uri()
                    .' is token protected but also sits behind the session guard.'
            );
        }

        $this->assertGreaterThan(30, $checked, 'Expected to inspect the token-protected slice of /api/*.');
    }

    /**
     * Every /api action has to exist. Three routes used to point at methods that
     * were never written (PendingTasksController::getApiPendingTasks and
     * getApiTouchpoints, FirstTimerController::show); because the session guard
     * answered first, two of them had been hiding the resulting 500 as a 401.
     */
    public function test_every_api_route_points_at_a_method_that_exists()
    {
        $router = app('router');
        $checked = 0;

        foreach ($router->getRoutes() as $route) {
            if (strpos($route->uri(), 'api/') !== 0) {
                continue;
            }

            $action = $route->getAction('uses');

            if (!is_string($action) || strpos($action, '@') === false) {
                continue; // Closures are fine; /api/* has none today.
            }

            list($controller, $method) = explode('@', $action);
            $checked++;

            $this->assertTrue(
                method_exists($controller, $method),
                implode('|', $route->methods()).' '.$route->uri().' points at '
                    .$controller.'::'.$method.', which does not exist.'
            );
        }

        $this->assertGreaterThan(30, $checked, 'Expected to inspect the /api/* actions.');
    }

    private function aMemberId()
    {
        return DB::table('tiu_member')->orderBy('tiu_member_id')->value('tiu_member_id');
    }
}
