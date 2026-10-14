<?php

namespace Tests\Feature;

use App\Services\ApiTokenService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The /api/* guard. The token is the credential, so nothing a client sends may
 * name a member the token does not.
 *
 * Deliberately no RefreshDatabase, matching SyncSchemaTest: the suite runs
 * against the application's real connection and only reads.
 */
class ApiTokenMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'api.token.key' => 'feature-test-signing-key',
            'api.token.ttl_days' => 90,
        ]);

        // Stands in for the ~35 protected routes: it reports which member the
        // guard decided the caller is.
        Route::middleware(['verify.token'])->get('/api/testing/whoami', function () {
            return response()->json([
                'user_id' => request()->user_id,
                'auth_id' => Auth::id(),
            ]);
        });
    }

    public function test_a_request_without_a_token_is_rejected()
    {
        $this->getJson('/api/testing/whoami')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'code' => 'token_missing']);
    }

    public function test_the_legacy_forgeable_token_is_rejected()
    {
        // What used to be a valid credential for any member id.
        $forged = base64_encode('1|'.time());

        $this->withHeaders(['Authorization' => 'Bearer '.$forged])
            ->getJson('/api/testing/whoami')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'code' => 'token_invalid']);
    }

    public function test_a_token_with_a_tampered_payload_is_rejected()
    {
        $segments = explode('.', app(ApiTokenService::class)->issue(999999)['token']);
        $other = explode('.', $this->mint(['sub' => '999998', 'exp' => time() + 3600]));

        // Genuine signature, somebody else's claims.
        $tampered = $segments[0].'.'.$other[1].'.'.$segments[2];

        $this->withHeaders(['Authorization' => 'Bearer '.$tampered])
            ->getJson('/api/testing/whoami')
            ->assertStatus(401)
            ->assertJson(['code' => 'token_invalid']);
    }

    public function test_an_expired_token_tells_the_client_to_sign_in_again()
    {
        $expired = $this->mint(['sub' => '999999', 'exp' => time() - 1]);

        $this->withHeaders(['Authorization' => 'Bearer '.$expired])
            ->getJson('/api/testing/whoami')
            ->assertStatus(401)
            ->assertJson(['code' => 'token_expired']);
    }

    public function test_a_valid_token_authenticates_the_member_it_names()
    {
        $memberId = $this->aMemberId();

        if (!$memberId) {
            $this->markTestSkipped('No tiu_member row to authenticate as.');
        }

        $token = app(ApiTokenService::class)->issue($memberId)['token'];

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/testing/whoami');

        $response->assertOk();
        $this->assertSame((string) $memberId, (string) $response->json('user_id'));
        $this->assertSame((string) $memberId, (string) $response->json('auth_id'));
    }

    public function test_a_user_id_in_the_request_cannot_override_the_token()
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

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/testing/whoami?user_id='.$otherId.'&uid='.$otherId);

        $response->assertOk();
        $this->assertSame((string) $memberId, (string) $response->json('user_id'));
    }

    public function test_a_token_for_a_member_that_no_longer_exists_is_rejected()
    {
        $token = app(ApiTokenService::class)->issue(999999999)['token'];

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/testing/whoami')
            ->assertStatus(401)
            ->assertJson(['code' => 'user_not_found']);
    }

    public function test_a_token_for_a_disabled_account_is_rejected()
    {
        $disabledId = DB::table('tiu_member')->where('status', 2)->value('tiu_member_id');

        if (!$disabledId) {
            $this->markTestSkipped('No disabled (status = 2) member row to test against.');
        }

        $token = app(ApiTokenService::class)->issue($disabledId)['token'];

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/testing/whoami')
            ->assertStatus(401)
            ->assertJson(['code' => 'account_disabled']);
    }

    private function aMemberId()
    {
        return DB::table('tiu_member')->orderBy('tiu_member_id')->value('tiu_member_id');
    }

    /**
     * Mints a token the way any HS256 producer would, so the guard is tested
     * against the format rather than against its own issuer.
     */
    private function mint(array $claims)
    {
        $encode = function ($value) {
            return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        };

        $header = $encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = $encode(json_encode($claims));

        return $header.'.'.$payload.'.'.$encode(
            hash_hmac('sha256', $header.'.'.$payload, 'feature-test-signing-key', true)
        );
    }
}
