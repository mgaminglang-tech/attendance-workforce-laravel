#!/usr/bin/env bash
set -euo pipefail

trap 'printf "Server setup stopped at line %s. Review the error before retrying.\n" "$LINENO" >&2' ERR

if [[ "$(id -u)" -ne 0 ]]; then
    printf 'Run this script as root with sudo on the target VPS.\n' >&2
    exit 1
fi

if [[ ! -r /etc/os-release ]]; then
    printf 'Cannot identify the operating system.\n' >&2
    exit 1
fi

# shellcheck disable=SC1091
source /etc/os-release
if [[ "${ID:-}" != ubuntu || "${VERSION_ID:-}" != 24.04 ]]; then
    printf 'This kit supports Ubuntu 24.04 LTS only.\n' >&2
    exit 1
fi

architecture="$(dpkg --print-architecture)"
if [[ "$architecture" != amd64 && "$architecture" != arm64 ]]; then
    printf 'NodeSource packages in this kit require amd64 or arm64.\n' >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

printf 'Updating the Ubuntu package index and installing prerequisites...\n'
apt-get update
apt-get install -y ca-certificates curl gnupg software-properties-common acl
add-apt-repository -y universe

printf 'Enabling the PHP 8.4 package source for Ubuntu 24.04...\n'
add-apt-repository -y ppa:ondrej/php

printf 'Configuring the signed NodeSource Node.js 24 LTS package source...\n'
install -d -m 0755 /usr/share/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key |
    gpg --dearmor --yes -o /usr/share/keyrings/nodesource.gpg
chmod 0644 /usr/share/keyrings/nodesource.gpg
cat > /etc/apt/sources.list.d/nodesource.sources <<EOF
Types: deb
URIs: https://deb.nodesource.com/node_24.x
Suites: nodistro
Components: main
Architectures: $architecture
Signed-By: /usr/share/keyrings/nodesource.gpg
EOF

apt-get update
apt-get install -y \
    nginx mysql-server git unzip composer nodejs certbot python3-certbot-nginx \
    php8.4-cli php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd

update-alternatives --set php /usr/bin/php8.4
if [[ "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" != 8.4 ]]; then
    printf 'The default PHP CLI is not PHP 8.4. Resolve PHP alternatives before deploying.\n' >&2
    exit 1
fi

php -r 'foreach (["ctype", "curl", "dom", "fileinfo", "filter", "hash", "mbstring", "openssl", "pcre", "PDO", "pdo_mysql", "session", "tokenizer", "xml", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: $extension\n"); exit(1); } }'
php-fpm8.4 -t

for service in nginx php8.4-fpm mysql; do
    systemctl enable --now "$service"
    systemctl is-active --quiet "$service"
done

if [[ ! -S /run/php/php8.4-fpm.sock ]]; then
    printf 'The expected PHP 8.4 FPM socket is missing. Check the FPM pool configuration.\n' >&2
    exit 1
fi
if [[ "$(stat -c '%G' /run/php/php8.4-fpm.sock)" != www-data ]]; then
    printf 'The PHP 8.4 FPM socket is not accessible through the www-data group.\n' >&2
    exit 1
fi

if [[ "$(node -p 'process.versions.node.split(".")[0]')" != 24 ]]; then
    printf 'Node.js 24 LTS was not installed from the configured package source.\n' >&2
    exit 1
fi

mysql_server_version="$(mysql -Nse 'SELECT VERSION()')"
if [[ ! "$mysql_server_version" =~ ^([8-9]|[1-9][0-9])\. || "$mysql_server_version" == *MariaDB* ]]; then
    printf 'MySQL 8 or newer is required.\n' >&2
    exit 1
fi

mysql_bind_address="$(mysql -Nse 'SELECT @@global.bind_address')"
if [[ "$mysql_bind_address" != 127.0.0.1 && "$mysql_bind_address" != ::1 && "$mysql_bind_address" != localhost ]]; then
    printf 'MySQL is not bound to a loopback address. Restrict it before continuing.\n' >&2
    exit 1
fi

printf '\nInstalled versions:\n'
nginx -v
php -v | head -n 1
php-fpm8.4 -v | head -n 1
mysql --version
composer --version
node --version
npm --version
git --version
certbot --version

printf '\nServer setup complete. Next: add the non-root deploy account to www-data, create the local MySQL database and app user, then follow DEPLOYMENT.md. Configure DNS, Nginx, and HTTPS explicitly after the app is ready.\n'
