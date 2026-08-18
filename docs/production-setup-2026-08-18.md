# Production setup status — 2026-08-18

This handoff intentionally contains no passwords, private keys, tokens, or
other secret values.

## Verified live

- `https://automind-ai.net/api/v1/health?type=liveness` returns HTTP 200.
- `https://automind-ai.net/api/v1/health?type=readiness` returns HTTP 200 with
  database, storage, and queue checks passing.
- The scheduler runs every minute.
- The database queue is drained every minute with `flock`, `/bin/sh`, and
  `scripts/run-queue-cron.sh` (or the equivalent private-host wrapper).
- The two stale duplicate queue cron entries were removed; only the scheduler
  and the valid locked queue worker remain.
- Android App Links publish the current Google Play signing SHA-256
  fingerprint for `com.automind.ai`.
- Apple Universal Links publish `D7ZNW2XCPQ.com.automind.ai`.
- Firebase contains the Google Play signing and upload-key SHA-1 and SHA-256
  fingerprints.
- The Firebase Android, iOS, and browser API keys are restricted to the
  corresponding package/certificate, bundle identifier, and HTTPS referrers;
  all three retain only the required Firebase API allow-list.
- Apple Sign in with Apple is enabled for `com.automind.ai`; the web Service ID
  is `com.automind.ai.service` and returns to the production backend callback.
- A production-only, topic-scoped APNs key exists for `com.automind.ai`.
- The production APNs key is uploaded to Firebase Cloud Messaging.
- A dedicated least-privilege FCM sender service account exists and its JSON
  credential is stored locally outside Git at
  `/Users/tajawal/.automind/google/firebase/automind-fcm-service-account.json`
  with owner-only permissions. It is also installed at the private Hostinger
  path below with mode `0600`; live Google OAuth authorization for the FCM
  messaging scope succeeds.
- Google Sign in is published for external users with Play-signed Android,
  upload-signed Android, iOS, and web/server OAuth clients. The consent screen
  includes the production homepage, privacy policy, terms, and authorized
  domain.
- The live backend has all four Google OAuth audiences configured, and push
  notifications are enabled with the dedicated FCM credential.
- The Android upload keystore is stored outside Git at
  `/Users/tajawal/.automind/android/automind-upload-20260818.jks`; the ignored
  `android/key.properties` points to it.
- A signed release bundle was produced at
  `/Users/tajawal/Documents/autoMind/build/app/outputs/bundle/release/app-release.aab`.
  Its SHA-256 is
  `8f1cc03695bfbf34155043eebfebe0b165ab935e4afa5fc3b901d9d14539a807`.
  This bundle was rebuilt with `config/dart_defines.production.json` and its
  JAR signature verifies successfully.
- Flutter static analysis passes and all 172 Flutter tests pass.
- Public terms, privacy, support, and account-deletion pages return HTTP 200.
- The previously exposed Laravel application key was rotated. Existing login
  sessions, encrypted cookies, OTP challenges, password-reset links, and
  signed URLs created under the old key were intentionally invalidated.
- Temporary Hostinger SSH access used for this setup was revoked and the local
  temporary SSH keypair was deleted after deployment.
- Store billing is deliberately disabled until both stores, their server
  credentials, and their webhook delivery have passed sandbox tests.

## Secret locations on Hostinger

Set identifiers and secret paths in `/home/u836855124/domains/automind-ai.net/public_html/.env`.
Keep provider private files under the non-public application storage tree:

```text
/home/u836855124/domains/automind-ai.net/public_html/storage/app/private/firebase/automind-service-account.json
/home/u836855124/domains/automind-ai.net/public_html/storage/app/private/google-play/automind-play-service-account.json
/home/u836855124/domains/automind-ai.net/public_html/storage/app/private/apple/SubscriptionKey.p8
```

Apple's public trust roots are deployed from:

```text
/home/u836855124/domains/automind-ai.net/public_html/resources/certificates/apple
```

After every environment or provider-file change, run:

```bash
php artisan optimize:clear
php artisan automind:check-production-config
php artisan automind:check-provider-config
php artisan automind:check-billing-config
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

## Remaining external blockers

- Rotate the remaining exposed provider credentials: database password,
  mailbox password, OpenAI API key, and Twilio credentials. Hostinger blocks
  automated entry into password fields, so those password changes require a
  short account-owner handoff.
- Commit/deploy the refreshed Firebase mobile configuration files with the app
  release after review; the signed Android bundle already contains the new
  public OAuth build defines.
- Create a Google Play Developer API service account, Pub/Sub topic, authenticated
  push subscription, and webhook identity.
- Create the Google Play merchant payments profile before creating products.
- Generate the separate App Store Connect In-App Purchase key.
- The Apple Account Holder must accept the updated agreement and finish any
  required tax forms.
- Register an approved production WhatsApp sender and an approved OTP Content
  Template in Twilio; the shared sandbox sender is not valid for production.
- Create the store products using the identifiers already defined in the
  backend, then test purchases, renewals, cancellation, refunds, and both
  notification webhooks before setting `BILLING_ENABLED=true`.

## Mobile release values

Build release artifacts with these non-secret values after OAuth setup:

```text
GOOGLE_SERVER_CLIENT_ID=490095417240-tekglausgidv9asgbpbvbhuupilhtbfs.apps.googleusercontent.com
GOOGLE_IOS_CLIENT_ID=490095417240-thgheq5l776m46ojoc637omeqe7ir4n9.apps.googleusercontent.com
APPLE_SERVICE_ID=com.automind.ai.service
APPLE_REDIRECT_URI=https://automind-ai.net/callbacks/sign_in_with_apple
```

Do not put Twilio, OpenAI, Firebase service-account, App Store Connect, Google
Play service-account, database, SMTP, or keystore secrets in Flutter or Git.
