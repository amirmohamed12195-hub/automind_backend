# Security model and verification checklist

## Main threats and controls

| Threat | Control |
|---|---|
| Stolen credentials/tokens | Adaptive password hashing, Sanctum tokens per device, logout-all, immediate revocation on deletion, login/reset throttles |
| IDOR or mass assignment | Policies and ownership checks on user resources; Form Requests map only explicit camelCase fields; protected status/user/price fields are server-owned |
| Malicious uploads | MIME and decoded-content checks, size/count/dimension/duration bounds, random private paths, image re-encoding/EXIF removal, optional ClamAV, fixed FFmpeg arguments |
| Prompt injection | Evidence/data separation in prompts, strict JSON schemas, second PHP validation, deterministic safety escalation, no execution of model-supplied instructions |
| Unsafe automotive advice | Confidence caps, emergency rules, professional-inspection requirements, stop/tow overrides, bilingual disclaimer on every report |
| Web price misinformation | URL attribution, source freshness/compatibility/currency checks, source deduplication, honest unavailable/partial status, PHP decimal totals |
| Webhook spoof/replay | Signature over exact raw body, timestamped Standard Webhooks headers, unique webhook receipt, queued exactly-once effects |
| Secret/data leakage | Backend-only keys, log redaction, private storage, short-lived URLs, `store: false`, privacy-safe hashed provider identifier, exact CORS allowlist |
| Resource exhaustion/cost abuse | Independent rate limits, queue isolation, distributed locks, bounded attempts/output/tool calls, daily user/global budgets |

## Production checklist

- Set `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, a unique `APP_KEY`, and exact `ADMIN_CORS_ORIGINS`.
- Store secrets in the platform secret manager; never image-layer, commit, send to Flutter, or print them.
- Run `composer audit --locked`, secret scanning, formatting, PHPStan, tests, migration-from-empty, OpenAPI lint, route parity, and schema-export parity in CI.
- Use least-privilege MySQL, Redis, S3, FCM, OAuth, and OpenAI project credentials; rotate and revoke on personnel/environment changes.
- Encrypt database/object-storage backups, test restore, define backup deletion, and restrict operators through audited roles.
- Terminate TLS at a maintained proxy/load balancer, enable HSTS and secure headers, limit request body sizes, and restrict internal DB/Redis networks.
- Register Google/Apple audiences exactly; validate issuer, audience, expiry, signature, and nonce. Keep JWK caching bounded.
- Monitor authentication failures, throttle events, webhook failures/replays, AI refusal/schema failure, queue age, spending, and account deletion jobs.
- Perform a legal/privacy review for consent copy, retention, minors, location, diagnostic media, and regional OpenAI processing before launch.

The API intentionally does not return raw provider payloads, private storage paths, secrets, internal exception traces, or sensitive diagnostic details in push notification bodies.
