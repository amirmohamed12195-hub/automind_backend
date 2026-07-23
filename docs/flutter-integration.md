# Flutter integration

The checked-in Flutter app currently has remote data-source interfaces but its repository implementations still bind local/mock sources. The backend preserves the existing `Vehicle`, `DiagnosticSession`, and `DiagnosticResult` required fields; the app needs the additive wiring below to use it.

## Exact client files to change

| File | Required change |
|---|---|
| `lib/core/constants/api_constants.dart` | Change the default from `https://api.example.com/v1` to the deployed `https://host/api/v1`; local Android emulator uses `http://10.0.2.2:8080/api/v1`. Prefer `--dart-define=API_BASE_URL=...`. |
| `lib/core/network/api_client.dart` | Add PATCH, PUT, DELETE, multipart POST, response-envelope unwrapping, and optional idempotency/header support. Current client only has JSON GET/POST. |
| `lib/core/network/auth_interceptor.dart` | Keep Bearer injection; also send `Accept: application/json`, current `Accept-Language`, and a new UUID/ULID `X-Request-Id`. On 401, clear the token and route to sign-in without looping retries. |
| `lib/features/authentication/data/datasources/auth_remote_data_source.dart` | Implement login with `POST /auth/login`, current user with `GET /me`, logout with `POST /auth/logout`; add register/reset/social/profile/settings/device-token methods as UI exposes them. Save `data.accessToken`. |
| `lib/features/authentication/data/repositories/auth_repository_impl.dart` | Inject/bind the remote implementation instead of `AuthLocalDataSource`; keep local storage only as an offline cache/session bootstrap. |
| `lib/features/vehicles/data/datasources/vehicles_remote_data_source.dart` | Map GET/POST/PATCH/DELETE `/vehicles`; implement selected vehicle, health, and catalog calls. |
| `lib/features/vehicles/data/repositories/vehicles_repository_impl.dart` | Inject/bind `VehiclesRemoteDataSource` instead of `VehiclesLocalDataSource`. |
| `lib/features/diagnosis/data/datasources/diagnosis_remote_data_source.dart` | Implement session CRUD, media/OBD upload, analyze, polling, cancel/retry, report/history, feedback, estimate refresh, and share. |
| `lib/features/diagnosis/data/repositories/diagnosis_repository_impl.dart` | Inject/bind `DiagnosisRemoteDataSource` instead of `DiagnosisLocalDataSource`. |
| `lib/features/diagnosis/domain/entities/diagnosis_entities.dart` | Add `queued` and `cancelled` statuses; model `progress`/`currentStep`; add optional service estimate, sources, disclaimer, limitations, missing evidence, evidence quality, professional inspection, and emergency warnings. Existing required result fields stay unchanged. |
| generated Injectable configuration | Regenerate after adding `@LazySingleton(as: ...)` remote implementations. |

## Existing method-to-endpoint mapping

| Current interface method | API operation | Response data |
|---|---|---|
| `AuthRemoteDataSource.login` | `POST /auth/login` | `{user, accessToken, tokenType}` |
| `AuthRemoteDataSource.logout` | `POST /auth/logout` | HTTP 204 |
| `AuthRemoteDataSource.getCurrentUser` | `GET /me` | user |
| `VehiclesRemoteDataSource.getVehicles` | `GET /vehicles` | vehicle array |
| `addVehicle` | `POST /vehicles` | vehicle |
| `updateVehicle` | `PATCH /vehicles/{vehicleId}` | vehicle |
| `deleteVehicle` | `DELETE /vehicles/{vehicleId}` | HTTP 204 |
| `DiagnosisRemoteDataSource.createSession` | `POST /diagnoses` | diagnostic session |
| `updateSession` | `PATCH /diagnoses/{sessionId}` | diagnostic session |
| `analyzeSession` | `POST /diagnoses/{sessionId}/analyze`, poll status, then fetch report | accepted status then diagnostic report |
| `getHistory` | `GET /diagnoses`; fetch each completed report or introduce an app-side session/history model | cursor page |
| `getResult` | `GET /reports/{reportId}` or `/diagnoses/{sessionId}/report` | diagnostic report |

Other API groups map directly: catalog/symptoms; media and OBD; report feedback/estimate/share; vehicle maintenance/reminders; mechanics/availability; appointments/reviews; notifications/read; account/settings/devices; and role-protected admin operations. `docs/openapi.yaml` is authoritative for all 87 operations and can generate typed clients.

## Envelope and error mapping

Successful JSON is `{ "data": ..., "meta": { "requestId": "...", "locale": "en", "nextCursor": null } }`. Errors are `{ "error": { "code": "VALIDATION_FAILED", "message": "...", "details": {...}, "requestId": "..." } }`. Map by stable `error.code`, preserve `requestId` for support, and use HTTP status only for broad categories. Validation details are arrays keyed by camelCase request fields.

## Dio examples

```dart
final form = FormData.fromMap({
  'kind': 'engine_sound', // photo | engine_sound | spoken_description
  'file': await MultipartFile.fromFile(path, filename: path.split('/').last),
});
final response = await dio.post<Map<String, dynamic>>(
  '/diagnoses/$sessionId/media',
  data: form,
  options: Options(headers: {'Idempotency-Key': uploadId}),
);
final media = response.data!['data'] as Map<String, dynamic>;
```

```dart
await dio.post('/diagnoses/$sessionId/analyze',
  options: Options(headers: {'Idempotency-Key': analysisId}));

while (true) {
  final response = await dio.get<Map<String, dynamic>>('/diagnoses/$sessionId/status');
  final status = response.data!['data'] as Map<String, dynamic>;
  switch (status['status']) {
    case 'completed':
      return (await dio.get<Map<String, dynamic>>('/diagnoses/$sessionId/report')).data!['data'];
    case 'failed':
    case 'cancelled':
      throw DiagnosticTerminalException(status);
  }
  await Future<void>.delayed(const Duration(seconds: 2));
}
```

Every create/analyze/refresh/appointment retry should reuse the same `Idempotency-Key`. Do not generate a new key after a network timeout unless the user intentionally starts a new operation.

## Payload notes

- Vehicle request: `brand`, `model`, `year`, `engine`, `fuelType`, `transmission`, `mileage`, optional `vin`, `plateNumber`, `nickname`, and catalog IDs.
- Diagnosis request: `vehicleId`, description up to 500 characters, `selectedSymptoms`, `inputLocale`, `reportLocale`, consent version, and optional `{countryCode, city, currency}` market.
- OBD: `recordedAt`, normalized `troubleCodes`, and optional sensor numbers. Unknown codes remain unknown.
- Report keeps all existing required fields: `id`, `sessionId`, `vehicleId`, `vehicleName`, `title`, `summary`, `confidence`, `severity`, `drivingRecommendation`, `suspectedFaults`, `safeChecks`, `recommendedActions`, and `createdAt`.
- `serviceEstimate` adds low/typical/high decimal ranges, currency, assumptions, status, and expiry. `sources` adds clickable `url`, domain/title, retrieval time, and quality metadata. Display the supplied disclaimer and do not label estimates as repair quotes.
