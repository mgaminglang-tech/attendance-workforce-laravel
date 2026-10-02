#!/usr/bin/env bash
set -euo pipefail

trap 'printf "Application update stopped at line %s. Review the error before retrying.\n" "$LINENO" >&2' ERR

APP_PATH="${APP_PATH:-/var/www/attendance-workforce}"
BRANCH="${BRANCH:-main}"

if [[ "$(id -u)" -eq 0 ]]; then
    printf 'Run as the non-root deploy user, not with sudo.\n' >&2
    exit 1
fi

if [[ "$APP_PATH" != /var/www/* || "$APP_PATH" != "$(realpath -m -- "$APP_PATH")" || -L "$APP_PATH" || ! -d "$APP_PATH/.git" ]]; then
    printf 'APP_PATH must be an existing, non-symlink Git checkout below /var/www.\n' >&2
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
    printf 'The deploy user must belong to the www-data group.\n' >&2
    exit 1
fi

cd "$APP_PATH"
if git ls-files --error-unmatch -- .env >/dev/null 2>&1; then
    printf 'The checkout tracks .env; remove it from Git before updating.\n' >&2
    exit 1
fi
if [[ ! -f .env || -L .env || -n "$(git status --porcelain --untracked-files=normal)" ]]; then
    printf 'The production .env is missing or the Git working tree is not clean. Resolve manually.\n' >&2
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

for setting in '^APP_ENV=production$' '^APP_DEBUG=false$' '^APP_URL=https://[^[:space:]]+$' '^DB_CONNECTION=mysql$' '^SESSION_SECURE_COOKIE=true$' '^APP_KEY=base64:[A-Za-z0-9+/=]+$'; do
    if ! grep -Eq "$setting" .env; then
        printf 'Production .env must have production mode, HTTPS, MySQL, secure cookies, and a valid APP_KEY.\n' >&2
        exit 1
    fi
done

if grep -Eq "^DB_PASSWORD=(<.*>|null|NULL|\"\"|'')$" .env || grep -Eq '^APP_URL=https://[^[:space:]]*\.example(/|$)' .env; then
    printf 'Replace placeholder production values in .env before updating.\n' >&2
    exit 1
fi

if [[ "$(git branch --show-current)" != "$BRANCH" ]]; then
    printf 'Expected branch %s. Refusing to change branches automatically.\n' "$BRANCH" >&2
    exit 1
fi

origin_url="$(git remote get-url origin)"
if [[ "$origin_url" != git@*:* && "$origin_url" != ssh://* ]]; then
    printf 'The origin remote must use SSH without a token in its URL.\n' >&2
    exit 1
fi

printf 'Fetching origin and verifying a fast-forward update...\n'
git fetch origin "$BRANCH"
git checkout "$BRANCH"
if ! git merge-base --is-ancestor HEAD FETCH_HEAD; then
    printf 'The local branch cannot fast-forward to origin/%s. No pull or migration was run.\n' "$BRANCH" >&2
    exit 1
fi
if [[ -n "$(git ls-tree -r --name-only FETCH_HEAD -- .env)" ]]; then
    printf 'The incoming commit tracks .env; refusing to pull it onto the server.\n' >&2
    exit 1
fi
fetched_commit="$(git rev-parse FETCH_HEAD)"
# Advance only to the commit whose tree was checked above.
git merge --ff-only "$fetched_commit"
if [[ "$(git rev-parse HEAD)" != "$fetched_commit" ]]; then
    printf 'The checkout did not reach the reviewed commit. Stop before installing or migrating.\n' >&2
    exit 1
fi

printf 'Installing locked production dependencies and building assets...\n'
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

printf 'Checking writable directories and applying migrations...\n'
umask 0002
install -d -m 2770 storage/app/private/dtr-bulk
chgrp -R www-data storage bootstrap/cache
chmod -R ug+rwX,o-rwx storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod g+s {} +
find storage bootstrap/cache -type d -exec setfacl -m d:g::rwx,d:m::rwx {} +
php artisan config:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

printf '\nApplication update complete. Run the production smoke test in DEPLOYMENT.md.\n'
