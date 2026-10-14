<?php

namespace Tests\Unit;

use App\Services\ApiTokenService;
use RuntimeException;
use Tests\TestCase;

/**
 * The token that replaced the forgeable `base64_encode($id.'|'.time())` one.
 *
 * Every rejection is asserted by its exact `error`, because the middleware turns
 * those into the codes clients see (`token_invalid` vs `token_expired`).
 */
class ApiTokenServiceTest extends TestCase
{
    const KEY = 'unit-test-signing-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'api.token.key' => self::KEY,
            'api.token.ttl_days' => 90,
            'api.token.issuer' => 'https://api.example.test',
            'api.token.clock_skew' => 300,
        ]);
    }

    public function test_an_issued_token_verifies_and_names_the_member()
    {
        $issued = $this->service()->issue(468);

        $result = $this->service()->verify($issued['token']);

        $this->assertTrue($result['valid'], 'A freshly issued token was rejected.');
        $this->assertNull($result['error']);
        $this->assertSame('468', (string) $result['claims']['sub']);
        $this->assertSame('https://api.example.test', $result['claims']['iss']);
        $this->assertSame($issued['expires_at'], (int) $result['claims']['exp']);
        $this->assertNotEmpty($result['claims']['jti']);
    }

    public function test_every_token_is_unique()
    {
        $first = $this->service()->issue(468);
        $second = $this->service()->issue(468);

        $this->assertNotSame($first['token'], $second['token']);
    }

    public function test_the_legacy_decodable_token_is_rejected()
    {
        // The old credential: base64 of "id|timestamp", decoded and trusted.
        $this->assertSame(
            'malformed',
            $this->service()->verify(base64_encode('468|'.time()))['error']
        );
    }

    public function test_an_empty_or_absent_token_is_reported_as_missing()
    {
        $this->assertSame('missing', $this->service()->verify('')['error']);
        $this->assertSame('missing', $this->service()->verify(null)['error']);
    }

    public function test_a_token_signed_with_a_different_key_is_rejected()
    {
        $foreign = $this->mint($this->claims(468), self::KEY.'-other');

        $this->assertSame('bad_signature', $this->service()->verify($foreign)['error']);
    }

    public function test_a_tampered_payload_is_rejected()
    {
        $segments = explode('.', $this->mint($this->claims(468)));

        // Keep the genuine signature, swap in claims for a different member.
        $tampered = $segments[0].'.'.explode('.', $this->mint($this->claims(999)))[1].'.'.$segments[2];

        $this->assertSame('bad_signature', $this->service()->verify($tampered)['error']);
    }

    public function test_an_unsigned_alg_none_token_is_rejected()
    {
        $header = $this->b64(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = $this->b64(json_encode($this->claims(468)));

        $this->assertSame(
            'bad_signature',
            $this->service()->verify($header.'.'.$payload.'.')['error']
        );
    }

    public function test_a_header_claiming_another_algorithm_is_rejected()
    {
        // Signed with the real key, but the header says HS512: the header is
        // attacker input, so it never gets to choose how the signature is read.
        $token = $this->mint($this->claims(468), self::KEY, ['alg' => 'HS512', 'typ' => 'JWT']);

        $this->assertSame('unsupported_algorithm', $this->service()->verify($token)['error']);
    }

    public function test_an_expired_token_is_reported_as_expired()
    {
        $claims = $this->claims(468);
        $claims['exp'] = time() - 1;

        $this->assertSame('expired', $this->service()->verify($this->mint($claims))['error']);
    }

    public function test_a_token_without_an_expiry_is_rejected()
    {
        $claims = $this->claims(468);
        unset($claims['exp']);

        $this->assertSame('expired', $this->service()->verify($this->mint($claims))['error']);
    }

    public function test_a_token_issued_in_the_future_is_rejected()
    {
        $claims = $this->claims(468);
        $claims['iat'] = time() + 3600;

        $this->assertSame('not_yet_valid', $this->service()->verify($this->mint($claims))['error']);
    }

    public function test_a_token_without_a_member_id_is_rejected()
    {
        $claims = $this->claims(468);
        $claims['sub'] = '';

        $this->assertSame('malformed', $this->service()->verify($this->mint($claims))['error']);
    }

    public function test_the_signing_key_must_be_configured()
    {
        config(['api.token.key' => '']);

        $this->expectException(RuntimeException::class);

        $this->service()->issue(468);
    }

    /**
     * The cPanel deployment adds nothing to the server's .env, so the fallback in the
     * shipped config file has to be real. This reads config/api.php from disk rather
     * than the booted config, because setUp() above deliberately overrides
     * `api.token.key` with a fixture - asserting on the booted value would only be
     * testing the fixture.
     *
     * What it pins down is the line that actually ships:
     * `env('API_TOKEN_KEY', env('APP_KEY'))`. If it starts failing, the deploy has
     * grown a required environment variable.
     */
    public function test_the_shipped_config_falls_back_to_app_key_when_the_token_key_is_unset()
    {
        if (env('API_TOKEN_KEY') !== null && env('API_TOKEN_KEY') !== '') {
            $this->markTestSkipped('API_TOKEN_KEY is set in this environment; the fallback is not in play.');
        }

        $shipped = require base_path('config/api.php');

        $this->assertNotSame(
            '',
            (string) env('APP_KEY'),
            'APP_KEY must be set for the fallback to sign with.'
        );
        $this->assertSame(env('APP_KEY'), $shipped['token']['key']);

        // And the resolved value is a usable secret - APP_KEY's `base64:` form and all.
        config(['api.token.key' => $shipped['token']['key']]);

        $issued = $this->service()->issue(468);

        $this->assertTrue(
            $this->service()->verify($issued['token'])['valid'],
            'A token signed with the fallback key did not verify.'
        );
    }

    /**
     * `php artisan key:generate` writes APP_KEY=base64:…, so the fallback path signs
     * with the decoded secret rather than with the literal "base64:" prefix.
     */
    public function test_a_base64_prefixed_secret_is_decoded_before_signing()
    {
        $raw = str_repeat('k', 32);
        $prefixed = 'base64:'.base64_encode($raw);

        config(['api.token.key' => $prefixed]);

        // Signed with the decoded secret: what the service must read.
        $decoded = $this->mint($this->claims(468), $raw);

        // Signed with the configured string taken literally: what would verify if the
        // prefix were left in place, so it has to be rejected.
        $literal = $this->mint($this->claims(468), $prefixed);

        $this->assertTrue($this->service()->verify($decoded)['valid']);
        $this->assertSame('bad_signature', $this->service()->verify($literal)['error']);
    }

    private function service()
    {
        return new ApiTokenService();
    }

    private function claims($userId)
    {
        return [
            'iss' => 'https://api.example.test',
            'sub' => (string) $userId,
            'iat' => time(),
            'exp' => time() + 86400,
            'jti' => 'test',
            'ver' => 1,
        ];
    }

    private function mint(array $claims, $key = self::KEY, array $header = ['alg' => 'HS256', 'typ' => 'JWT'])
    {
        $h = $this->b64(json_encode($header));
        $p = $this->b64(json_encode($claims));

        return $h.'.'.$p.'.'.$this->b64(hash_hmac('sha256', $h.'.'.$p, $key, true));
    }

    private function b64($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
