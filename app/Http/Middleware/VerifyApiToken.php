<?php

namespace App\Http\Middleware;

use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Authenticates /api/* requests with the Bearer token minted by POST /api/login.
 *
 * The token is verified cryptographically (App\Services\ApiTokenService) before
 * the member id it carries is trusted for anything. The previous implementation
 * read the token as `base64_decode($token)` and handed the first segment
 * straight to Auth::loginUsingId(), so `base64_encode('123|')` was a valid
 * credential for every protected route - the signature check below is what
 * closes that hole.
 */
class VerifyApiToken
{
    /** @var ApiTokenService */
    protected $tokens;

    public function __construct(ApiTokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return $this->unauthorized('Unauthorized - No token', 'token_missing');
        }

        $result = $this->tokens->verify($token);

        if (!$result['valid']) {
            if ($result['error'] === 'expired') {
                return $this->unauthorized('Session expired - please sign in again', 'token_expired');
            }

            return $this->unauthorized('Unauthorized - Invalid token', 'token_invalid');
        }

        $userId = $result['claims']['sub'];

        // The token carries an id, not a member: confirm it still resolves, so a
        // deleted member cannot keep using a token that has not expired yet.
        $user = DB::selectOne("SELECT tiu_member_id, status FROM tiu_member WHERE tiu_member_id = ?", [$userId]);

        if (!$user) {
            return $this->unauthorized('Unauthorized - User not found', 'user_not_found');
        }

        // Mirrors the check in Api\AuthController::login: disabling an account has
        // to end its API access, not just its next sign-in - otherwise a token
        // already in a phone keeps working for the rest of its 90-day life.
        if (isset($user->status) && (int) $user->status === 2) {
            return $this->unauthorized('Unauthorized - Account disabled', 'account_disabled');
        }

        // The verified token is the only source of identity. merge() overwrites
        // any user_id the client sent in the body or query string, so the
        // controllers that read $request->user_id (and Auth::id()) stay sound.
        $request->merge(['user_id' => $userId]);
        $request->attributes->set('user_id', $userId);
        $request->attributes->set('api_token_claims', $result['claims']);

        // Also manually login the user for Auth::user()
        Auth::loginUsingId($userId);

        return $next($request);
    }

    /**
     * `code` is additive - clients that only read `success` and `message` keep
     * working, while a client that needs to prompt for a re-login can react to
     * `token_expired` alone.
     *
     * @param  string  $message
     * @param  string  $code
     * @return \Illuminate\Http\JsonResponse
     */
    private function unauthorized($message, $code)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
        ], 401);
    }
}