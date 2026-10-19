# Kashier in-app payments

Kashier is integrated through `kashier_flutter_sdk` 0.1.4 on Android and iOS alongside the existing Apple/Google purchase paths. The app's paywall fetches an authenticated Kashier catalog and starts the SDK with only a server-created session ID. Card and Egyptian mobile-wallet entry stay in Kashier's payment UI; merchant secrets remain on the backend.

Availability uses the authenticated user's stored `country_code`: only `EG` is eligible. This is account country, not physical geolocation or a verified store storefront. Missing country fails closed; changing language, sending a country in the request, or supplying an IP header cannot enable it. Eligibility is checked again when creating an order. Existing orders can still be verified if country changes.

The public website has no purchase flow. `/billing` redirects to the app download page, website billing login/checkout are disabled, and public Plans links have been removed. A neutral return page and signed webhook remain available for provider callbacks.

Prices approved for this integration:

| Plan | Total in EGP | Fulfilment |
| --- | ---: | --- |
| SINGLE_FULL_REPORT | 30.00 | One non-expiring report credit |
| PLUS_MONTHLY | 100.00 | One prepaid calendar month |
| PLUS_YEARLY | 1000.00 | One prepaid calendar year |

Plus purchases do not renew automatically. The existing plan's features and report limits apply. An active subscription prevents buying another Kashier subscription. Existing store purchases and subscriptions are unchanged.

## Configuration

Set these only in the deployed, untracked `.env` (or secret manager):

```dotenv
KASHIER_ENABLED=true
KASHIER_MOBILE_ENABLED=true
KASHIER_WEB_ENABLED=false
KASHIER_MODE=live
KASHIER_MERCHANT_ID=YOUR_MID
KASHIER_API_KEY=YOUR_PAYMENT_API_KEY
KASHIER_SECRET_KEY=YOUR_SECRET_KEY
KASHIER_SINGLE_REPORT_EGP=30.00
KASHIER_PLUS_MONTHLY_EGP=100.00
KASHIER_PLUS_YEARLY_EGP=1000.00
```

`BILLING_ENABLED` must also be true. `APP_URL` must be the canonical HTTPS origin. The credentials must belong to the selected mode. The mode selects the API host; do not send a `mode` field in the session JSON (the API rejects it despite the documentation listing it). Test mode calls `test-api.kashier.io`; live mode calls `api.kashier.io`. Checkout URLs must be HTTPS on `payments.kashier.io`. Do not commit keys or paste them into logs, issue trackers, screenshots, or client builds.

Test payments on an `APP_ENV=production` server are recorded but **never grant entitlements or report credits**. Local/testing environments can exercise fulfilment with test sessions.

## Deployment

Run the additive migration, clear config/routes/views, and restart workers using the normal deployment script. The existing Hostinger CLI release-preparation job also applies the Kashier migration. It remains inaccessible over HTTP. `automind:check-billing-config` validates Kashier when enabled.

The per-session `serverWebhook` is `https://automind-ai.net/api/v1/billing/webhooks/kashier`. It requires no bearer token or browser CSRF token. Its HMAC is verified with the Payment API Key, never the Secret Key. The signature must cover all critical payment fields. A signed delivery triggers a fresh server request for the known session; no benefit is granted from browser return parameters or the unsigned event name. Return HTTP 200 for handled and repeated deliveries, 401 for invalid signatures, and 503 on provider errors so Kashier retries.

Register the same URL for live `pay`, `capture`, `refund`, `partial_refund`, `void`, and `reversal` events if those event types are offered by the dashboard. Per-session callbacks handle checkouts even without a global registration. Do not subscribe to transfer/payout events for this integration.

`automind:reconcile-kashier` runs every 15 minutes through the existing Laravel scheduler. It checks up to 50 pending/recent paid orders, including refunds, using the stored session ID. It never initiates charges. Recent means 180 days; older refunds rely on webhooks or a customer status refresh.

## Integrity and operations

- Prices, plan eligibility, customer references, return URLs, and webhook URLs are set server-side.
- Checkout creation uses a unique per-user request key and an account lock. The same request cannot create another chargeable session. A session creation timeout is not automatically retried.
- Session merchant, order, amount, and currency must all match the stored order. `PAID` with the exact captured amount is required. Authorized-only or declined payments grant nothing.
- Session creation returns the session directly. Verification uses authenticated `GET /v3/payment/sessions/{id}/payment`, whose response wraps payment details in `data`. The public session endpoint rejects already-paid sessions and must not be used for reconciliation. A missing status envelope fails closed and prompts webhook retries.
- Fulfilment is transactional and locked per account/order. Duplicate callbacks and page refreshes cannot duplicate credits or extend prepaid expiry.
- Full refunds/reversals revoke related subscription access and reverse an available report credit. Used report credits are flagged `refundNeedsReview` on the purchase. Partial refunds are marked `partially_refunded` for operator review and retain existing access.
- Mobile endpoints use Sanctum authentication, account-active checks, throttles, ownership checks, and private/no-store responses. The app verifies server status after SDK success, pending, cancellation, and failure. SDK callbacks never grant access. Pending sessions can be resumed, and account changes discard old callbacks.
- Checkout creation can be disabled with `KASHIER_ENABLED=false`; verification of existing payments continues.
- Kashier purchases appear as `platform=kashier` in purchase records, and subscriptions use `source=kashier`. Apple/Google reconciliation does not process them.

A paid live transaction and any live refund must be performed by the account holder. A successful session-creation probe proves credentials and checkout availability, not settlement.

## Reference

- https://developers.kashier.io/docs/api-reference/payment-sessions/createPaymentSession
- https://developers.kashier.io/docs/api-reference/payment-sessions/getPaymentSession
- https://developers.kashier.io/docs/webhooks
- https://developers.kashier.io/docs/webhooks/payloads
- https://developers.kashier.io/docs/get-started/going-live

Run `php artisan test --filter=Kashier` for signature, ownership, duplicate-payment, refund, environment, and entitlement checks. The full backend suite also exercises the existing store integrations.

## Mobile API

- `GET /api/v1/billing/kashier/catalog?platform=android|ios`: returns availability, account reference, EGP plans, active subscription flag, and any open order.
- `POST /api/v1/billing/kashier/orders`: accepts `platform`, `plan`, and UUID `idempotencyKey`; ignores client amounts/currency/country. Returns an owned order and a session ID only while it is payable.
- `GET /api/v1/billing/kashier/orders/{order}`: verifies settlement/refund status and returns authoritative `fulfilled` plus entitlements. Refresh calls are coalesced for three seconds.

The app's existing store catalog, receipt verification, restore flow, and prices are preserved. Kashier subscriptions are prepaid; their app account page does not expose store cancellation controls. The API never returns merchant API or secret keys, and payment/session identifiers should not be logged.

## Distribution constraint

Account-country filtering is not store permission. As reviewed on 2026-10-19, Egypt is not in Google's published alternative-billing markets; Apple's standard Egyptian storefront rules require IAP for these digital reports and Plus features. This change implements the requested technical integration, not a StoreKit external-purchase entitlement or Google alternative-billing enrollment/reporting program. No app-store submission was made. Resolve the applicable store distribution requirements before publishing this flow, or disable new Kashier sessions with `KASHIER_MOBILE_ENABLED=false`.

- https://support.google.com/googleplay/android-developer/answer/13821247
- https://developer.apple.com/app-store/review/guidelines/#in-app-purchase
- https://pub.dev/packages/kashier_flutter_sdk
