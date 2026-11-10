9in Laravel Server
==================

<img src="docs/banner.png" width="160" alt="9in"/>

Keep your users in your Laravel database and sign them in with [9in](https://9in.io).

This package serves the three endpoints of 9in's **adapter protocol v2** from your app:

| Endpoint | What 9in asks |
|---|---|
| `POST /nine/authenticate` | Is this password right for this email / username / phone? |
| `POST /nine/user` | Who is this user (by id or identifier)? |
| `POST /nine/event` | Notifications, including one-time codes for you to deliver |

Every request is signed with your adapter secret and must be less than five minutes old.
Every response is signed over the request path plus 9in's request id, so a response cannot
be replayed. Applications, scopes, social login, sessions and two-factor authentication are
configured in 9in, not here.

Requires PHP 8.1+ and Laravel 10, 11, 12 or 13 (Laravel 13 itself needs PHP 8.3+).

```bash
composer require 9in/laravel-server:^4.0
```

Upgrading from `ssofy/laravel-server` 2.x? Read [MIGRATION.md](MIGRATION.md).

## Setup

1. In the 9in console, open **Connections → User database**, choose **Adapter**, set the URL
   to `https://your-app.com/nine` and copy the secret.
2. Add it to `.env`:

   ```dotenv
   NINE_ADAPTER_SECRET=…
   ```

3. Optionally publish the config, views and the users-table migration (adds `username`,
   `phone`, `phone_verified_at`, `metadata`, `avatar_url` when missing):

   ```bash
   php artisan vendor:publish --tag=nine:config
   php artisan vendor:publish --tag=nine:views
   php artisan vendor:publish --tag=nine:migrations
   ```

## Mapping your users table

`config/nine-server.php` maps each claim 9in stores to a column of `user.model`
(`App\Models\User` by default). Set a claim to `null` when you have no column for it:

```php
'columns' => [
    'id'                    => 'id',
    'email'                 => 'email',
    'email_verified'        => 'email_verified_at',  // boolean or timestamp column
    'phone_number'          => 'phone',
    'phone_number_verified' => 'phone_verified_at',
    'username'              => 'username',
    'name'                  => 'name',
    'picture'               => 'avatar_url',
    'blocked'               => null,                 // e.g. 'banned_at'
    'password'              => 'password',           // Laravel Hash (bcrypt/argon), rehashed when needed
    'metadata'              => 'metadata',           // array/JSON; becomes the token's `app` claim
    // given_name, family_name, locale, zoneinfo, birthdate
],
'identifiers' => ['email', 'username', 'phone'],   // what users may sign in with
```

Emails and usernames are matched case-insensitively. For anything else (scopes, soft
deletes, a different hasher, another data source), extend
`Nine\Laravel\Server\EloquentUserAdapter` or point `nine-server.adapter` at your own class
implementing `Nine\Adapter\AdapterInterface`.

## One-time codes

9in generates sign-in and password-reset codes for adapter users and sends them to
`/nine/event` as `otp.requested`. The package delivers them with
`Nine\Laravel\Server\Notifications\OtpNotification` on the channel configured for email
(`mail`) or SMS (`vonage`; install `laravel/vonage-notification-channel`, or set
`NINE_OTP_SMS_CHANNEL` to your own channel, which gets `toSms()`). Templates are
`resources/views/vendor/nine/otp/{email,sms}.blade.php` after publishing.

To deliver codes yourself, set `nine-server.otp.send` to `false` and listen to
`Nine\Laravel\Events\OtpRequested`.

## Events

Every adapter event is dispatched as a Laravel event from
[`9in/laravel-events`](https://github.com/9in-io/laravel-events): `OtpRequested`,
`UserUpdated` (with the changed `fields`), `PasswordChanged`, `SessionsRevoked`, …, plus
`EventReceived` for all of them.

```php
Event::listen(\Nine\Laravel\Events\PasswordChanged::class, function ($event) {
    // $event->userId() is your user's id (the payload's data.user_id)
});
```

Adapter events are `{id, event, created_at, data}` with snake_case data; `otp.requested`
carries `user_id`, `channel`, `to`, `code`, `purpose` and `expires_at` (ISO 8601 UTC).

## Sign-in links

`php artisan nine:login-link jane@acme.com --return-to=https://app.acme.com/callback`
prints a single-use sign-in URL (the replacement for SSOfy's `auth/token`). It needs a
confidential 9in application with management scopes in `NINE_ISSUER`,
`NINE_CLIENT_ID` / `NINE_CLIENT_SECRET` (or `NINE_MANAGEMENT_CLIENT_ID` /
`NINE_MANAGEMENT_CLIENT_SECRET`). Adapter users exist in 9in after their first sign-in.

## Environment

| Variable | Fallback | Default |
|---|---|---|
| `NINE_ADAPTER_SECRET` | `SSOFY_ADAPTER_SECRET`, `SSOFY_API_SECRET` | (required) |
| `NINE_ADAPTER_SECRET_PREVIOUS` | | Accepted during a secret rotation |
| `NINE_ADAPTER_PATH` | | `nine` |
| `NINE_ADAPTER_TOLERANCE` | | `300` seconds |
| `NINE_OTP_EMAIL_CHANNEL` | `SSOFY_OTP_EMAIL_CHANNEL` | `mail` |
| `NINE_OTP_SMS_CHANNEL` | `SSOFY_OTP_SMS_CHANNEL` | `vonage` |
| `NINE_EVENT_QUEUE` | `SSOFY_EVENT_QUEUE` | default queue |
| `NINE_ISSUER`, `NINE_CLIENT_ID`, `NINE_CLIENT_SECRET` | `SSOFY_URL`, `SSOFY_CLIENT_ID`, `SSOFY_CLIENT_SECRET` | for `nine:login-link` |

Sign users in to your app with [`9in/laravel`](https://github.com/9in-io/laravel). Both
packages can be installed side by side.

## Tests

```bash
composer install
composer test
```

## License

The MIT License (MIT). See [LICENSE](LICENSE). 9in and SSOfy are by [Cubelet Ltd](https://cubelet.co.uk).
