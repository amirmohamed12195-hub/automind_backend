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
- A Google Play closed Alpha release draft was prepared for
  `AutoMind 1.0.0 Alpha`, but the uploaded bundle was not persisted before the
  Play Console page refreshed. The release is not rolled out and the signed
  bundle must be uploaded again and saved as a draft.
- The App Store Connect consumable
  `com.automind.ai.full_report.single.v1` is configured for all storefronts at
  a USD 4.99 base price with English localization, review notes, and a valid
  review screenshot.
- The App Store Connect subscription group `AutoMind Plus` and monthly product
  `com.automind.ai.plus.monthly.v1` exist. The group is localized and the
  monthly product is available in all storefronts; pricing and remaining
  review metadata are not yet complete.
- Twilio Content Template `automind_whatsapp_otp` was created with Content SID
  `HX8e191724c380d3fdc20dca7ecd7b756a`, authentication category, a 10-minute
  expiry, and a copy-code button. It cannot be submitted for WhatsApp approval
  until a production WhatsApp sender is registered.

## Exact store catalog

The mobile app and backend already agree on these immutable product IDs:

| Store | Type | Product ID | Base plan | Intended USD price |
| --- | --- | --- | --- | ---: |
| Google Play | Consumable | `automind_full_report_single_v1` | — | 4.99 |
| Google Play | Subscription | `automind_plus_v1` | `monthly-v1` | 9.99 |
| Google Play | Subscription | `automind_plus_v1` | `yearly-v1` | 99.99 |
| App Store | Consumable | `com.automind.ai.full_report.single.v1` | — | 4.99 |
| App Store | Subscription | `com.automind.ai.plus.monthly.v1` | — | 9.99 |
| App Store | Subscription | `com.automind.ai.plus.yearly.v1` | — | 99.99 |

Do not rename or replace these IDs after customers can purchase them.

## Production environment values after provider approval

The following non-secret values are ready. Keep both feature switches false
until the missing credentials, sender, products, and sandbox tests are complete:

```dotenv
BILLING_ENABLED=false
BILLING_ENVIRONMENT=sandbox
BILLING_WEBHOOK_BASE_URL=https://automind-ai.net/api/v1/webhooks
BILLING_TERMS_URL=https://automind-ai.net/terms
BILLING_PRIVACY_URL=https://automind-ai.net/privacy
BILLING_RECONCILIATION_BATCH_SIZE=100
BILLING_RECONCILIATION_STALE_HOURS=12
BILLING_STALE_RESERVATION_HOURS=2

APPLE_BUNDLE_ID=com.automind.ai
APPLE_APP_ID=6801621951
APPLE_PRIVATE_KEY_PATH=/home/u836855124/domains/automind-ai.net/public_html/storage/app/private/apple/SubscriptionKey.p8
APPLE_ROOT_CERTIFICATES_PATH=/home/u836855124/domains/automind-ai.net/public_html/resources/certificates/apple
APPLE_ONLINE_CERTIFICATE_CHECKS=true
APPLE_OPENSSL_BINARY=openssl

GOOGLE_PLAY_PACKAGE_NAME=com.automind.ai
GOOGLE_PLAY_PROJECT_ID=automind-d7a2b
GOOGLE_PLAY_SERVICE_ACCOUNT_PATH=/home/u836855124/domains/automind-ai.net/public_html/storage/app/private/google-play/automind-play-service-account.json
GOOGLE_PLAY_PUBSUB_AUDIENCE=https://automind-ai.net/api/v1/webhooks/google/play-notifications
GOOGLE_PLAY_PUBSUB_TOPIC=projects/automind-d7a2b/topics/automind-google-play-billing

TWILIO_WHATSAPP_ENABLED=false
TWILIO_WHATSAPP_CONTENT_SID=HX8e191724c380d3fdc20dca7ecd7b756a
TWILIO_TIMEOUT_SECONDS=10
TWILIO_OTP_CODE_TTL_SECONDS=600
TWILIO_OTP_CHALLENGE_TTL_SECONDS=1800
TWILIO_OTP_RESEND_COOLDOWN_SECONDS=30
TWILIO_OTP_MAX_ATTEMPTS=5
```

These still require provider-generated values and must remain blank until they
are created or approved: `APPLE_ISSUER_ID`, `APPLE_KEY_ID`,
`GOOGLE_PLAY_PUBSUB_SERVICE_ACCOUNT_EMAIL`, `TWILIO_WHATSAPP_FROM`,
`TWILIO_API_KEY`, and their corresponding private secrets. Do not copy any of
those secrets into Git or the Flutter app.

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
- Create a Google Play Developer API service account, Pub/Sub topic,
  authenticated push subscription, and webhook identity. Creating the
  persistent service-account credential requires account-owner confirmation.
- Add a Google Play payout method to the existing payments profile. This is a
  financial account-owner step.
- Upload the signed AAB to the closed Alpha release again, wait for processing,
  and save the release as a draft. Then create the exact consumable and
  subscription/base-plan catalog. Do not roll out the Alpha release as part of
  catalog setup.
- Generate the separate App Store Connect In-App Purchase key.
- The Apple Paid Apps agreement and bank account are active, but the Account
  Holder must complete the outstanding U.S. foreign-status/W-8BEN tax forms.
- Finish the Apple monthly price/review metadata and create the yearly product
  `com.automind.ai.plus.yearly.v1` at a USD 99.99 base price. The intended
  monthly base price is USD 9.99.
- Register a dedicated production WhatsApp sender in Twilio. This requires the
  account owner to select and validate a phone number and may require removing
  that number from an existing consumer or small-business WhatsApp account.
  After approval, submit the existing OTP Content Template for WhatsApp
  approval.
- Create a new Twilio API key/secret only after account-owner confirmation;
  deploy the API key/secret, approved sender, and approved Content SID with
  `TWILIO_AUTH_TOKEN` blank. Keep `TWILIO_WHATSAPP_ENABLED=false` until a live
  OTP delivery and verification test succeeds.
- Test store purchases, renewals, cancellation, refunds, restore, and both
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
