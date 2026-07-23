# Implementation and contract audit

## Client contract inspected

The Flutter client defines authentication, vehicle, diagnostic session, diagnostic result, OBD, history, and analysis flows. Its required report model is preserved in the API. The inspected repositories still use local data sources, so real API integration is additive and explicitly mapped in `flutter-integration.md`; no Flutter UI was rewritten.

## Implemented domains

1. API conventions, bilingual envelopes/errors, request IDs, health/readiness, metrics, auth and policy foundation.
2. Users, social identities, tokens/devices/settings, vehicle catalog and owned vehicles.
3. Diagnostic sessions, consent, symptom/OBD evidence, private media processing, idempotency, state machine, cancellation and retry.
4. Versioned OpenAI provider adapters, strict structured output, bilingual report graph, deterministic safety, usage/cost tracking and budgets.
5. Source-backed price research, deterministic decimal ranges, maintenance and reminders.
6. Mechanics, availability, conflict-safe appointments, reviews, notifications, webhooks, and admin maintenance.
7. MySQL schema export, factories/seeders, tests/fakes, Docker/CI, OpenAPI/Postman, Flutter mapping, security/deployment/retention documentation.

`php scripts/audit-api-contract.php` compares normalized Laravel route methods/paths against OpenAPI and fails on either undocumented code or unimplemented documentation. `php scripts/export-mysql-schema.php --check` regenerates migration DDL in memory and fails when the checked-in MySQL export is stale.
