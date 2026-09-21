<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Published (not left at the framework default) so `allowed_origins`
    | is env-driven rather than a bare '*'. The API is pure Bearer-token
    | auth with `supports_credentials` left false, so the previous wildcard
    | was not itself exploitable -- but a wildcard origin is exactly the
    | setting that becomes exploitable the moment something later turns
    | `supports_credentials` on (e.g. enabling SPA cookie auth), so it
    | should never be the thing standing in the way of noticing that.
    |
    | CORS_ALLOWED_ORIGINS is a comma-separated list, e.g.
    | "https://pos.example.com,https://admin.example.com". Left unset, this
    | falls back to '*' -- keeping local development working out of the box
    | -- so a real deployment must set it explicitly.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '*'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
