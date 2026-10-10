# IAS2 Group 8 Employee Portal

A basic Employee Portal developed for Information Assurance and Security 2. The application demonstrates security controls identified in the Group 8 IAS1 risk assessment.

## Features

- Employee and administrator authentication
- Account lockout after repeated failed login attempts
- Role-based access control for administrator pages
- Audited 15-minute idle timeout and logout invalidation
- Employee record management using dummy data
- Audit logging for login and employee-record activities
- Automated security-control tests

## Technology

- Laravel 13
- Vue.js 3 with Inertia.js
- Tailwind CSS
- SQLite for local development

## Local setup

Requirements: PHP 8.3 or later, Composer, Node.js, and npm.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000`.

## Demonstration accounts

| Role | Email | Password |
|---|---|---|
| Employee | `employee@example.test` | `DemoPassword!2026` |
| Administrator | `admin@example.test` | `DemoPassword!2026` |

These accounts contain dummy data and are intended only for local demonstration.

## Testing

Run the complete application test suite:

```bash
php artisan test
```

Run only the security-control tests:

```bash
php artisan test --filter=SecurityControlsTest
```

Detailed manual test procedures and evidence requirements are documented in [SECURITY-AUDIT-GUIDE.md](SECURITY-AUDIT-GUIDE.md).

## Deployment notes

Before deploying publicly:

- Replace the demonstration credentials.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure a production database.
- Enable HTTPS and set `SESSION_SECURE_COOKIE=true`.
- Protect the application with appropriate hosting and database access controls.
