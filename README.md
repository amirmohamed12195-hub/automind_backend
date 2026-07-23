# AutoMind AI Backend

Production-oriented Laravel 13 REST API for the AutoMind Flutter application. It provides token authentication, vehicle and maintenance management, multimodal diagnostic orchestration, strict bilingual AI reports, sourced service estimates, mechanics and appointments, notifications, admin operations, and signed OpenAI webhooks.

## Architecture

- PHP 8.3+ and Laravel 13, MySQL 8.4, Redis, Sanctum mobile tokens, private local/S3 storage.
- API responses use camelCase fields, `data`/`meta` success envelopes, stable localized error envelopes, ULIDs, UTC ISO-8601 timestamps, cursor pagination, and `X-Request-Id` correlation.
- Named queues: `media-processing`, `diagnostic-ai`, `price-search`, `notifications`, and `maintenance-reminders`.
- OpenAI API adapters are behind contracts. Diagnostic synthesis, image understanding, engine audio, spoken transcription, and web price search are distinct stages.
- Reports are strict-schema validated twice, passed through deterministic safety rules, and persisted as a normalized bilingual graph.

See [architecture](docs/architecture.md), [OpenAI integration](docs/openai-integration.md), [notifications](docs/notifications.md), [Flutter integration](docs/flutter-integration.md), [security](docs/security.md), and [deployment](docs/deployment.md).

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
docker compose up -d --build
docker compose exec app php artisan migrate --seed
php artisan test
php artisan queue:work redis --queue=media-processing,diagnostic-ai,price-search,notifications,maintenance-reminders
php artisan schedule:work
```

The API is served at `http://localhost:8080/api/v1`. Non-production Swagger UI is at `http://localhost:8080/docs/api`. For a host-only SQLite test run, create `database/database.sqlite`, set `DB_CONNECTION=sqlite`, then run migrations and tests.

## Configuration

`.env.example` contains every supported variable. Production requires:

- MySQL and Redis credentials, `APP_KEY`, HTTPS `APP_URL`, and non-debug production mode.
- `OPENAI_API_KEY`, capability-verified model IDs, an optional webhook secret for signed provider events, and a versioned `OPENAI_PRICING_MODELS_JSON` snapshot from official pricing. Keep provider background mode disabled; Laravel queues orchestrate the workflow.
- S3-compatible private bucket credentials, Google and Apple OAuth client IDs, FCM credentials, geocoding credentials, mail transport, and exact admin CORS origins as applicable.

Pricing JSON keys are model IDs and rates are USD per million tokens: `input`, `cachedInput`, `output`, plus optional per-call `webSearchCall`. Run `php artisan automind:check-provider-config` in deployment; it validates capabilities, endpoint, webhook, and pricing without spending API credit.

## AI and safety behavior

Uploads require explicit consent and remain private. Images are MIME/content/dimension checked, re-encoded to strip EXIF, optionally scanned by ClamAV, and bounded to six photos. Audio is bounded to one engine recording plus one spoken description and 30 seconds, then normalized with fixed FFmpeg arguments. Engine sound is analyzed as acoustic evidence; it is never treated as speech.

Analysis freezes an input manifest, uses a distributed session lock and persisted checkpoints, respects cancellation and `Retry-After`, records usage/cost metadata, enforces daily user/global budgets, and degrades honestly if price research fails. AI text and web content are always treated as untrusted evidence. Critical deterministic rules can escalate severity and force stop/tow/professional-inspection guidance.

## Localization and storage

Send `Accept-Language: ar` or `en`. API errors, report text, actions, causes, notifications, and seeded catalog labels support both languages. The locale changes presentation only; normalized codes remain stable. Raw media uses the configured private disk and short-lived signed URLs. Retention defaults are documented in [retention](docs/retention.md).

## Quality and generated artifacts

```bash
composer check
php scripts/audit-api-contract.php
php scripts/audit-postman-contract.php
php scripts/export-mysql-schema.php --check
npx --yes @redocly/cli@2.18.1 lint docs/openapi.yaml
```

The OpenAPI 3.1 source is `docs/openapi.yaml`, Postman artifacts are under `docs/postman/`, and the generated MySQL 8 snapshot is `database/schema/mysql-schema.sql`. It includes Laravel's migration ledger, so framework schema loading is safe as well as human-readable. `composer check` runs formatting, static analysis, tests, route/OpenAPI parity, and schema-export parity.

## Deployment

Use HTTPS, immutable images, external MySQL/Redis/S3, at least one worker for every named queue, one scheduler leader, encrypted backups, centralized JSON logs, health monitoring, and secret rotation. Run `php artisan migrate --force` before shifting traffic and `php artisan automind:check-provider-config` before enabling workers. See [deployment.md](docs/deployment.md) for rollback and zero-downtime details.

For a first deployment, start from `.env.production.example`. On Apache, point
the document root to `public/` when the host allows it; the root `.htaccess`
supports shared hosts that cannot change the document root. After configuring
production secrets, use `composer run deploy:production` for the repeatable
release steps.
