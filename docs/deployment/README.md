# Deployment

YAP supports VPS hosting and rental/shared hosting with PHP CLI cron. Nodes run the Go Agent separately; the web host needs PHP and MySQL, not a Go runtime or SSH connectivity to nodes. See [node provisioning](../agents/node-operations.md), [Agent configuration](../../agent/README.md), and [core builds/upgrades](../agents/v2fly-upgrade.md).

## Requirements and configuration

- PHP 8.3+ for web requests and CLI, with Composer-required extensions, including `bcmath`, MySQL PDO, and the extensions checked by `composer check-platform-reqs --no-dev`.
- MySQL or MariaDB; writable `storage` and `bootstrap/cache` directories. Keep strict mode enabled.
- Build host: Composer and Node.js 22.12+ or a newer supported LTS. Upload the built `vendor` and `public/build` artifacts when the rental host has neither Composer nor Node.
- Outbound HTTPS for configured payment/OAuth/AI services. Agents require a publicly reachable HTTPS panel URL and verify its certificate.
- Working CLI cron and a queue worker. Agent synchronization does not depend on the queue; deferred account/AI/notification jobs still do.
- Optional local backups require executable `mysqldump`, `gzip` and PHP subprocess support. Web and CLI PHP may have different extensions and restrictions: verify both.

Use `.env.example` as the template. Set a unique application key, database credentials, canonical URL and provider credentials privately:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.example.com
APP_TIMEZONE=Asia/Tokyo
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yap
DB_USERNAME=yap
DB_PASSWORD=replace-privately
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
NODE_AGENT_ENABLED=true
NODE_SNAPSHOT_STORE=file
NODE_POLL_INTERVAL_SECONDS=5
NODE_TRAFFIC_INTERVAL_SECONDS=60
NODE_TRAFFIC_RETENTION_DAYS=7
SUBSCRIPTION_CONTENT_STORE=file
SUBSCRIPTION_LOCK_STORE=database
```

`APP_TIMEZONE` also controls business schedules and MySQL-family session timezones. The application converts it to the current numeric offset when each connection opens, and refreshes that offset on queries when it changes. Shared MySQL does not need named timezone tables. There is no separate `DB_TIMEZONE` setting. Historical SQL date grouping across daylight-saving changes uses the session's current offset; an application requiring historical DST conversion needs additional date-range handling. Tokyo has no daylight-saving transition.

Configure GitHub OAuth and registered payment/Sponsors webhooks in the corresponding provider dashboards using the actual canonical domain. Set trusted proxy configuration for the real reverse proxy/CDN, and keep application secrets, backups and cron scripts outside the public document root.

## VPS

Point the web server at the application's `public` directory. Build/install with the deployed PHP version:

```sh
cp .env.example .env
# Edit .env privately before continuing.
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
npm ci
npm run build
php artisan key:generate --no-interaction
php artisan migrate --force --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
```

For an existing installation, preserve its key and configure the existing database; do not generate another key. Never use `migrate:fresh`, reset a database or overwrite unrelated tables. Give the application user ownership of writable directories rather than granting global write access.

Invoke the scheduler once per minute under the application user, substituting absolute paths:

```cron
* * * * * cd /srv/yap && /usr/bin/php artisan schedule:run >> /srv/yap/storage/logs/cron.log 2>&1
```

Use Supervisor or systemd for a persistent database queue worker. Example Supervisor program:

```ini
[program:yap-queue]
directory=/srv/yap
command=/usr/bin/php artisan queue:work database --queue=default --tries=3 --backoff=60 --timeout=120 --no-interaction
user=yap
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=360
redirect_stderr=true
stdout_logfile=/srv/yap/storage/logs/queue.log
```

Worker timeout must be shorter than `DB_QUEUE_RETRY_AFTER` (default 360 seconds). PHP CLI needs the worker's signal/timeout support for enforcing that deadline. Restart persistent workers after deploying code, for example with `php artisan queue:restart`. Rotate file logs.

## Rental server without SSH or persistent workers

Build the application on a compatible machine and upload code, production `vendor`, and `public/build` over the provider's supported transfer method. Configure a private production `.env` and point the domain's upload/public directory at `yap/public`. Web PHP and CLI PHP must both satisfy requirements.

Use provider CLI cron for the one-time migration/cache commands if no SSH console is available. Keep this bootstrap script outside `public`, enable it only for the initial operation, inspect its exit status and remove its scheduled entry afterward. Do not expose an unauthenticated web migration or scheduler route.

Run `schedule:run` every minute and a bounded database queue worker every minute. The queue can wait for the next invocation; backlog or slow upstream services can add delay. `jobs` and `failed_jobs` remain necessary. No batch-job storage is configured; adding `Bus::batch` or batch-based admin actions requires the corresponding storage.

### Five delayed cron entries (Lolipop example)

For an account whose control panel offers five entries at five-minute intervals, [the example bundle](rental-cron/cron.sh) contains five launchers delayed by 0, 60, 120, 180 and 240 seconds. Together they invoke one dispatcher approximately every minute. Confirm the account permits sleeping jobs for that duration; this is not a guarantee of exact wall-clock execution or a provider-independent hosting limit.

1. Copy `rental-cron/` to a private directory outside `public`, such as the account's FTP root. Create `config.sh` from `config.example.sh`, set absolute `PHP_BIN`/`APP_DIR`, and set `YAP_ENABLED=1` after validating the application.
2. Set `config.sh` mode 0600, and all executable `.sh` files mode 0755. FTP uploads may lose execute bits; set them again after every replacement.
3. In the provider panel, schedule `cron-0.sh`, `cron-1.sh`, `cron-2.sh`, `cron-3.sh` and `cron-4.sh` at the same five-minute boundary. If installed as `yap-cron/`, the five panel paths are `yap-cron/cron-0.sh` through `yap-cron/cron-4.sh`.
4. Check natural executions in cron mail/logs and Filament Scheduler Health, across several five-minute boundaries. Disable any old scheduler before enabling another one against the same database.

`cron.sh` contains one line per external task. The runner uses `flock` and one overwritten minute-bucket file per task to prevent duplicate attempts. For the scheduler, it releases the lock after claiming the minute, allowing the next minute's tick to run even while a long task is executing. Laravel's task-specific overlap locks protect business tasks. Queue workers and other external tasks retain their lock throughout execution. The runner records attempts before executing; a failed attempt is reported to stderr and retried at the next eligible bucket. State is bounded rather than a growing execution log. Keep state private and retain it when replacing scripts. The host must provide `flock`, `sleep`, `date`, and POSIX `sh`.

The queue example uses `--stop-when-empty --max-time=40 --max-jobs=100 --tries=3 --backoff=60 --timeout=30`. Adjust the timeout for actual jobs and host execution limits, always below database `retry_after`. `--max-time` is checked between jobs and cannot interrupt a long job. With no CLI signal support, the worker timeout cannot enforce a job deadline. A provider that kills delayed/long jobs can prevent every-minute operation even with these launchers.

For five-minute or hourly custom tasks, pass 300 or 3600 to `run_job`; those tasks run only in the undelayed slot so the sleep does not consume their runtime budget. Existing unrelated site cron tasks can be added as separate scripts/calls. The application's business schedules remain in `routes/console.php`; do not duplicate them in the shell. Application local backups are already scheduled there.

## Subscription caching and traffic

Subscription requests build content only on a cache miss, then store the result in the configured content store. File content storage and database locks avoid a permanent worker dependency and prevent competing requests from rebuilding the same content. Authorization and node changes invalidate the relevant cache. An optional `app/ClashYamlCustomizer.php` runs during generation, not each cache hit; use the array-to-array example and avoid network calls in that function.

Authorization changes store a durable notification in the application database queue inside the same transaction as the account change. After commit, the application refreshes node revisions immediately and removes the notification; interrupted or failed refreshes are retried by the database worker. Keep that worker on the application database and its configured queue table; leave `DB_QUEUE_CONNECTION` unset or set it to the application connection name. A separate queue connection is rejected when Agent authorization is enabled. This avoids holding node locks inside financial transactions.

Agents poll configuration and report traffic independently. Defaults are five-second polls, ten-second local samples and sixty-second uploads. Unchanged snapshots use caching/version checks; uploads use immutable batches and deduplication. `traffic_records` holds short-lived raw accounting receipts; `user_stats` holds hourly display aggregates. Keep the pruning and aggregation schedules enabled. Filament exposes raw receipts; the customer statistics UI uses `user_stats`.

### Updating Agent intervals

After changing `NODE_POLL_INTERVAL_SECONDS` or `NODE_TRAFFIC_INTERVAL_SECONDS` in the panel's runtime `.env`, rebuild Laravel's configuration cache and clear the snapshot cache. Existing snapshots retain the old intervals until they are invalidated or expire. Run these commands from the deployed application directory with its configured PHP CLI:

```sh
php artisan config:cache --no-interaction
php artisan cache:clear file --no-interaction
```

`file` is the default `NODE_SNAPSHOT_STORE`. If another snapshot store is configured, replace `file` with the effective `node_agent.snapshot_store` value after rebuilding configuration. A bare `cache:clear` clears the default application store, which may differ from the snapshot store. The command flushes all cached entries in the named store.

Reload persistent application processes so they use the new configuration; for a persistent Laravel queue worker, run `php artisan queue:restart --no-interaction` after clearing the cache. Rental hosting can execute the same commands through a private one-time CLI cron script. Bounded workers load configuration on their next start.

The next successful Agent poll rebuilds the missing snapshot and advances its revision. Verify that enabled nodes apply the new revision and their saved snapshots contain both requested interval values. The update arrives on the previous polling cadence; each new interval takes effect when its next loop timer is created. Inspect only the required revision and interval fields, since full snapshots contain user credentials. Keep Agent SQLite state intact.

## Optional local MySQL backups

Backups default to enabled and are configured through `.env`:

```dotenv
BACKUP_ENABLED=true
# An absolute directory outside public; omission uses storage/app/private/backups.
BACKUP_PATH=/absolute/private/path/to/backups
BACKUP_INTERVAL_HOURS=1
BACKUP_RETENTION_HOURS=168
BACKUP_MYSQLDUMP_BINARY=mysqldump
BACKUP_GZIP_BINARY=gzip
BACKUP_TIMEOUT_SECONDS=1800
```

The scheduler calls the backup command, which deduplicates interval buckets, creates compressed dumps and applies retention only to its managed files. Credentials are passed through a private temporary defaults file. Check `php artisan app:backup-mysql --help` and test a dump before relying on it. If the account cannot execute the required tools, set `BACKUP_ENABLED=false` and arrange an alternative backup method.

Filament Backup Status shows file count, sizes, latest backup, predicted next attempt and recorded success/failure, without a public download endpoint or a growing database history. Gzip integrity is checked after a successful dump; this does not prove that a full restore will succeed. Keep application key/private configuration recoverable separately, and periodically test restoration into an isolated database without clearing an existing database. These are local backups; they do not protect against loss of the hosting account.

## Operational checks

Filament Scheduler Health shows actual scheduler start/completion and Laravel-calculated next task dates. The next minute tick is an estimate; delayed delivery, overlap locks and host failures can change actual execution. Scheduler health does not establish queue health: inspect `jobs`, `failed_jobs` and worker errors separately.

Read private Laravel logs, configure Sentry when desired, and use the provider's access/error logs where available. Cron stderr can be sent through provider cron mail. Keep `APP_DEBUG=false` in production and do functional panel acceptance in an isolated sandbox.
