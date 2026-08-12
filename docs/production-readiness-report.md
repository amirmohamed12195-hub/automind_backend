# AutoMind production-readiness report

> Historical audit. The 2026-08-11 implementation adds public legal/support/deletion/reset/report pages, association endpoints, explicit legal/diagnostic consent, Firebase setup, and billing fail-closed validation. The current launch handoff is in `autoMind/docs/LAUNCH_HANDOFF_AR_2026-08-11.md`.

Date: 2026-07-24

## Executive status

The Laravel API and Flutter client are a production candidate at source-code
level. The API contract, database schema, reference data, authentication
lifecycle, social-login token exchange, deployment preflight, and release
signing safeguards are implemented and covered by automated checks.

Production launch is **not yet unblocked** because provider credentials, mobile
store identities, and deployment access are external to the repositories. The
currently deployed API is healthy, but its public make and symptom catalogs are
still empty; the new reference seeder must be deployed and run.

## Completed in this readiness pass

- Set the Flutter default API to
  `https://automind.rafeequae.com/api/v1`, while retaining a build-time
  `API_BASE_URL` override.
- Added an idempotent production reference catalog containing 42 vehicle makes,
  285 common models, 9 diagnostic symptoms, 15 maintenance services, and 10
  mechanic specialties. Production deploys now run this seeder automatically.
- Kept fictional demo users, vehicles, and workshops out of production.
- Added native Google and Apple sign-in to Flutter and connected both to the
  backend token-exchange endpoints.
- Hardened social identity verification: provider signature, issuer, audience,
  expiry, subject, verified email, and Apple SHA-256 nonce validation.
- Made social account creation/linking transactional and idempotent, preserved
  Apple's first-authorization name, and handled subsequent Apple tokens that do
  not repeat the email.
- Added the HTTPS Apple Android callback with fixed package targeting,
  allow-listed fields, throttling, no-store, and no-referrer headers.
- Added production preflight checks for OAuth audiences, durable queues, SMTP,
  Firebase service credentials, HTTPS, database configuration, admin
  credentials, and debug mode.
- Fixed protected-data loading before authentication, expired-session handling,
  user-data clearing on logout, stale asynchronous responses, notification
  pagination, vehicle dependency injection, admin HTTP method mismatches, and
  system/version response mapping.
- Changed diagnosis analysis to display server-reported progress and made report
  routes fetch the canonical remote report instead of requiring in-memory
  history.
- Added Android release-keystore configuration that fails closed when missing.
  Split iOS development and production push entitlements and added Sign in with
  Apple capability.
- Documented the native provider and deployment configuration.
- Added a public localized maintenance-service catalog and completed vehicle
  edit/delete/health plus maintenance record/reminder controls in Flutter.
- Replaced the unavailable OBD placeholder with a real BLE ELM327 transport,
  including ELM initialization, serialized commands, six live SAE J1979 PIDs,
  mode 03 DTC reading, disconnect handling, and parser tests.
- Added on-device VIN OCR with VIN normalization/check-digit preference,
  manual correction, and deletion of the temporary camera image.
- Added real recorded-audio playback, stop/replay/delete behavior, audio-session
  activation, completion handling, and playback position.
- Added a blocking physical-device and App Store/Google Play QA matrix at
  `autoMind/docs/device-store-qa.md`.

## Verified

- Laravel formatter: passed.
- PHPStan: passed with zero errors.
- Laravel tests: 61 passed with 429 assertions; one intentionally skipped
  live-provider test.
- API/OpenAPI parity: 88 operations across 68 paths.
- Postman parity: 88 requests.
- MySQL schema export: matches all migrations, 182 statements.
- Production frontend build: passed using Node.js 24.4.1.
- Composer and production npm advisory audits: zero known vulnerabilities.
- Fresh isolated migration and seed: 42 makes, 285 models, 9 symptoms, 15
  maintenance services, and 10 specialties.
- Flutter static analysis: zero issues.
- Flutter tests: all 43 tests pass.
- Native compilation: Android debug APK, iOS Simulator app, and unsigned iOS
  arm64 device app build. Both simulator apps install and launch without fatal
  logs.
- Live read-only check: health and version respond successfully. The deployed
  make and symptom endpoints currently return zero records, and the new public
  maintenance-service endpoint returns 404 until this revision is deployed.

## Launch blockers requiring external credentials or consoles

| Priority | Owner action |
|---|---|
| Blocker | Deploy this backend revision and run `composer run deploy:production` so migrations and `ReferenceDataSeeder` reach the live database. |
| Blocker | Create/restrict Google Android, iOS, and web/server OAuth clients. Add Android signing SHA-1/SHA-256 fingerprints, download new Firebase config files, add the iOS reversed-client URL scheme, and set `GOOGLE_CLIENT_IDS` plus Flutter Google Dart defines. |
| Blocker | Enable Sign in with Apple for `com.automind.ai`, create an Apple Service ID for Android, register `https://automind.rafeequae.com/callbacks/sign_in_with_apple`, set `APPLE_CLIENT_IDS`, and create distribution provisioning profiles. |
| Blocker | Supply a newly generated OpenAI API key and webhook secret, then run the production/provider preflight and one approved low-cost live-provider test. No provider secret was added to source control. |
| Blocker | Supply MySQL, SMTP, administrator password hash, and Firebase service-account credentials in the production secret store. Upload an APNs `.p8` key to Firebase for iOS push. |
| Blocker | Create the Android upload keystore and ignored `android/key.properties`; configure Apple distribution certificates/profiles. |
| Required | Populate audited current currency-rate and labor-rate sources before exposing price estimates as production market guidance. The application deliberately does not invent volatile financial data. |
| Required | Configure persistent queue workers for all named queues, the one-minute scheduler, backups, restore tests, centralized logs, HTTPS monitoring, and alerting. |

The production preflight rejects placeholder or unsafe values for the
configuration it can validate locally. It cannot prove that third-party
credentials are active without an authorized connection test.

## Product features still not ready

These are product/UI integrations rather than missing backend API contracts:

- Physical qualification of the BLE OBD-II implementation. Source support is
  complete for compatible BLE ELM327 UART profiles, but it must be tested
  against named adapters and real vehicles. Generic Bluetooth Classic SPP
  adapters remain outside the supported scope, particularly on iOS.
- Password-reset app/universal links. Reset screens and API calls exist, but
  Android App Links, iOS Universal Links, and the mail URL need the final
  production domain/team configuration.
- Full profile editing and avatar-management UI. Backend profile and avatar APIs
  exist; the consumer screen currently focuses on account display, settings,
  help/safety, logout, and deletion.
- Mechanic map/location permission UX, appointment availability/reviews, and
  complete cursor/load-more controls across every list.
- Full report feedback, estimate refresh/source-link presentation, and
  accessibility/content review of all safety states.
- A dedicated admin application. Typed Flutter admin access exists but is kept
  out of consumer navigation; the backend provides a protected web admin entry.
- Store-release QA on physical Android/iOS devices, including social login,
  push, background/resume, camera, microphone, RTL, accessibility, poor
  networks, and account deletion.

## Recommended release gate

Do not publish a store build until every blocker above is complete, every
required row in `autoMind/docs/device-store-qa.md` is signed off, the live
catalog endpoints return the seeded data, `composer check` and `flutter test`
pass on the release commit, production preflight passes on the host, and a
physical-device smoke test succeeds for email login, Google, Apple, push,
diagnosis, report retrieval, and logout/session expiry.
