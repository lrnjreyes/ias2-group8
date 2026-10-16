# MFA + Account Lockout: verified results

**Lockout: working as email/IP login throttling. MFA: NOT IMPLEMENTED.**

Audited on 16 October 2026, Asia/Manila. Source commit: `b111432a82eea9590edacd16baa176c29e44df1f`.
Target: `http://127.0.0.1:8000`; demonstration account: `employee@example.test`.
Runtime: PHP 8.5.11; Laravel 13.32.0; database cache store.

Open [report.html](report.html) for the complete visual evidence. All images in
`screenshots/01` through `screenshots/07` are actual browser screenshots of the
unmodified application. `08` and later images are explicitly labeled report or
source-listing screenshots, not extra application screens.

## Expected versus actual

| Check | Expected | Actual | Result |
|---|---|---|---|
| LOCK-01 | Reject all five and remain signed out. | Five credential errors; five auth.login.failed events (IDs 14-18). | PASS |
| LOCK-02 | Five failures for the same email/IP activate throttling. | Counter = 5; is_limited = true. Fifth response still shows the credential error. | PASS |
| LOCK-03 | Reject submission six, even with the correct password. | Too many login attempts. Please try again in 39 seconds. | PASS |
| LOCK-04 | Allow the correct password after the window expires; clear the counter. | Dashboard reached after natural expiry. Counter = 0; auth.login.succeeded recorded. | PASS |
| MFA-01 | If MFA were implemented, require a separate factor before granting dashboard access. | Email and password alone granted dashboard access. No MFA enrollment, challenge, secret, or recovery-code implementation found. | NOT IMPLEMENTED / FAIL against an MFA requirement |
| AUDIT-01 | Record five failures, a blocked attempt, and the successful recovery. | Exactly 5 failed + 1 locked + 1 succeeded, IDs 14-20. | PASS |

The fifth incorrect password returns the normal credentials error while bringing
the counter to five. The guard runs before authentication on each request, so
the lockout message is displayed on submission six. The captured sixth submission
used the known-correct demonstration password and was denied.

The default decay is **60 seconds from the first counted failure**, not 60 seconds
after the fifth failure. The screenshot displayed the remaining wait, rather than
a fresh full minute. No counter was cleared between the fifth failure and the
successful recovery; the browser recovery used natural expiration.

This is a temporary rate limit keyed by lowercased email plus IP address. It is
not a persistent account-disabled flag and does not establish a global lockout
across different IP addresses. Multi-IP bypass testing was outside this run.

## Exact code locations

- `app/Http/Requests/Auth/LoginRequest.php:42-56`: check the limit, try credentials,
  increment the failure counter, log failure, and clear the counter on success.
- `app/Http/Requests/Auth/LoginRequest.php:64-82`: threshold of five, lockout event,
  audit record, remaining time, and the throttle validation message.
- `app/Http/Requests/Auth/LoginRequest.php:87-90`: email/IP throttle key.
- `vendor/laravel/framework/src/Illuminate/Cache/RateLimiter.php:148`: `hit()` has
  a default decay of 60 seconds. This is dependency code, not an application edit.
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php:31-41`: password
  authentication is followed directly by session creation and dashboard redirect.
- `routes/auth.php`: password login, reset, email-verification, and logout routes;
  no MFA enrollment or challenge route.
- `app/Models/User.php`: no second-factor trait or stored MFA-secret configuration.

See [the code picture](code-locations.html), [numbered source](raw/code-locations-numbered.txt),
and [exact source snapshots](raw/source/). Line references describe the captured build.

## MFA confirmation

**There is no working MFA in this audited build. Do not claim MFA is implemented.**
The successful browser login reached the protected dashboard with only email and
password. Source, route, dependency, and model inspection found no OTP/TOTP,
authenticator enrollment, WebAuthn/passkey, recovery-code, or second-factor flow.
Email verification, password reset, and password confirmation routes do not
establish a second authentication factor in the login flow.

The negative finding is supported by both [source inspection](raw/mfa-source-search.txt)
and the [successful password-only login](screenshots/07-after-expiry-password-only-dashboard.jpg).
The search alone is not treated as proof of absence.

## Browser captures

- Submission 1: [incorrect_password](screenshots/01-failed-attempt-1.jpg)
- Submission 2: [incorrect_password](screenshots/02-failed-attempt-2.jpg)
- Submission 3: [incorrect_password](screenshots/03-failed-attempt-3.jpg)
- Submission 4: [incorrect_password](screenshots/04-failed-attempt-4.jpg)
- Submission 5: [incorrect_password](screenshots/05-failed-attempt-5.jpg)
- Submission 6: [correct_password_during_lockout](screenshots/06-correct-password-blocked-by-lockout.jpg)
- Submission 7: [correct_password_after_natural_expiry](screenshots/07-after-expiry-password-only-dashboard.jpg)

The server audit baseline was ID 13. IDs 14-18 are the five failed requests,
ID 19 is the lockout, and ID 20 is the successful recovery. Preliminary UI timing
checks happened before that baseline and are excluded. The raw timestamps and
audit entries let a reviewer correlate the screenshots with actual requests.
Passwords and session tokens are not included in the report or screenshots.

## Automated verification

- Existing project lockout test: **1 test, 3 assertions, passed**.
- Additional evidence checks: **3 tests, 41 assertions, passed**.
- Combined: **4 tests, 44 assertions**.

The additional password-only test passes because it characterizes the current
absence of MFA; that pass must not be reported as passing an MFA requirement.
MFA-01 above is explicitly not implemented / failed against that requirement.

Raw PHPUnit output and JUnit XML are in `raw/`. Tests use SQLite in memory and
the array cache/session drivers from `phpunit.xml`; they do not reset the local
application database. The browser checks use the existing demonstration account
and persist normal security audit events. Only that demonstration email/IP
counter was cleared once before the final sequence. No application UI or
authentication implementation was changed.

Re-run the evidence tests from the project root:

```powershell
.\dependencies\php\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml output/mfa-account-lockout-2026-10-16/raw/MfaLockoutEvidenceTest.php --testdox
.\dependencies\php\php.exe vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --filter=test_login_is_rate_limited_after_five_failures --testdox
```

With system PHP installed on Mac/Linux, replace `.\dependencies\php\php.exe` with
`php`. Browser screenshots verify this local Windows-hosted build; this evidence
does not claim a native macOS execution or a production deployment verification.