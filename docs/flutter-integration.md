# Flutter integration

The sibling Flutter application at `../autoMind` is connected to the remote
Laravel API. Its production default is:

```text
https://automind.rafeequae.com/api/v1
```

Override the API for development with
`--dart-define=API_BASE_URL=https://host/api/v1`. The client implements the API
envelope, localized errors, bearer-token storage, request IDs, multipart
uploads, idempotency keys, cursor pagination, and session invalidation.

## Implemented API groups

- Email/password registration, login, reset, logout, profile, settings, account
  deletion, device registration, Google login, and Apple login.
- Vehicle catalog, vehicle CRUD/selection/health, symptoms, diagnostic sessions,
  photos, audio, OBD payloads, analysis/status/cancel/retry, reports, history,
  sharing, feedback, and estimate refresh.
- Maintenance, mechanics and availability, appointments and reviews,
  notifications, system status/version, and typed admin data access.
- All 87 OpenAPI operations are accounted for in
  `lib/core/network/api_operation_manifest.dart`. The OpenAI webhook is
  intentionally backend-only and admin operations are not exposed in consumer
  navigation.

`docs/openapi.yaml` remains authoritative for request and response contracts.
Run `dart run tool/api_operation_audit.dart` from the Flutter project when the
API changes.

## Native Google login

Create OAuth clients for Android, iOS, and a web/server client in the same
Google Cloud/Firebase project. Add every accepted web/server audience to
`GOOGLE_CLIENT_IDS` on the backend.

Android needs a regenerated `android/app/google-services.json` containing the
Android OAuth client for package `com.automind.ai`, the app signing SHA-1 and
SHA-256 fingerprints, and the web/server OAuth client.

iOS needs a regenerated `ios/Runner/GoogleService-Info.plist` containing
`CLIENT_ID` and `REVERSED_CLIENT_ID`. Register `REVERSED_CLIENT_ID` under
`CFBundleURLTypes` in `ios/Runner/Info.plist`.

Build the client with:

```bash
flutter build appbundle \
  --dart-define=GOOGLE_SERVER_CLIENT_ID=WEB_CLIENT_ID

flutter build ipa \
  --dart-define=GOOGLE_SERVER_CLIENT_ID=WEB_CLIENT_ID \
  --dart-define=GOOGLE_IOS_CLIENT_ID=IOS_CLIENT_ID
```

The client sends the Google ID token to `POST /api/v1/auth/social/google`. The
backend verifies the provider signature, issuer, configured audience, subject,
expiry, and an explicit verified-email claim before linking an account.

## Native Apple login

Enable Sign in with Apple for App ID `com.automind.ai` and its production
provisioning profiles. Add the app ID and every Apple Service ID used by
Android/web to `APPLE_CLIENT_IDS`.

For Android, create an Apple Service ID and configure this HTTPS return URL:

```text
https://automind.rafeequae.com/callbacks/sign_in_with_apple
```

Build Android with:

```bash
flutter build appbundle \
  --dart-define=APPLE_SERVICE_ID=YOUR_APPLE_SERVICE_ID \
  --dart-define=APPLE_REDIRECT_URI=https://automind.rafeequae.com/callbacks/sign_in_with_apple
```

The app creates a cryptographically random raw nonce, supplies its SHA-256 hash
to Apple, and sends the raw nonce to the backend. The backend verifies the hash
against the signed Apple token. The HTTPS callback only forwards expected
Apple fields to the fixed `com.automind.ai` Android application intent.

## Envelope and diagnosis behavior

Successful JSON uses
`{"data": ..., "meta": {"requestId": "...", "locale": "en"}}`. Errors use
`{"error": {"code": "...", "message": "...", "details": {...},
"requestId": "..."}}`. The client maps stable error codes and preserves the
request ID for support.

Analysis uploads server-owned media IDs, starts the job with an idempotency key,
polls the real status/progress fields, handles failed and cancelled terminal
states, and fetches the report from the API. Reports expose safety guidance,
limitations, missing evidence, sourced estimates, and the server disclaimer.

Flutter contains no OpenAI API key, model name, prompt, pricing, or webhook
secret. Those remain server-only.

## External mobile release requirements

Before store submission, supply the Android upload keystore through the ignored
`android/key.properties`, configure Apple distribution certificates and
profiles, upload the APNs key to Firebase, and replace both Firebase client
configuration files after the OAuth clients are created. These credentials
cannot be generated from source control.
