# Kashier website checkout

Kashier supplements the existing Apple and Google purchase paths. Website customers visit `/billing`, sign in with their existing AutoMind email/password, and pay on Kashier's hosted checkout. Social-login users can set a password through the app's existing password-reset flow. No card details or payment keys enter the mobile application.

Prices approved for this integration:

| Plan | Total in EGP | Fulfilment |
| --- | ---: | --- |
| SINGLE_FULL_REPORT | 30.00 | One non-expiring report credit |
| PLUS_MONTHLY | 100.00 | One prepaid calendar month |
| PLUS_YEARLY | 1000.00 | One prepaid calendar year |

Plus purchases do not renew automatically. The existing plan's features and report limits apply. An active subscription prevents buying another website subscription. Existing store purchases and subscriptions are unchanged.

## Configuration

Set these only in the deployed, untracked `.env` (or secret manager):

```dotenv
KASHIER_ENABLED=true
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
- All customer pages use session authentication, CSRF protection for forms, throttles, ownership checks, and private/no-store responses for account/order pages.
- Checkout creation can be disabled with `KASHIER_ENABLED=false`; verification of existing payments continues.
- Kashier purchases appear as `platform=kashier` in purchase records, and subscriptions use `source=kashier`. Apple/Google reconciliation does not process them.

A paid live transaction and any live refund must be performed by the account holder. A successful session-creation probe proves credentials and checkout availability, not settlement.

## Reference

- https://developers.kashier.io/docs/api-reference/payment-sessions/createPaymentSession
- https://developers.kashier.io/docs/api-reference/payment-sessions/getPaymentSession
- https://developers.kashier.io/docs/webhooks
- https://developers.kashier.io/docs/webhooks/payloads
- https://developers.kashier.io/docs/get-started/going-live

Run `php artisan test --filter=KashierCheckoutTest` for signature, ownership, duplicate-payment, refund, environment, and entitlement checks. The full backend suite also exercises the existing store integrations.
