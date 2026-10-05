# Flutter integration

The sibling Flutter application at `../autoMind` is connected to the remote
Laravel API. Its production default is:

```text
https://automind-ai.net/api/v1
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
https://automind-ai.net/callbacks/sign_in_with_apple
```

Build Android with:

```bash
flutter build appbundle \
  --dart-define=APPLE_SERVICE_ID=YOUR_APPLE_SERVICE_ID \
  --dart-define=APPLE_REDIRECT_URI=https://automind-ai.net/callbacks/sign_in_with_apple
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

Analysis uploads server-owned media IDs and starts the job with an idempotency
key. The start request returns HTTP 202 immediately; the client must poll the
provided `statusUrl` and must not hold that POST open or apply a fixed two- or
three-minute deadline to the whole workflow. It handles failed and cancelled
terminal states, then fetches the report from the API. Reports expose safety
guidance, limitations, missing evidence, sourced estimates, and the server
disclaimer. Price research continues independently after the core report is
ready, so refresh the report while `estimateStatus` is `queued` or `running`.

Flutter contains no OpenAI API key, model name, prompt, pricing, or webhook
secret. Those remain server-only.

## Language resolution

Send the effective app/device language as `Accept-Language: en` or `ar` on every request. Regional variants such as `ar-EG` work. With no supported header, the server uses the authenticated account locale, then English. This controls errors, notifications, maintenance definitions, catalog names and report history summaries.

The backend `App\Support\ContentLocale` is authoritative for report language. It strips URLs/email, VINs and alphanumeric codes containing digits; ignores one-letter tokens, automotive acronyms, common makes/models, and selected vehicle make/model tokens; and counts the remaining Arabic/Latin letters. The dominant script wins (Arabic on a tie). One-word symptoms such as `noise` count. Technical-only input such as `BMW P0301 RPM 800` does not override the fallback.

- `POST /diagnoses` and `PATCH /diagnoses/{id}` infer locale from meaningful description. Creation accepts optional `inputLocale`/`reportLocale` analysis fallback hints; updates accept `reportLocale`. App locale is the default. The backend corrects legacy English defaults when meaningful Arabic text exists.
- Spoken descriptions use automatic transcription without forcing the UI language. If there is no meaningful typed description, meaningful transcript text determines input/report language and is saved in the analysis manifest.
- `GET /reports/{id}` and `GET /diagnoses/{id}/report` return resolved `reportLocale` and matching `meta.locale`. Priority is typed question, then transcript, then current app locale. The entire report page should use this locale without changing the global app locale.
- Once report access is authorized, report-operation errors use the resolved report language; follow-up submission errors use a meaningful submitted question language when present. Other app endpoints remain in the header language.
- `GET /reports` returns app-localized summaries and summary `reportLocale`. Fetching a full report can resolve a different language.
- `GET /reports/{id}/share` signs the resolved locale into the URL. Public JSON and HTML respect that signed locale even when the recipient app uses another language.
- `GET`/`POST /reports/{id}/follow-ups` return `answerLocale` for each answer and its suggested evidence. A meaningful follow-up question wins over both app and report locale; technical-only or photo-only input falls back to report locale. Original question text is preserved.

Display `Vehicle.displayBrand` and `displayModel` only when `displayLocale` matches the page locale; retain canonical `brand`/`model` for editing and identifiers. Estimate line items expose localized `displayName`, `categoryLabel` and `unitLabel`; never display `canonicalCode` as prose. Report `missingEvidence` remains stable enum codes, with ordered `missingEvidenceLabels` for display. Part numbers, OBD codes, units in evidence and original source citation titles remain technical identifiers or proper names.

AI output stays bilingual in storage. Missing localized nullable fields are null or omitted from text lists instead of copied from another language. Obvious wrong-language main titles/summaries fail analysis with localized `schema` status; invalid follow-up prose returns HTTP 502 `FOLLOW_UP_INVALID_RESPONSE` and is not saved. No live AI request is needed for the language regression tests.

## External mobile release requirements

Before store submission, supply the Android upload keystore through the ignored
`android/key.properties`, configure Apple distribution certificates and
profiles, upload the APNs key to Firebase, and replace both Firebase client
configuration files after the OAuth clients are created. These credentials
cannot be generated from source control.
