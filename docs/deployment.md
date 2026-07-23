# Deployment

## Release sequence

1. Build an immutable PHP-FPM image from the repository and scan dependencies/image layers.
2. Inject production secrets at runtime. Run `php artisan automind:check-provider-config`; it makes no paid provider call.
3. Run `php artisan migrate --force` from one release task. Never run the demo seeder in production; it is blocked unless an explicit unsafe override is set. Migrations are additive-first; delay destructive changes until old application versions are drained.
4. Warm configuration/routes/views, start web instances, and pass `/api/v1/health?type=readiness` before shifting traffic.
5. Start workers for all named queues and one scheduler leader. Restart workers after every deployment.
6. Run an authenticated smoke flow that does not invoke OpenAI, then an explicitly approved low-cost provider smoke if required.

## First production deployment

The host must provide PHP 8.3 with BCMath, Fileinfo, GD, Mbstring, OpenSSL,
PDO MySQL, Tokenizer, and XML extensions. Frontend assets are built before
release and committed for PHP-only shared hosts. Create the production
environment file and replace every `CHANGE_ME` value before continuing:

```bash
cp .env.production.example .env
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan key:generate --force
composer run deploy:production
```

Generate `APP_KEY` only for the first deployment. Replacing it later invalidates
encrypted data, signed URLs, cookies, and tokens. Store the generated value in
the host's secret manager and keep `.env` out of Git.

For every later deployment, run:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
composer run deploy:production
```

The production deployment script clears stale caches, validates provider
configuration without making a paid request, applies migrations, rebuilds
Laravel's caches, and restarts queue workers.

After changing frontend source files, build and commit the generated assets on
a development machine with Node.js 20.19+ or 22.12+:

```bash
composer run build:production
git add public/build
```

The Docker image performs this frontend build in its own Node.js stage; do not
run the frontend build again on a PHP-only production host.

Verify the release before directing traffic to it:

```bash
php artisan about --only=environment
curl --fail --silent --show-error 'https://api.example.com/api/v1/health?type=liveness'
curl --fail --silent --show-error 'https://api.example.com/api/v1/health?type=readiness'
```

Replace `api.example.com` with the real API domain.

## Apache and shared hosting

The preferred Apache document root is the repository's `public/` directory.
Enable `mod_rewrite`, allow `.htaccess` overrides, and make only `storage/` and
`bootstrap/cache/` writable by the PHP process. Do not use world-writable
permissions such as `chmod 777`.

`public/.htaccess` contains Laravel routing and production response headers.
The repository-root `.htaccess` is a shared-hosting fallback when the provider
cannot point the document root directly at `public/`; it blocks application
internals and forwards requests into `public/`.

The production environment template defaults to local storage, file cache, and
database-backed sessions and queues so it works without Redis or S3. If the
host provides managed Redis, set `CACHE_STORE`, `QUEUE_CONNECTION`, and
`SESSION_DRIVER` to `redis` only after replacing `REDIS_HOST` and the related
credentials with the real connection details.

For the `automind.rafeequae.com` Hostinger deployment, install the dedicated
environment template with:

```bash
read -rsp "New OpenAI API key: " AUTOMIND_NEW_OPENAI_KEY
echo
AUTOMIND_OPENAI_API_KEY="$AUTOMIND_NEW_OPENAI_KEY" php scripts/install-hostinger-env.php
unset AUTOMIND_NEW_OPENAI_KEY
php artisan config:clear
composer run deploy:production
```

The installer creates a timestamped backup, preserves the existing `APP_KEY`,
and deliberately does not preserve the old OpenAI key. The silent prompt keeps
the newly generated key out of shell history.

Apache or PHP-FPM serves the API, so do not use `php artisan serve` in
production.

## Queue workers and scheduler

Run the queue worker under Supervisor, systemd, or the hosting provider's
persistent worker feature:

```bash
php artisan queue:work redis --queue=media-processing,diagnostic-ai,price-search,notifications,maintenance-reminders --sleep=1 --tries=4 --timeout=240 --max-time=3600
```

When using the shared-hosting defaults, replace `redis` with `database`.

Configure this cron entry with the actual absolute project path:

```cron
* * * * * cd /absolute/path/to/automind_backend && php artisan schedule:run >> /dev/null 2>&1
```

## Runtime layout

- HTTPS load balancer or maintained Nginx in front of PHP-FPM.
- Managed MySQL 8.4 with strict mode, utf8mb4, point-in-time recovery, encrypted replicas/backups, and tested restores.
- Managed Redis for cache, locks, queues, and scheduler coordination; it must not be publicly reachable.
- Private S3-compatible bucket with encryption, lifecycle policy, CORS disabled unless a signed direct-upload design is introduced, and least-privilege application access.
- Horizontally scalable API instances; queue workers scaled independently by queue age. Keep the scheduler singleton through `onOneServer` locking.

The included Compose stack is a development/smoke environment. Copy `.env.example` to `.env`, fill local credentials, then run `docker compose up -d --build`, `docker compose exec app php artisan migrate --seed`, and inspect app/queue/scheduler logs. Do not use example MySQL passwords in production.

## Operations

- Probe `/health?type=liveness` for process health and `/health?type=readiness` for MySQL, Redis, storage, and queue dependencies. Health checks never call OpenAI.
- Ship structured JSON logs centrally; alert on 5xx rate, p95/p99 latency, queue delay, failed jobs, provider failure/refusal/schema rate, missing price sources, and approximate daily AI spend.
- Run `queue:work` with bounded timeout/tries and a supervisor that restarts clean exits. Use `queue:restart` during deploys.
- Run the scheduler every minute or use `schedule:work`; monitor its heartbeat. It sends maintenance reminders and enforces retention.
- Register the exact public OpenAI webhook URL and rotate its secret using an overlap window. Rotate OAuth, push, storage, database, and provider credentials with documented owners.
- Before exposing service estimates, use the audited admin endpoints to append current currency evidence at `/admin/currency-rates` and market/service-specific labor hours plus hourly-rate ranges at `/admin/labor-rate-sources`. Use canonical part codes or `default` for a whole-job labor basis; expire superseded labor records instead of silently guessing values.
- Back up before risky migrations. Verify the SQL export using `php scripts/export-mysql-schema.php --check` in the built release.

## Rollback

Keep the prior image and configuration. Stop traffic to the failed release, restart the prior image, and do not blindly roll back irreversible data migrations. Prefer forward-compatible corrective migrations. If a deployment changed prompts/models, restore the prior environment model IDs and prompt-bearing image together so AI run metadata remains reproducible.
