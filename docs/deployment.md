# Deployment

## Release sequence

1. Build an immutable PHP-FPM image from the repository and scan dependencies/image layers.
2. Inject production secrets at runtime. Run `php artisan automind:check-provider-config`; it makes no paid provider call.
3. Run `php artisan migrate --force` from one release task. Never run the demo seeder in production; it is blocked unless an explicit unsafe override is set. Migrations are additive-first; delay destructive changes until old application versions are drained.
4. Warm configuration/routes/views, start web instances, and pass `/api/v1/health?type=readiness` before shifting traffic.
5. Start workers for all named queues and one scheduler leader. Restart workers after every deployment.
6. Run an authenticated smoke flow that does not invoke OpenAI, then an explicitly approved low-cost provider smoke if required.

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
