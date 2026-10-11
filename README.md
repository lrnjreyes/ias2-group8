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

### Windows launcher

Download or clone the complete project, extract it, and double-click `run-local.bat`.
The first run needs an internet connection. The launcher downloads PHP, Composer,
and Node.js into the project's `dependencies` folder, installs the application
packages, creates the local SQLite database and demonstration accounts, and builds
the frontend. No PHP, Composer, or Node.js PATH setup is needed.

Later runs reuse these tools and keep the existing database and accounts. The
launcher window stays open while the server runs and pauses if startup fails.
Open the login address printed in that window. Press Ctrl+C to stop the server.
The default port is 8000; if it is busy, the launcher chooses the next available
port. You can also use `run-local.bat 8080` to request a different starting port.

This launcher is for 64-bit Windows. If PHP reports that the Microsoft Visual C++
runtime is missing, install the [official x64 runtime](https://aka.ms/vs/17/release/vc_redist.x64.exe)
and run the launcher again. Keep `run-local.bat` and the `scripts` folder in the
project when sharing it. Downloaded tools in `dependencies` are generated locally
and are excluded from Git.

### macOS, Linux, and Codespaces

Install PHP 8.3 or later with SQLite support and Node.js 24 with npm, available
in your terminal. For macOS, [Laravel Herd](https://herd.laravel.com/) provides
PHP; [Node.js](https://nodejs.org/en/download) provides Node and npm.
Codespaces runs Linux, regardless of the computer used to access it.

In the project folder, run:

```bash
bash run-local.sh
```

The launcher downloads Composer into `dependencies`, creates the required storage
folders, installs the application packages, prepares the database and demonstration
accounts, and builds the frontend. It keeps existing accounts and the application
key on later runs. First setup needs an internet connection. PHP and Node use the
versions installed on your machine; the Windows runtime downloads are not used.

On macOS, open the address printed in the terminal. In Codespaces, open port 8000
from the **Ports** tab. Keep the terminal open; press Ctrl+C to stop.
Use `bash run-local.sh 8080` to request a different starting port, or
`bash run-local.sh --setup-only` to prepare the app without starting the server.

### Fixing "Please provide a valid cache path"

This error means Laravel's compiled-view directory is missing or its cached
configuration points to an old path. In the Mac/Linux/Codespaces terminal, stop
the server with Ctrl+C, then run:

```bash
mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
php artisan config:clear
php artisan view:clear
php artisan serve --host=0.0.0.0 --port=8000
```

The last command is for Codespaces. For a local Mac, use `php artisan serve`.
The storage placeholder files in this project keep these directories present
in fresh clones. Include them when sharing the project.

### Manual setup

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
