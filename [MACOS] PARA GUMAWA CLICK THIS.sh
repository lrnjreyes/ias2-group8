#!/usr/bin/env bash
set -Eeuo pipefail
trap 'printf "Local startup failed. Check the message above.\n" >&2' ERR

cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
setup_only=0
if [[ "${1:-}" == "--setup-only" ]]; then
    setup_only=1
    shift
fi
port="${1:-8000}"
if [[ ! "$port" =~ ^[0-9]{1,5}$ ]] || (( 10#$port < 1 || 10#$port > 65535 )); then
    printf 'Use a port number between 1 and 65535.\n' >&2
    exit 1
fi
port=$((10#$port))

for tool in php node npm; do
    if ! command -v "$tool" >/dev/null 2>&1; then
        printf 'Missing %s. Install PHP 8.3+ and Node.js 24 with npm, then try again. See README.md.\n' "$tool" >&2
        exit 1
    fi
done
php_exe="$(command -v php)"
"$php_exe" -r 'if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "PHP 8.3 or later is required.\n"); exit(1); } foreach (["pdo_sqlite", "mbstring", "openssl", "fileinfo"] as $ext) { if (!extension_loaded($ext)) { fwrite(STDERR, "Missing PHP extension: $ext\n"); exit(1); } }'
for file in artisan .env.example composer.lock package-lock.json; do
    if [[ ! -f "$file" ]]; then
        printf 'Missing %s. Download or clone the complete project.\n' "$file" >&2
        exit 1
    fi
done

mkdir -p storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache \
    dependencies/composer dependencies/cache
export COMPOSER_CACHE_DIR="$PWD/dependencies/cache/composer"
export npm_config_cache="$PWD/dependencies/cache/npm"
composer_path="$PWD/dependencies/composer/composer.phar"
composer_hash='7a2d379d5b8ffdaa028580ef26494c36d2feef4b178d3dd1473a4dbc5e17c8d6'
if [[ ! -f "$composer_path" ]] || ! "$php_exe" -r 'exit(hash_file("sha256", $argv[1]) === $argv[2] ? 0 : 1);' "$composer_path" "$composer_hash"; then
    printf 'Downloading Composer for this project...\n'
    curl --fail --location --retry 2 --output "$composer_path.download" \
        'https://getcomposer.org/download/2.10.3/composer.phar'
    if ! "$php_exe" -r 'exit(hash_file("sha256", $argv[1]) === $argv[2] ? 0 : 1);' "$composer_path.download" "$composer_hash"; then
        printf 'The Composer download failed its checksum verification.\n' >&2
        exit 1
    fi
    mv -- "$composer_path.download" "$composer_path"
fi
[[ -f .env ]] || cp .env.example .env
"$php_exe" "$composer_path" install --no-interaction --prefer-dist --no-progress
"$php_exe" "$composer_path" check-platform-reqs --no-interaction
"$php_exe" artisan config:clear --no-ansi
if ! grep -Eq '^APP_KEY=[^[:space:]]+' .env; then
    "$php_exe" artisan key:generate --force --no-ansi
fi
[[ -f database/database.sqlite ]] || touch database/database.sqlite
"$php_exe" artisan migrate --force --no-ansi
if "$php_exe" -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(App\Models\User::exists() ? 0 : 2);'; then
    :
else
    user_status=$?
    if [[ "$user_status" -eq 2 ]]; then
        "$php_exe" artisan db:seed --force --no-ansi
    else
        printf 'Could not check the local accounts.\n' >&2
        exit "$user_status"
    fi
fi
npm ci --no-audit --no-fund
npm run build
if [[ "$setup_only" -eq 1 ]]; then
    printf 'Local setup completed.\n'
    exit 0
fi

local_host=127.0.0.1
if [[ "${CODESPACES:-false}" == "true" ]]; then
    local_host=0.0.0.0
    printf 'Open the server port in the Codespaces Ports tab.\n'
fi
printf 'Keep this terminal open. Press Ctrl+C to stop the server.\n'
exec "$php_exe" artisan serve --host="$local_host" --port="$port" --no-reload
