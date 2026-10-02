# Ubuntu VPS deployment kit

This kit targets Ubuntu 24.04 LTS, Nginx, PHP 8.4 with PHP-FPM, MySQL 8+, Composer, Node.js 24 LTS with npm, Git, Certbot, and this Laravel 13 application. Run it first on a disposable demo VPS. Nothing here deploys automatically from this repository. Keep production secrets and backups outside Git.

## A. Prerequisites

- A fresh Ubuntu 24.04 VPS with root/sudo access and an infrastructure owner.
- A domain or subdomain you control, with access to DNS records.
- SSH access to the private Git repository from a non-root deploy account. Use a scoped deploy key or an SSH agent; never put a private key or token in the repository or URL.
- A decision about who owns MySQL, its backups, and restore testing. This guide assumes MySQL on the same VPS, bound to localhost.
- SMTP host, port, credentials, and verified sender details if the application must send email. Mail acceptance still needs live testing.
- A snapshot or backup and maintenance window before the first migration or any future schema-changing update.

Use an existing non-root sudo account as the deploy account, or arrange one before starting. It must own the application checkout and belong to `www-data` after Nginx is installed. PHP-FPM runs as `www-data`. Do not run Composer or npm as root.

## B. First server setup

The repository does not exist yet on a fresh VPS. First install Git from Ubuntu's repository as the non-root sudo account. Provision that account with SSH access to this one private repository, preferably a read-only GitHub deploy key. Verify the GitHub SSH host fingerprint against [GitHub's published guidance](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/testing-your-ssh-connection) before accepting it. Keep the private key in that account's protected SSH directory, never in the repository or `.env`.

```bash
sudo apt-get update
sudo apt-get install -y git openssh-client
ssh -T git@github.com
git clone --branch main --single-branch git@github.com:OWNER/PRIVATE-REPOSITORY.git "$HOME/workforce-kit"
sudo bash "$HOME/workforce-kit/deploy/setup-server.sh"
```

Replace `OWNER/PRIVATE-REPOSITORY.git` before cloning. This first checkout is a private **bootstrap copy** in the deploy account's home; the application will be cloned separately under `/var/www`. `ssh -T` may return status 1 even when GitHub reports successful authentication. [GitHub deploy keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/managing-deploy-keys) are read-only by default; leave write access disabled.

The setup script checks Ubuntu 24.04 and amd64/arm64, updates apt indexes, enables Ubuntu's `universe` component, adds the `ondrej/php` PHP 8.4 PPA and signed NodeSource Node.js 24 repository, installs the stack and required PHP extensions, starts Nginx/PHP-FPM/MySQL, and verifies the PHP-FPM socket, Node major version, MySQL server version, and local MySQL binding. PHP 8.4 requires the PPA on Ubuntu 24.04; review and trust this third-party package source before running the script. It does not create accounts, open firewall ports, or change MySQL network binding. Stop if any package, service, or version check fails.

Grant the same non-root account used for the bootstrap checkout access to `www-data`, then prepare its empty application directory. Log out and back in so group membership is refreshed. Keep SSH and firewall policy under the infrastructure owner's control.

```bash
sudo usermod -aG www-data "$(id -un)"
sudo install -d -o "$(id -un)" -g www-data -m 2775 /var/www/attendance-workforce
```

The initial Git access must still work after the new login session. No GitHub token belongs in `REPO_URL`.

## C. Clone and deploy the application

Run as the same deploy account after logging back in. Replace the repository address with the actual **SSH** address. The default branch is `main`; `APP_PATH` defaults to `/var/www/attendance-workforce`.

```bash
export APP_PATH=/var/www/attendance-workforce
export REPO_URL=git@github.com:OWNER/PRIVATE-REPOSITORY.git
export BRANCH=main
bash "$HOME/workforce-kit/deploy/deploy-app.sh"
```

The script runs from the bootstrap copy and clones the application into the empty `/var/www` directory. It accepts an empty target directory or checks an existing clean checkout with the expected branch and origin. It installs locked Composer and npm dependencies and builds assets. On first run it copies `.env.example` to `.env` if missing, then stops before a key or migration. The copied file is local only and must never be committed.

## D. Configure production `.env`

Edit `APP_PATH/.env` on the VPS with a private editor session. Do not paste secrets into shell history, tickets, Git, or logs. Set at least:

```dotenv
APP_NAME="Workforce Attendance"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_KEY=
LOG_LEVEL=warning
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=attendance_workforce
DB_USERNAME=attendance_app
DB_PASSWORD=<private-value-entered-on-server>
SESSION_SECURE_COOKIE=true
```

Edit the existing active assignments instead of appending duplicates. Replace every placeholder and keep `APP_KEY` blank only for the first resumed deployment; the script generates it once. Preserve an existing key across updates and restorations or encrypted data/sessions may become unreadable. Do not run the deployment script against sample DB values. The script rejects duplicate critical keys, shell environment overrides, sample URL/password values, and unsafe production settings, then requires `CONFIRM_PRODUCTION_ENV=YES`. The operator remains responsible for verifying the actual database, domain, and secret values. Because sessions and cache use the database, MySQL must be available before migrations and runtime use.

`MAIL_MAILER=log` is suitable only while email is intentionally inactive. Configure SMTP under section J before accepting email workflows.

## E. Create MySQL database and least-privileged app user

Use the local MySQL administrative account interactively on the VPS. Replace the sample database/user names and enter a unique private password. Do not save the real SQL with its password in Git. Keep MySQL listening on localhost; do not expose port 3306 publicly.

```bash
sudo env MYSQL_HISTFILE=/dev/null mysql
```

```sql
CREATE DATABASE attendance_workforce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'attendance_app'@'127.0.0.1' IDENTIFIED BY '<private-value-entered-on-server>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
    ON attendance_workforce.* TO 'attendance_app'@'127.0.0.1';
```

The grants are scoped to this schema and cover this application's migrations and runtime writes. Do not grant global privileges. Set `.env` to the matching host, database, user, and password. Verify connectivity as the app user without printing a password or pasting it into a command argument. The interactive client above avoids saving the entered SQL to a MySQL history file. The infrastructure owner must take a database backup before migrations and test restoration on a separate instance.

## F. Run Laravel migrations

After section D and E are complete, run the first deployment script again **as the deploy account**:

```bash
CONFIRM_PRODUCTION_ENV=YES APP_PATH=/var/www/attendance-workforce \
REPO_URL=git@github.com:OWNER/PRIVATE-REPOSITORY.git BRANCH=main \
bash "$HOME/workforce-kit/deploy/deploy-app.sh"
```

The script checks production settings, gives PHP-FPM access to `.env`, makes `storage`, `bootstrap/cache`, and `storage/app/private/dtr-bulk` writable, clears any stale configuration cache, generates `APP_KEY` only if absent, runs `php artisan migrate --force`, then caches configuration, routes, and views. It does not seed an admin. Check `php artisan migrate:status --no-interaction` and the Laravel log afterward. Treat any migration failure as a stop condition and investigate before continuing.

## G. Configure Nginx

Replace `DOMAIN` and `APP_PATH` in the application's `deploy/nginx.conf.example` with the actual domain and absolute application path, then install the resulting server block under `/etc/nginx/sites-available/`. Enable it with a symlink in `sites-enabled` and disable the default site if it conflicts. Review the rendered file before enabling it. Its root must be `APP_PATH/public`, never the repository root. It routes requests only through `index.php` via the PHP 8.4 FPM socket, denies other PHP files, hidden files, and private storage paths, and sets basic content-type/referrer headers.

```bash
sudo nginx -t
sudo systemctl reload nginx
```

The template starts with HTTP so Certbot can perform domain validation. Do not use the site for production traffic until HTTPS is enabled. The private DTR directory is under `storage/app/private`, outside the web root; never symlink it into `public`.

## H. Configure domain DNS

Create the needed A/AAAA records with your DNS provider, pointing to the VPS public addresses. Verify they resolve to this VPS and that the infrastructure owner permits inbound ports 80 and 443 while keeping SSH access controlled. DNS changes are manual.

## I. Configure HTTPS with Certbot

Only after DNS resolves correctly and Nginx validates, explicitly authorize issuance for the domain and run on the VPS:

```bash
sudo certbot --nginx -d your-domain.example
sudo nginx -t
sudo certbot renew --dry-run
```

Follow Certbot prompts, verify an HTTPS request and redirect behavior, and keep `APP_URL=https://...` with `SESSION_SECURE_COOKIE=true`. Check the installed renewal timer and monitor certificate renewal. Neither deployment script edits DNS or runs Certbot. Recheck Nginx configuration after Certbot changes it.

## J. Configure mail

If mail is required, enter provider-supplied `MAIL_MAILER=smtp`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` in the server `.env`. Match the scheme and port to the provider's documented TLS mode. Keep credentials private. Run `php artisan config:cache` after changing `.env`, then perform a controlled live delivery and receipt check to a designated address. Until that succeeds, treat invitation and notification email as unverified.

## K. Create initial Global Admin

Run this only after production migrations have completed successfully. From the application checkout, as the non-root deploy account, use an interactive terminal (for SSH, allocate a terminal with `ssh -t`):

```bash
cd /var/www/attendance-workforce
php artisan app:create-global-admin
```

Use the actual application directory if it differs from this example. Enter a full name and a company-controlled admin email, followed by a password and password confirmation. Both password prompts are hidden; choose a unique password with at least 12 characters, uppercase and lowercase letters, a number, and a symbol. There are no default credentials or password command-line arguments. Do not pipe answers, use `--no-interaction`, or record a terminal session containing credentials. If hidden input is unavailable, the command stops without creating an account.

The account uses the existing Global Admin role (`admin`) and is active immediately, without invitation activation. Global Admins have no Employee profile or employee number. Email is trimmed and lowercased, and an existing email is rejected. If any Global Admin exists, including a disabled one, the command warns and requires explicit confirmation before creating another account; declining makes no changes. The account is created in a transaction and serialized with a database cache lock, so run migrations first. A validation or creation failure returns a nonzero exit status. For an unexpected failure, check whether the account exists before retrying. Never run development seeders in production.

## L. Production smoke test

On the disposable demo VPS first, confirm `nginx -t`, PHP-FPM and MySQL service status, HTTPS certificate and redirect, login page availability, no debug output, expected auth boundaries, asset loading, writable session/cache/log directories, and no public access to `.env` or private DTR paths. After the approved admin bootstrap, exercise one authorized attendance/DTR flow, including a private bulk ZIP if relevant. Verify SMTP delivery only if mail is configured. Inspect Laravel, Nginx, PHP-FPM, and MySQL logs; do not call a code-only check a live acceptance test.

## M. Future updates using `update-app.sh`

Back up MySQL and confirm a restore path before any migration. Review the incoming commit and schema changes, then run as the deploy account:

```bash
APP_PATH=/var/www/attendance-workforce BRANCH=main \
bash /var/www/attendance-workforce/deploy/update-app.sh
```

The script requires a clean working tree and valid production `.env`, fetches origin, verifies the current commit is an ancestor of the fetched branch, rejects an incoming tracked `.env`, and fast-forwards to that exact fetched commit with `git merge --ff-only`. This is the fast-forward portion of a pull without a second fetch that could advance the remote after `.env` was checked. It then installs locked dependencies, rebuilds assets, clears stale configuration, applies migrations, and refreshes Laravel caches. If fast-forward is impossible it stops before dependency changes or migrations. It never resets, cleans untracked files, or overwrites `.env`. Run section L smoke checks after each update.

## N. Rollback guidance

Keep a tested database backup/snapshot and the exact previous application commit before updates. These scripts update files in place; a failure after the Git fast-forward or dependency installation can leave a partially updated live tree. Use a maintenance window and an operator-controlled recovery procedure. If an update fails before migrations, diagnose and redeploy a reviewed prior release. Once a migration ran, **do not assume** old code is compatible with the new schema or automatically reverse migrations. Coordinate code and database restoration from a matching backup in a maintenance window, and test the recovery on a disposable copy first. The scripts do not automate rollback, `migrate:fresh`, or database wiping.

## O. Logs and ownership

- Laravel: `APP_PATH/storage/logs/laravel.log` when the single log channel is in use.
- Nginx: typically `/var/log/nginx/access.log` and `/var/log/nginx/error.log` on Ubuntu.
- PHP-FPM: `sudo journalctl -u php8.4-fpm` and the configured PHP-FPM logs.
- MySQL: `sudo journalctl -u mysql` and the configured MySQL error log.

Application requirements are writable `storage` and `bootstrap/cache`, available MySQL, working SMTP for email workflows, HTTPS, and readable operational logs. The **infrastructure owner** is responsible for MySQL backups, restore testing, disk/CPU/RAM/uptime monitoring, firewall policy, OS security updates, and SSL renewal monitoring. This kit does not install enterprise monitoring or a backup service.
