<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Adapter secret
    |--------------------------------------------------------------------------
    |
    | From the 9in console: Connections → User database → Adapter. 9in signs its
    | requests with it, and your responses are signed with it too. During a rotation,
    | put the new secret in NINE_ADAPTER_SECRET and the old one in
    | NINE_ADAPTER_SECRET_PREVIOUS.
    |
    | SSOFY_ADAPTER_SECRET and SSOFY_API_SECRET are still read as fallbacks, but the
    | value itself is a new one from 9in.
    |
    */

    'secret' => env('NINE_ADAPTER_SECRET', env('SSOFY_ADAPTER_SECRET', env('SSOFY_API_SECRET'))),

    'previous_secret' => env('NINE_ADAPTER_SECRET_PREVIOUS'),

    // Maximum age of a signed request, in seconds.
    'tolerance' => (int) env('NINE_ADAPTER_TOLERANCE', 300),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | POST /{prefix}/authenticate, /{prefix}/user and /{prefix}/event. Set the adapter
    | URL in the console to https://your-app.com/{prefix}.
    |
    */

    'route' => [
        'enabled'    => true,
        'prefix'     => env('NINE_ADAPTER_PATH', 'nine'),
        'middleware' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Adapter
    |--------------------------------------------------------------------------
    |
    | Class implementing Nine\Adapter\AdapterInterface. The default reads the Eloquent
    | model below; extend it or bind your own.
    |
    */

    'adapter' => Nine\Laravel\Server\EloquentUserAdapter::class,

    'user' => [
        'model' => \App\Models\User::class,

        /**
         * Column for each user claim; null when there is none. Verified flags and
         * `blocked` accept a boolean or a timestamp column (set = true).
         * "[CLAIM]" => "[COLUMN]"
         */
        'columns' => [
            'id'                    => 'id',
            'email'                 => 'email',
            'email_verified'        => 'email_verified_at',
            'phone_number'          => 'phone',
            'phone_number_verified' => 'phone_verified_at',
            'username'              => 'username',
            'name'                  => 'name',
            'given_name'            => null,
            'family_name'           => null,
            'picture'               => 'avatar_url',
            'locale'                => null,
            'zoneinfo'              => null,
            'birthdate'             => null,
            'blocked'               => null,
            'password'              => 'password',
            'metadata'              => 'metadata',
        ],

        // Identifier types users may sign in with (email, username, phone).
        'identifiers' => ['email', 'username', 'phone'],
    ],

    /*
    |--------------------------------------------------------------------------
    | One-time codes
    |--------------------------------------------------------------------------
    |
    | 9in generates codes for your users and sends them to you in an `otp.requested`
    | event. With `send` on, they are delivered with the notification class below.
    | Turn it off to deliver them yourself from a Nine\Laravel\Events\OtpRequested
    | listener.
    |
    */

    'otp' => [
        'send'         => true,
        'notification' => Nine\Laravel\Server\Notifications\OtpNotification::class,
        'channels'     => [
            'email' => env('NINE_OTP_EMAIL_CHANNEL', env('SSOFY_OTP_EMAIL_CHANNEL', 'mail')),
            'sms'   => env('NINE_OTP_SMS_CHANNEL', env('SSOFY_OTP_SMS_CHANNEL', 'vonage')),
        ],
        'queue'        => env('NINE_EVENT_QUEUE', env('SSOFY_EVENT_QUEUE')),
        'vars'         => [
            'brand' => env('APP_NAME'),
        ],
    ],

    // Dispatch Nine\Laravel\Events\* for every adapter event.
    'events' => [
        'dispatch' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Management API (optional)
    |--------------------------------------------------------------------------
    |
    | A confidential 9in application with management scopes, for
    | `php artisan nine:login-link` (replaces SSOfy's auth/token).
    |
    */

    'management' => [
        'issuer'        => env('NINE_ISSUER', env('SSOFY_URL')),
        'client_id'     => env('NINE_MANAGEMENT_CLIENT_ID', env('NINE_CLIENT_ID', env('SSOFY_CLIENT_ID'))),
        'client_secret' => env('NINE_MANAGEMENT_CLIENT_SECRET', env('NINE_CLIENT_SECRET', env('SSOFY_CLIENT_SECRET'))),
    ],
];
