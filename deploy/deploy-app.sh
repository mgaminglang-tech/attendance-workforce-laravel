#!/usr/bin/env bash
set -euo pipefail

trap 'printf "First deployment stopped at line %s. Review the error before retrying.\n" "$LINENO" >&2' ERR

APP_PATH="${APP_PATH:-/var/www/attendance-workforce}"
REPO_URL="${REPO_URL:-}"
BRANCH="${BRANCH:-main}"

if [[ "$(id -u)" -eq 0 ]]; then
    printf 'Run as the non-root deploy user, not with sudo.\n' >&2
    exit 1
fi

if [[ -z "$REPO_URL" || -z "$BRANCH" ]]; then
    printf 'Set REPO_URL to the private repository address (and optionally BRANCH).\n' >&2
    exit 1
fi

if [[ "$REPO_URL" != git@*:* && "$REPO_URL" != ssh://* ]]; then
    printf 'Use an SSH repository URL with server-side SSH access; do not pass tokens in REPO_URL.\n' >&2
    exit 1
fi

if [[ "$APP_PATH" != /var/www/* || "$APP_PATH" != "$(realpath -m -- "$APP_PATH")" || -L "$APP_PATH" ]]; then
    printf 'APP_PATH must be a direct, non-symlink path below /var/www.\n' >&2
    exit 1
fi

for command in git composer npm php chgrp chmod find setfacl; do
    if ! command -v "$command" >/dev/null 2>&1; then
        printf 'Required command is missing: %s\n' "$command" >&2
        exit 1
    fi
done

if ! git check-ref-format --branch "$BRANCH" >/dev/null 2>&1; then
    printf 'BRANCH is not a valid Git branch name.\n' >&2
    exit 1
fi

if ! id -nG | tr ' ' '\n' | grep -qx www-data; then
    printf 'The deploy user must belong to the www-data group. Reopen the login session after adding it.\n' >&2
    exit 1
fi

if [[ -e "$APP_PATH" && ! -d "$APP_PATH" ]]; then
    printf 'APP_PATH exists but is not a directory.\n' >&2
    exit 1
fi

if [[ ! -d "$APP_PATH/.git" ]]; then
    if [[ -d "$APP_PATH" && -n "$(find "$APP_PATH" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
        printf 'APP_PATH is nonempty and is not a Git checkout; refusing to overwrite it.\n' >&2
        exit 1
    fi
    if [[ -d "$APP_PATH" && ! -w "$APP_PATH" ]]; then
        printf 'The deploy user cannot write to the empty APP_PATH directory.\n' >&2
        exit 1
    fi
    if [[ ! -d "$APP_PATH" && ( ! -d "$(dirname "$APP_PATH")" || ! -w "$(dirname "$APP_PATH")" ) ]]; then
        printf 'The deploy user needs a writable parent directory to create APP_PATH.\n' >&2
        exit 1
    fi
    printf 'Cloning the private repository on branch %s...\n' "$BRANCH"
    git clone --branch "$BRANCH" --single-branch -- "$REPO_URL" "$APP_PATH"
else
    if [[ "$(git -C "$APP_PATH" remote get-url origin)" != "$REPO_URL" || "$(git -C "$APP_PATH" branch --show-current)" != "$BRANCH" || -n "$(git -C "$APP_PATH" status --porcelain --untracked-files=normal)" ]]; then
        printf 'Existing checkout has an unexpected origin, branch, or working-tree changes. Resolve manually.\n' >&2
        exit 1
    fi
fi

cd "$APP_PATH"
if [[ ! -f composer.lock || ! -f package-lock.json || ! -f .env.example ]]; then
    printf 'The checkout is missing a required lockfile or .env.example.\n' >&2
    exit 1
fi

if git ls-files --error-unmatch -- .env >/dev/null 2>&1; then
    printf 'The checkout tracks .env; remove it from Git before deploying.\n' >&2
    exit 1
fi

if [[ -L .env ]]; then
    printf 'The production .env must be a regular file, not a symbolic link.\n' >&2
    exit 1
fi

printf 'Installing locked production dependencies and building assets...\n'
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

if [[ ! -f .env ]]; then
    cp -n .env.example .env
    chmod 0600 .env
    printf '\nCreated .env from .env.example. Configure production values and create the MySQL database/user, then rerun with CONFIRM_PRODUCTION_ENV=YES. No migration was run.\n'
    exit 0
fi

if [[ "${CONFIRM_PRODUCTION_ENV:-}" != YES ]]; then
    printf 'Review .env and database access, then rerun with CONFIRM_PRODUCTION_ENV=YES. No migration was run.\n' >&2
    exit 1
fi

for setting in APP_NAME APP_ENV APP_DEBUG APP_URL APP_KEY DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD SESSION_SECURE_COOKIE; do
    if printenv "$setting" >/dev/null 2>&1; then
        printf 'Remove the external %s override; production settings must come from .env.\n' "$setting" >&2
        exit 1
    fi
    if [[ "$(grep -Ec "^${setting}=" .env || true)" != 1 ]]; then
        printf 'Production .env must contain exactly one %s assignment.\n' "$setting" >&2
        exit 1
    fi
done

for setting in 'APP_NAME=.' 'DB_HOST=.' 'DB_PORT=.' 'DB_DATABASE=.' 'DB_USERNAME=.' 'DB_PASSWORD=.'; do
    if ! grep -Eq "^${setting}" .env; then
        printf 'A required production .env setting is empty or missing: %s\n' "${setting%%=*}" >&2
        exit 1
    fi
done

if grep -Eq "^DB_PASSWORD=(<.*>|null|NULL|\"\"|'')$" .env || grep -Eq '^APP_URL=https://[^[:space:]]*\.example(/|$)' .env; then
    printf 'Replace placeholder production values in .env before deploying.\n' >&2
    exit 1
fi

for setting in '^APP_ENV=production$' '^APP_DEBUG=false$' '^APP_URL=https://[^[:space:]]+$' '^DB_CONNECTION=mysql$' '^SESSION_SECURE_COOKIE=true$'; do
    if ! grep -Eq "$setting" .env; then
        printf 'Production .env must set APP_ENV, APP_DEBUG, APP_URL, DB_CONNECTION, and SESSION_SECURE_COOKIE as documented.\n' >&2
        exit 1
    fi
done

printf 'Setting permissions for the deploy user and PHP-FPM...\n'
umask 0002
install -d -m 2770 storage/app/private/dtr-bulk
chgrp -R www-data storage bootstrap/cache
chmod -R ug+rwX,o-rwx storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} +
find storage bootstrap/cache -type d -exec setfacl -m d:g::rwx,d:m::rwx {} +
chgrp www-data .env
chmod 0640 .env

php artisan config:clear
if grep -Eq '^APP_KEY=$' .env; then
    printf 'Generating a missing application key...\n'
    php artisan key:generate --force
elif ! grep -Eq '^APP_KEY=base64:[A-Za-z0-9+/=]+$' .env; then
    printf 'APP_KEY has an unexpected format; review it without replacing an existing key.\n' >&2
    exit 1
fi

if ! grep -Eq '^APP_KEY=base64:[A-Za-z0-9+/=]+$' .env; then
    printf 'APP_KEY was not saved; stopping before migration.\n' >&2
    exit 1
fi
chmod 0640 .env

printf 'Running migrations and caching application configuration...\n'
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

printf '\nApplication deployment complete. Configure Nginx, DNS, and HTTPS, then run php artisan app:create-global-admin interactively as described in DEPLOYMENT.md.\n'
