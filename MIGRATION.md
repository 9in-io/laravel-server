# Migrating from `ssofy/laravel-server` 2.x to `9in/laravel-server` 3.x

SSOfy is now [9in](https://9in.io). 3.0 is a new major version under a new package name
that serves **adapter protocol v2**.

**Upgrading is required to use 9in.** `ssofy/laravel-server` 2.x only talks to the SSOfy service (it serves the SSOfy v1 resource-server protocol, which 9in never calls). That service has shut down (ssofy.com has lapsed), and 9in does not serve the SSOfy v1 protocol, so 2.x does not work against 9in. The old versions stay published but are no longer maintained. Move to 3.x as part of moving the app to 9in.

## 1. Packages

```bash
composer remove ssofy/laravel-server ssofy/laravel-events ssofy/php-sdk
composer require 9in/laravel-server:^3.0
```

PHP 8.1+ and Laravel 10–13 are required. Namespace `SSOfy\Laravel\…` → `Nine\Laravel\Server\…`.
The service provider is auto-discovered (`Nine\Laravel\Server\ServerServiceProvider`).

## 2. Configuration

Publish the new config (`php artisan vendor:publish --tag=nine:config`) and copy your
column mapping over from `config/ssofy-server.php`, then delete the old file.

| `ssofy-server.php` | `nine-server.php` |
|---|---|
| `secret` (`SSOFY_API_SECRET`) | `secret` (`NINE_ADAPTER_SECRET`; the old names are fallbacks, but use the new secret from the 9in console) |
| `event_queue` (`SSOFY_EVENT_QUEUE`) | `otp.queue` (`NINE_EVENT_QUEUE`) |
| `otp.notification.class`, `.channels`, `.vars` | `otp.notification`, `otp.channels`, `otp.vars` |
| `otp.store`, `otp.methods` | Removed: 9in generates and checks the codes |
| `authentication.methods`, `.passwordless` | Configured in the 9in console; `user.identifiers` limits which identifiers your table answers for |
| `repository.client`, `.scope`, `.otp` | Removed: applications and scopes live in 9in |
| `repository.user` | `adapter` (a class implementing `Nine\Adapter\AdapterInterface`) |
| `user.model` | `user.model` |
| `user.filter` | Removed with the `users` endpoint |
| `user.columns` | `user.columns`, renamed to OIDC claims: `phone` → `phone_number`, `phone_verified` → `phone_number_verified`, `display_name` → `name`; `hash` removed; new `given_name`, `family_name`, `locale`, `zoneinfo`, `birthdate`, `blocked` |
| `data.scopes`, `data.clients` | Create the applications and APIs in the 9in console |

## 3. Routes

The endpoints move from `/ssofy/*` to `/nine/*` (`NINE_ADAPTER_PATH`), and from eight to three:

| SSOfy v1 | 9in v2 |
|---|---|
| `POST /ssofy/auth/password` | `POST /nine/authenticate` |
| `POST /ssofy/user` | `POST /nine/user` |
| `POST /ssofy/event` | `POST /nine/event` |
| `POST /ssofy/client`, `/scopes` | Gone (9in console) |
| `POST /ssofy/otp-options` | Gone (derived from the user's email and phone) |
| `POST /ssofy/auth/social` | Gone (9in links social accounts) |
| `POST /ssofy/auth/token` | Gone; `php artisan nine:login-link` / `ManagementClient::createLoginLink()` |
| `POST /ssofy/users` | Gone |

Set the adapter URL in the console to `https://your-app.com/nine` (or keep `/ssofy` with
`NINE_ADAPTER_PATH=ssofy`).

## 4. Signatures

The `ssofy.signature` and `ssofy.response` middleware are gone. Requests and responses use
`9in-signature: t=<unix>,v1=<HMAC-SHA256(t.METHOD.path.body)>`. Requests older than five
minutes are rejected, and responses are bound to the request id. This is handled for you
by `Nine\Adapter\AdapterHandler`.

## 5. Code you may have extended

| 2.x | 3.x |
|---|---|
| `SSOfy\Laravel\Repositories\UserRepository` (`findById`, `find`, `verifyPassword`, `findBySocialLinkOrCreate`, `createToken`…) | `Nine\Laravel\Server\EloquentUserAdapter` (`authenticate`, `findUser`, `onEvent`, `transform`) |
| `SSOfy\Laravel\Transformers\UserTransformer` | `EloquentUserAdapter::transform()` returning `Nine\Adapter\AdapterUser` |
| `SSOfy\Laravel\Notifications\OTPNotification($option, $vars, $code, $channels)` | `Nine\Laravel\Server\Notifications\OtpNotification($data, $vars, $channel)` |
| Views `vendor/ssofy/otp/*` with `$option` | Views `vendor/nine/otp/*` with `$code`, `$purpose`, `$purposeLabel`, `$expiresAt`, `$vars` |
| `ssofy:token-generate`, `ssofy:token-verify`, `ssofy:token-delete` | `nine:login-link` |
| `UserSocialLink` model and `user_social_links` table | Not used; drop the table when you no longer need it |
| `SSOfy\Laravel\Events\OTPSent`, `UserAuthenticated`, `TokenDeleted`… | `Nine\Laravel\Events\OtpRequested`, `PasswordChanged`, `SessionsRevoked`… (see 9in/laravel-events) |
