# IAS2 Employee Portal Security Audit Guide

This prototype uses fake data and exists only to demonstrate security controls for the IAS2 project.

## Demonstration accounts

- Employee: `employee@example.test`
- Administrator: `admin@example.test`
- Password for both: `DemoPassword!2026`

Change or remove these accounts before any real deployment.

## Start the prototype

1. Run `php artisan migrate:fresh --seed`.
2. Run `npm run build`.
3. Run `php artisan serve`.
4. Open `http://127.0.0.1:8000`.

Local HTTP is acceptable for local development. HTTPS must be configured and tested on the final deployment environment.

## Audit tests and screenshot evidence

### AUD-01: Account lockout

Enter an incorrect password five times for `employee@example.test`, then try the correct password. The next attempt must be rejected temporarily. Capture the throttling message. Do not capture a real password.

### AUD-02: Role-based access control

Sign in as the employee and manually open `/admin/employees`. The server must return a 403 response. Then sign in as the administrator and confirm that the page opens. Capture both results.

### AUD-03: Secure session termination

Sign in, log out, and use the browser Back button or reopen `/dashboard`. The application must require authentication again. Capture the login redirect. The configured session lifetime is 15 minutes.

### AUD-04: Audit logging

Sign in as the administrator, edit a dummy employee record, and open `/admin/audit-logs`. Confirm that the log identifies the administrator, action, time, subject record, and IP address. Capture the record and the matching log entry, redacting the IP address if required by the course.

## Automated verification

Run `php artisan test --filter=SecurityControlsTest`. These tests independently verify role restrictions, login throttling, logout invalidation, and audit-log creation.

## Verification result - September 17, 2026

- Application test suite: 30 tests passed, 76 assertions.
- PHP dependency audit: no known security vulnerability advisories found.
- Production JavaScript dependency audit: 0 known vulnerabilities.
- Production frontend build: passed.

These results cover automated checks and dependency advisories. The manual screenshot tests above must still be performed in the browser for the course evidence.

## Production note

For a public deployment, enable HTTPS at the web server or hosting platform, set `APP_ENV=production`, set `APP_DEBUG=false`, set `SESSION_SECURE_COOKIE=true`, replace demonstration passwords, and restrict access to authorized users.
