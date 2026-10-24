<?php

namespace App\Services;

use RuntimeException;

/**
 * The two pieces of cryptography a Web Push sender needs, done with the openssl
 * extension Laravel already requires - no Composer package.
 *
 *   1. Message encryption (RFC 8291, `aes128gcm` content coding): one AES-128-GCM
 *      record per push, sealed with a key derived from an ECDH secret between a
 *      throw-away P-256 key and the subscription's public key.
 *   2. VAPID (RFC 8292): the ES256 JWT that proves to the push service that the
 *      request comes from this site, signed with the site's own VAPID key.
 *
 * Both are pinned by tests/Feature/WebPushTest.php against the test vector
 * published in RFC 8291 Appendix A, so this file cannot drift silently.
 *
 * Keys are handled in the compact form the Web Push ecosystem uses - base64url of
 * the raw bytes, no PEM:
 *
 *   * VAPID public key  - 65 bytes, `0x04 || X || Y` (uncompressed P-256 point)
 *   * VAPID private key - 32 bytes, the private scalar
 *   * `p256dh` / `auth` - exactly what PushSubscription.toJSON() hands the page
 *
 * PEM is only built internally, because that is what openssl speaks.
 */
class WebPushCrypto
{
    /**
     * Record size advertised in the aes128gcm header. A push payload is one
     * record and every notification is far smaller than this.
     */
    public const RECORD_SIZE = 4096;

    /**
     * The octet that ends the payload inside the encrypted record (RFC 8188:
     * 0x02 marks the last record, 0x01 every record before it).
     */
    private const LAST_RECORD = "\x02";

    /**
     * DER object identifiers for the two things the encoding names: the EC
     * public-key algorithm and the P-256 curve (`prime256v1`, a.k.a. secp256r1),
     * the only curve Web Push uses.
     */
    private const OID_EC_PUBLIC_KEY = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";

    private const OID_PRIME256V1 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

    /* ------------------------------------------------------------------ *
     * Encoding helpers
     * ------------------------------------------------------------------ */

    /**
     * base64url without padding (RFC 4648 section 5) - what every push API reads.
     */
    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Decode base64url that may or may not carry padding.
     */
    public static function decode(string $encoded): string
    {
        $encoded = (string) preg_replace('/\s+/', '', $encoded);
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('That is not valid base64url: '.substr($encoded, 0, 12).'...');
        }

        return $decoded;
    }

    /* ------------------------------------------------------------------ *
     * Keys
     * ------------------------------------------------------------------ */

    /**
     * A fresh VAPID key pair, in the base64url form .env wants.
     *
     * @return array{public: string, private: string}
     */
    public static function generateKeys(): array
    {
        $details = openssl_pkey_get_details(self::newPrivateKey());

        if (! isset($details['ec']['d'], $details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('openssl did not return P-256 key material: '.self::lastError());
        }

        return [
            'public' => self::encode("\x04".$details['ec']['x'].$details['ec']['y']),
            'private' => self::encode($details['ec']['d']),
        ];
    }

    /**
     * The public key that belongs to a private scalar - what `push:keys` prints
     * next to the private key it just made.
     */
    public static function publicKeyFor(string $privateKey): string
    {
        $details = openssl_pkey_get_details(self::privateKey($privateKey));

        if (! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('That VAPID private key is not a P-256 scalar.');
        }

        return self::encode("\x04".$details['ec']['x'].$details['ec']['y']);
    }

    protected static function newPrivateKey()
    {
        $key = openssl_pkey_new([
            'ec' => [
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ],
        ]);

        if ($key === false) {
            throw new RuntimeException('openssl could not generate a P-256 key: '.self::lastError());
        }

        return $key;
    }

    /**
     * A VAPID private scalar as an openssl key handle.
     *
     * @param  string  $privateKey  base64url of the 32-byte scalar
     */
    protected static function privateKey(string $privateKey)
    {
        $key = openssl_pkey_get_private(self::pemFromScalar(self::decode($privateKey)));

        if ($key === false) {
            throw new RuntimeException('That VAPID private key could not be loaded: '.self::lastError());
        }

        return $key;
    }

    /**
     * Wrap a raw 32-byte P-256 scalar in a SEC1 `EC PRIVATE KEY` PEM, which is
     * the form openssl_pkey_get_private() accepts.
     *
     * Only needed because the key lives in .env in its compact form; the RFC 8291
     * test uses it to import the RFC's own private key.
     */
    public static function pemFromScalar(string $scalar): string
    {
        $body = self::der(0x02, "\x01")             // version
            .self::der(0x04, $scalar)               // privateKey
            .self::der(0xA0, self::OID_PRIME256V1); // parameters: namedCurve

        return "-----BEGIN EC PRIVATE KEY-----\n"
            .chunk_split(base64_encode(self::der(0x30, $body)), 64, "\n")
            ."-----END EC PRIVATE KEY-----\n";
    }

    /**
     * Wrap an uncompressed P-256 point (65 bytes, `0x04 || X || Y`) in a
     * SubjectPublicKeyInfo PEM - what openssl_pkey_get_public() accepts.
     */
    public static function pemFromPoint(string $point): string
    {
        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            throw new RuntimeException('A P-256 public key must be 65 bytes starting with 0x04.');
        }

        $algorithm = self::der(0x30, self::OID_EC_PUBLIC_KEY.self::OID_PRIME256V1);
        $subjectKey = self::der(0x03, "\x00".$point); // BIT STRING, no unused bits

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(self::der(0x30, $algorithm.$subjectKey)), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    /**
     * `p256dh` is already an uncompressed point; this is the one place that says
     * so, so encryption and its tests agree on the format.
     */
    protected static function pointFromUserKey(string $userKey): string
    {
        $point = self::decode($userKey);

        if (strlen($point) !== 65 || $point[0] !== "\x04") {
            throw new RuntimeException('The push subscription key (p256dh) is not an uncompressed P-256 point.');
        }

        return $point;
    }

    /* ------------------------------------------------------------------ *
     * RFC 8291 - message encryption
     * ------------------------------------------------------------------ */

    /**
     * Encrypt one push payload for one subscription.
     *
     * The return value is the whole request body: the aes128gcm header followed by
     * the ciphertext and its 16-byte tag. It has to be sent with
     * `Content-Encoding: aes128gcm` and the headers from vapidHeaders().
     *
     * @param  string  $payload  JSON the service worker's `push` handler reads
     * @param  string  $userKey  `p256dh` from the subscription
     * @param  string  $authSecret  `auth` from the subscription
     * @param  array  $options  `salt` (raw 16 bytes) and `ephemeral_key` (PEM): the
     *                          RFC 8291 vector pins both. Production omits them and
     *                          they are generated here.
     */
    public function encrypt(string $payload, string $userKey, string $authSecret, array $options = []): string
    {
        $userPoint = self::pointFromUserKey($userKey);
        $auth = self::decode($authSecret);

        if (strlen($auth) < 16) {
            throw new RuntimeException('The push subscription authentication secret (auth) is too short.');
        }

        $salt = isset($options['salt']) ? (string) $options['salt'] : random_bytes(16);

        if (strlen($salt) !== 16) {
            throw new RuntimeException('The aes128gcm salt must be 16 bytes.');
        }

        // A throw-away key per message: its public half rides in the header so the
        // browser can repeat the ECDH and derive the same secret.
        $ephemeral = isset($options['ephemeral_key'])
            ? openssl_pkey_get_private((string) $options['ephemeral_key'])
            : self::newPrivateKey();

        if ($ephemeral === false) {
            throw new RuntimeException('The ephemeral key could not be loaded: '.self::lastError());
        }

        $details = openssl_pkey_get_details($ephemeral);
        $asPoint = "\x04".$details['ec']['x'].$details['ec']['y'];

        $ecdhSecret = openssl_pkey_derive(openssl_pkey_get_public(self::pemFromPoint($userPoint)), $ephemeral);

        if ($ecdhSecret === false || $ecdhSecret === '') {
            throw new RuntimeException('ECDH with the subscription key failed: '.self::lastError());
        }

        // RFC 8291 section 3.3: mix the ECDH secret with the subscription's auth
        // secret, then with both public keys. The result keys the record.
        $ikm = $this->hkdf($ecdhSecret, $auth, "WebPush: info\x00".$userPoint.$asPoint, 32);

        $cek = $this->hkdf($ikm, $salt, "Content-Encoding: aes128gcm\x00", 16);
        $nonce = $this->hkdf($ikm, $salt, "Content-Encoding: nonce\x00", 12);

        // 16 (salt) + 4 (record size) + 1 (key id length) + 65 (key id)
        $header = $salt.pack('N', self::RECORD_SIZE).chr(65).$asPoint;

        // aes128gcm authenticates with an EMPTY additional-data string: the salt,
        // record size and key id already feed the CEK and nonce above, so passing
        // the header as AAD would change the tag and every browser would reject
        // the message. (The older "aesgcm" coding is the one that sets AAD.)
        // tests/Feature/WebPushTest.php pins this against RFC 8291 Appendix A.
        if (strlen($payload) + 1 > self::RECORD_SIZE - 16) {
            throw new RuntimeException('The push payload is larger than one aes128gcm record ('.(self::RECORD_SIZE - 17).' bytes).');
        }

        $tag = '';
        $ciphertext = openssl_encrypt(
            $payload.self::LAST_RECORD,
            'aes-128-gcm',
            $cek,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-128-GCM encryption failed: '.self::lastError());
        }

        return $header.$ciphertext.$tag;
    }

    /* ------------------------------------------------------------------ *
     * RFC 8292 - VAPID
     * ------------------------------------------------------------------ */

    /**
     * The header that authenticates a request to a push service.
     *
     * The JWT's audience is the endpoint's origin, so one JWT cannot be reused
     * across push services; 12 hours is the longest lifetime any push service
     * accepts.
     *
     * @return array<string, string>
     */
    public function vapidHeaders(string $endpoint, string $publicKey, string $privateKey, string $subject, int $lifetime = 43200): array
    {
        $parts = parse_url($endpoint);

        if (! isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('That push endpoint is not a URL: '.substr($endpoint, 0, 40));
        }

        $audience = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $expires = time() + max(60, min($lifetime, 43200));

        $header = self::encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::encode(json_encode([
            'aud' => $audience,
            'exp' => $expires,
            'sub' => $subject,
        ]));

        $signature = '';
        $signed = openssl_sign($header.'.'.$claims, $signature, self::privateKey($privateKey), OPENSSL_ALGO_SHA256);

        if ($signed === false) {
            throw new RuntimeException('The VAPID JWT could not be signed: '.self::lastError());
        }

        return [
            'Authorization' => 'vapid t='.$header.'.'.$claims.'.'.self::encode(self::derToRawSignature($signature)).',k='.$publicKey,
        ];
    }

    /**
     * openssl signs with ECDSA and returns a DER `SEQUENCE { INTEGER r, INTEGER s }`,
     * while a JWS ES256 signature is the bare 64-byte `r || s`.
     */
    protected static function derToRawSignature(string $der): string
    {
        $offset = 0;
        $sequence = self::readDer($der, $offset, 0x30);

        if ($sequence === null) {
            throw new RuntimeException('openssl returned a signature that is not a DER sequence.');
        }

        $inner = 0;
        $r = self::readDer($sequence, $inner, 0x02);
        $s = self::readDer($sequence, $inner, 0x02);

        if ($r === null || $s === null) {
            throw new RuntimeException('openssl returned a signature without r and s.');
        }

        return str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT)
            .str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    }

    /* ------------------------------------------------------------------ *
     * DER / HKDF plumbing
     * ------------------------------------------------------------------ */

    /**
     * The value of the next DER element when it carries `$tag`, or null.
     */
    protected static function readDer(string $data, int &$offset, int $tag): ?string
    {
        if (! isset($data[$offset]) || ord($data[$offset]) !== $tag) {
            return null;
        }

        $offset++;
        $length = ord($data[$offset] ?? "\x00");
        $offset++;

        if ($length > 0x80) {
            $bytes = $length - 0x80;
            $length = 0;

            for ($i = 0; $i < $bytes; $i++) {
                $length = ($length << 8) | ord($data[$offset] ?? "\x00");
                $offset++;
            }
        }

        $value = substr($data, $offset, $length);
        $offset += $length;

        return $value;
    }

    /**
     * DER tag-length-value.
     */
    protected static function der(int $tag, string $body): string
    {
        return chr($tag).self::derLength(strlen($body)).$body;
    }

    protected static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    /**
     * HKDF-SHA256 (RFC 5869). Web Push never asks for more than one block, so a
     * single 32-byte block covers every request.
     *
     * @param  string  $ikm  Input keying material
     * @param  string  $salt  Salt (the auth secret, then the record salt)
     * @param  string  $info  Context string
     */
    protected function hkdf(string $ikm, string $salt, string $info, int $length): string
    {
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        $output = '';
        $block = '';
        $counter = 1;

        while (strlen($output) < $length) {
            $block = hash_hmac('sha256', $block.$info.chr($counter), $prk, true);
            $output .= $block;
            $counter++;
        }

        return substr($output, 0, $length);
    }

    protected static function lastError(): string
    {
        $error = openssl_error_string();

        return $error === false ? 'no openssl error reported' : $error;
    }
}
