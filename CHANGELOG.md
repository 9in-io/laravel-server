# CHANGELOG

## 4.0.0 - 2026-11-10

Breaking: requires `9in/php-sdk` ^4.0 and `9in/laravel-events` ^3.0 for 9in's snake_case adapter
events (the 9in release of 2026-11-10). Includes the 3.1.1 fix. See [MIGRATION.md](MIGRATION.md).

* `onEvent` recognises `otp.requested` by `event` (was `type`); `OtpNotification` reads `expires_at`.
* `nine:login-link` prints the ISO 8601 `expires_at` the API now returns (it formatted Unix seconds).

## 3.1.1 - 2026-11-09

* Fix: OTP notifications failed with "class not found" on case-sensitive filesystems (Linux servers).
  The `OtpNotification` class was still in a file named `OTPNotification.php`, which PSR-4
  autoloading cannot find there. Affected 3.0.0, 3.0.1 and 3.1.0.
* Tests run on GitHub Actions: Laravel 10 to 13 on each supported PHP version, plus the lowest
  dependency versions.

## 3.1.0 - 2026-11-09

* Laravel 13 support (`laravel/framework` `^10.0 || ^11.0 || ^12.0 || ^13.0`; Orchestra Testbench 11 for
  development). No code changes were needed: the full test suite passes on Laravel 12 and 13 without
  deprecations, and the live end-to-end suite against 9in passes on Laravel 13.
* Test that runs the published users-table migration against Laravel's default `users` table.

## 3.0.1 - 2026-11-09

Same code as 3.0.0, released again under a new version because the 3.0.0 tag was moved to a
rewritten commit after Packagist had published it (published versions cannot change).

## 3.0.0 - 2026-11-09

Rebranded as **9in** (`9in/laravel-server`, namespace `Nine\Laravel\Server`) and moved to adapter protocol v2. See [MIGRATION.md](MIGRATION.md).

* Three endpoints instead of eight: `POST /nine/authenticate`, `/nine/user`, `/nine/event`, served by `Nine\Adapter\AdapterHandler` from 9in/php-sdk 3.
* Timestamped `9in-signature` on requests and responses, previous-secret rotation, 5-minute replay window.
* `EloquentUserAdapter` with OIDC claim mapping, case-insensitive email/username lookup, blocked users, password rehashing.
* `otp.requested` delivered with `OtpNotification` (mail / Vonage / custom channel) or by your own listener.
* Adapter events dispatched as `Nine\Laravel\Events\*` (9in/laravel-events 2).
* `nine:login-link` command (Management API) replaces the token commands.
* Removed: client/scope/OTP repositories, social links, filters, request validators, the v1 middleware and commands.
* PHP 8.1+, Laravel 10–12. Feature tests with Orchestra Testbench.

## 2.1.5 - 2024-08-17

* Fixed the Social Auth issue.

## 2.1.4 - 2024-06-17

* Fixed the PaginatedResponseEntity import.

## 2.1.3 - 2024-06-16

* Fixed the bug with UserRepository::find().

## 2.1.2 - 2024-06-16

* Alignments with latest php-sdk `UserRepositoryInterface::find()` signature updates.
* Implemented `UserRepository::findAll()`.
* Removed user caching in UserRepository.
* Enhanced validation rules by taking advantages of php-sdk model validation.
* Added `/users` endpoint.

## 2.1.1 - 2024-01-30

* Fixed the message in blade view.

## 2.1.0 - 2024-01-29

* Added config for otp options' enabled methods.
* Updated Blade views. OTP Action included.
* Config file revamp with compatibility.

## 2.0.6 - 2023-10-01

* Renamed `settings` to `vars` in default otp templates while preserving the old functionality.

## 2.0.5 - 2023-09-16

* Fixed OTP verification issue with non-exising otp option or user.
* Fixed wrong column mapping in config file.
* Config property rename `otp.notification.settings` to `otp.notification.vars` for compatibility with the new draft.

## 2.0.4 - 2023-08-15

* Validations fix.

## 2.0.3 - 2023-07-22

* Hotfix: OTPRepository fixed.

## 2.0.2 - 2023-07-22

* Fixed the SMS OTP error.
* OTPRepository refinements.
* Adapted repositories with the contracts provided in php-sdk.

## 2.0.1 - 2023-07-01

* Hotfix: fixed the otp options endpoint validation error.
* Hotfix: fixed the otp request issue.

## 2.0.0 - 2023-06-18

* Upgraded to `ssofy/php-sk:2.0.0`.
* Removed SSO Client functionality.
* Renamed the config file to `sso-server.php`.
* The `OTP` class is renamed to `UserTokenManager` to avoid confusions.
* Repositories manipulate database directly instead of relying on User Provider.
* Dispatching Events for various operations done.
* Bug Fixes.

## 1.0.4 - 2023-06-02

* Fixed the issue with event payload validation.
* Fixed the issue with `user_add` and `user_update`.
* Fixed the issue with empty `name` attribute.

## 1.0.3 - 2023-06-02

* Improved user search functionality using login, email, and phone.

## 1.0.2 - 2023-06-01

* Fixed the issue with `email_verified` and `phone_verified` claims.

## 1.0.1 - 2023-06-01

* Handled `user_updated` (profile updates) and `user_added` (registration).
* Added config options for authentication methods.
* Fixed the issue with passwordless authentication.

## 1.0.0 - 2023-03-24

* First Release.
