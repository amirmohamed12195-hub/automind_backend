# Push notifications

AutoMind stores each bilingual notification in the user inbox, then dispatches an FCM HTTP v1 job on the `notifications` queue. The job selects the user's locale, sends platform-specific Android/APNs payloads, marks successful inbox records as sent, disables invalid registrations, and retries transient failures. Device tokens are encrypted at rest and addressed by a SHA-256 hash for safe lookup.

## Backend configuration

Set the Firebase project and provide the service account through a mounted secret file:

```dotenv
FCM_PROJECT_ID=automind-d7a2b
FCM_CREDENTIALS_PATH=/run/secrets/automind-firebase-service-account.json
FCM_ANDROID_CHANNEL_ID=automind_high_importance
FCM_TIMEOUT_SECONDS=15
FCM_VALIDATE_ONLY=false
FCM_STALE_TOKEN_DAYS=90
```

For platforms that cannot mount files, inject a base64-encoded service-account JSON through `FCM_CREDENTIALS_BASE64`. Never commit the service-account JSON or put a long-lived access token in `.env`; the backend creates and caches short-lived OAuth 2.0 tokens automatically. The service account needs permission to send through the Firebase Cloud Messaging API for the configured project.

The included Docker Compose stack reads the host path from `FCM_CREDENTIALS_PATH` and exposes that file only to the queue service as `/run/secrets/automind_firebase_service_account`. Production orchestrators should provide an equivalent read-only secret mount or inject `FCM_CREDENTIALS_BASE64` through their secret manager.

Run a worker that includes the notification queue and keep the scheduler active:

```bash
php artisan queue:work redis --queue=notifications,maintenance-reminders --tries=3
php artisan schedule:work
```

The scheduler sends maintenance reminders and disables stale registrations. The app refreshes each active registration timestamp at startup and whenever Firebase rotates its token.

## Apple setup

The iOS target already contains the Push Notifications entitlement, remote-notification/background-fetch modes, Firebase plist, and Flutter handlers. Delivery to physical Apple devices additionally requires an APNs authentication key in Firebase Console:

1. Create or reuse an Apple Push Notification service `.p8` key in the Apple Developer portal.
2. In Firebase Console, open project settings, then Cloud Messaging.
3. Upload the `.p8` key for the `com.automind.ai` iOS app and provide its key ID and Apple team ID.
4. Confirm that the App ID and provisioning profile include Push Notifications.

The service-account JSON used by the backend is not an APNs key and cannot replace this step.

## Delivery flow

- `POST /api/v1/devices` registers or refreshes the authenticated installation.
- FCM token rotation replaces the old backend registration; logout removes it and deletes the local FCM token.
- Foreground Android messages are displayed through the high-importance local channel. iOS foreground presentation uses the native Firebase delegate.
- Background and terminated-state notification messages are displayed by Android or APNs.
- Taps mark the inbox notification as read and route diagnostic reports, maintenance reminders, appointments, or generic broadcasts to the relevant screen.
- `POST /api/v1/admin/notifications/broadcast` persists and queues localized broadcasts for selected users or the complete audience.

Monitor failed jobs, queue age, FCM 401/403/429/5xx responses, and invalid-registration rates. Rotate a leaked service-account key immediately and update the runtime secret without committing it.
