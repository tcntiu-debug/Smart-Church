<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Access Tokens
    |--------------------------------------------------------------------------
    |
    | Every /api/* request (the mobile app today, third-party front ends later)
    | authenticates with a Bearer token minted by POST /api/login - see
    | App\Services\ApiTokenService for the format and the verification rules.
    |
    | `key` signs those tokens. It must be a long random secret and must never
    | reach a client: anyone holding it can mint a token for any member id.
    | API_TOKEN_KEY is preferred over APP_KEY so that rotating APP_KEY (which
    | re-encrypts sessions and cookies) does not also sign every mobile user out.
    |
    | `ttl_days` is how long an issued token stays valid. There is no refresh
    | endpoint yet, so expiry is the moment the holder has to sign in again -
    | raise it only if support calls demand it, never to avoid one re-login.
    |
    */

    'token' => [
        'key' => env('API_TOKEN_KEY', env('APP_KEY')),

        // Recorded as the `iss` claim, so a token minted by another issuer (a
        // later Supabase/VPS identity service) can be told apart from ours.
        'issuer' => env('API_TOKEN_ISSUER', env('APP_URL', 'http://localhost')),

        'ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 90),

        // Leeway in seconds granted on the `iat` claim, for clients whose clock
        // runs slightly ahead of the server.
        'clock_skew' => (int) env('API_TOKEN_CLOCK_SKEW', 300),
    ],

];
