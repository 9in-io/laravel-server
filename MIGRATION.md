# Migrating

## 4.x → 5.x

The old SSOfy environment variables are no longer read. If you still set any, rename them:
`SSOFY_ADAPTER_SECRET` / `SSOFY_API_SECRET` → `NINE_ADAPTER_SECRET`, `SSOFY_OTP_EMAIL_CHANNEL` /
`SSOFY_OTP_SMS_CHANNEL` → `NINE_OTP_EMAIL_CHANNEL` / `NINE_OTP_SMS_CHANNEL`, `SSOFY_EVENT_QUEUE` →
`NINE_EVENT_QUEUE`, and `SSOFY_URL`, `SSOFY_CLIENT_ID`, `SSOFY_CLIENT_SECRET` → `NINE_ISSUER`,
`NINE_CLIENT_ID`, `NINE_CLIENT_SECRET`. 5.0 requires `9in/php-sdk` ^5.0. Nothing else changes.

## 3.x → 4.x

4.0 moves to `9in/php-sdk` 4 and `9in/laravel-events` 3, which read 9in's snake_case adapter
events (the 9in release of 2026-11-10). Upgrade together with 9in.

```bash
composer require 9in/laravel-server:^4.0 -W
```

- Adapter events are `{id, event_type, created_at, data}` (were `type`, `createdAt`), and
  `otp.requested` data is `user_id` / `expires_at` (were `userId` / `expiresAt`). The
  `OtpRequested` accessors and the `$expiresAt` view variable of the OTP views keep their
  names. A custom `nine-server.otp.notification` class that reads `$this->data['expiresAt']`
  must read `$this->data['expires_at']`.
- `php artisan nine:login-link` prints the expiry as 9in returns it (ISO 8601 UTC).
- Users the adapter returns were already snake_case: nothing changes there.
