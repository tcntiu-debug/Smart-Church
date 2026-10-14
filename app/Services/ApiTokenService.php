<?php

namespace App\Services;

use RuntimeException;

/**
 * Mints and verifies the Bearer tokens used against /api/* by the mobile app and
 * (later) by every third-party front end.
 *
 * A token is an HS256 JWT: base64url(header).base64url(claims).base64url(sig),
 * the signature being an HMAC-SHA256 over the first two segments using
 * config('api.token.key'). The signature is the whole point - without it the
 * token is just a member id in a wrapper, which is exactly what the previous
 * implementation trusted.
 *
 * Why not Sanctum personal access tokens: the auth provider is App\Models\
 * TiuMember with a non-integer primary key, and personal_access_tokens needs a
 * migration that a shell-less cPanel host can only apply through
 * `app:sync-schema`. This class needs no table, no shell and no new package, so
 * it lands over the existing SFTP pipeline.
 *
 * Why a JWT and not a private format: it is the same shape an identity provider
 * (Supabase Auth, or an issuer on the future VPS) hands out, so replacing
 * verify() with a JWKS-based check later does not change a single client.
 *
 * Claims: iss (issuer URL), sub (tiu_member_id), iat, exp, jti, ver.
 */
class ApiTokenService
{
    /**
     * The only algorithm issued, and the only one accepted. The token header is
     * attacker input, so it never gets to select the algorithm - that is how
     * `alg: none` forgeries get in.
     */
    const ALGORITHM = 'HS256';

    /**
     * Bumped when the claim set changes in a way consumers must know about.
     */
    const VERSION = 1;

    /**
     * Mint a token for a member.
     *
     * @param  int|string  $userId  tiu_member_id
     * @return array{token: string, expires_at: int, expires_in: int}
     */
    public function issue($userId)
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + ($this->ttlDays() * 86400);

        $claims = [
            'iss' => (string) config('api.token.issuer'),
            'sub' => (string) $userId,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
            'ver' => self::VERSION,
        ];

        return [
            'token' => $this->encode(['alg' => self::ALGORITHM, 'typ' => 'JWT'], $claims),
            'expires_at' => $expiresAt,
            'expires_in' => $expiresAt - $issuedAt,
        ];
    }

    /**
     * Verify a token.
     *
     * Never throws for a bad token: callers get `valid` plus a machine-readable
     * `error`, so the middleware can tell "expired, sign in again" apart from
     * "forged", and tests can assert the exact reason for a rejection.
     *
     * @param  mixed  $token
     * @return array{valid: bool, claims: array|null, error: string|null}
     */
    public function verify($token)
    {
        if (!is_string($token) || $token === '') {
            return $this->failure('missing');
        }

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return $this->failure('malformed');
        }

        list($headerSegment, $payloadSegment, $signatureSegment) = $parts;

        // The signature is checked before the payload is read, so nothing inside
        // the claims can influence how the claims are validated.
        $expected = hash_hmac(
            'sha256',
            $headerSegment.'.'.$payloadSegment,
            $this->key(),
            true
        );

        if (!hash_equals($expected, $this->base64UrlDecode($signatureSegment))) {
            return $this->failure('bad_signature');
        }

        $header = $this->json($headerSegment);

        if (!is_array($header) || strtoupper((string) ($header['alg'] ?? '')) !== self::ALGORITHM) {
            return $this->failure('unsupported_algorithm');
        }

        $claims = $this->json($payloadSegment);

        if (!is_array($claims) || (string) ($claims['sub'] ?? '') === '') {
            return $this->failure('malformed');
        }

        $now = time();
        $skew = (int) config('api.token.clock_skew', 300);

        if (isset($claims['iat']) && (int) $claims['iat'] > $now + $skew) {
            return $this->failure('not_yet_valid');
        }

        if (!isset($claims['exp']) || (int) $claims['exp'] <= $now) {
            return $this->failure('expired');
        }

        return ['valid' => true, 'claims' => $claims, 'error' => null];
    }

    /**
     * @param  array  $header
     * @param  array  $claims
     * @return string
     */
    private function encode(array $header, array $claims)
    {
        $headerSegment = $this->base64UrlEncode(json_encode($header));
        $payloadSegment = $this->base64UrlEncode(json_encode($claims));

        $signature = hash_hmac(
            'sha256',
            $headerSegment.'.'.$payloadSegment,
            $this->key(),
            true
        );

        return $headerSegment.'.'.$payloadSegment.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * Decode a base64url JWT segment into an array, or null when it is not one.
     *
     * @param  string  $segment
     * @return array|null
     */
    private function json($segment)
    {
        $decoded = json_decode($this->base64UrlDecode($segment), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  string  $value
     * @return string
     */
    private function base64UrlEncode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Always returns a string, so a malformed signature is never compared against
     * a non-string - hash_equals() would throw, turning a bad token into a 500
     * instead of a 401.
     *
     * @param  string  $value
     * @return string
     */
    private function base64UrlDecode($value)
    {
        $padded = strtr((string) $value, '-_', '+/');
        $remainder = strlen($padded) % 4;

        if ($remainder !== 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($padded, true);

        return $decoded === false ? '' : $decoded;
    }

    /**
     * The HMAC secret. Laravel's `base64:` APP_KEY form is accepted, so a stock
     * install needs no extra configuration.
     *
     * @return string
     */
    private function key()
    {
        $key = (string) config('api.token.key');

        if (strncmp($key, 'base64:', 7) === 0) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        if ($key === '') {
            throw new RuntimeException(
                'No API token signing key: set API_TOKEN_KEY (or APP_KEY) in the environment.'
            );
        }

        return $key;
    }

    /**
     * @return int
     */
    private function ttlDays()
    {
        $days = (int) config('api.token.ttl_days', 90);

        // A token that never expires cannot be revoked, and a negative TTL would
        // mint credentials that are already dead.
        return $days > 0 ? $days : 90;
    }

    /**
     * @param  string  $error
     * @return array{valid: bool, claims: array|null, error: string}
     */
    private function failure($error)
    {
        return ['valid' => false, 'claims' => null, 'error' => $error];
    }
}
