# Twilio WhatsApp phone verification setup

Password registration requires an E.164 phone number and completes only after the user enters the one-time code delivered through a Twilio WhatsApp Content Template. Google and Apple sign-in are unchanged.

The backend generates a six-digit code, sends it as Content Template variable `{{1}}`, and stores only an HMAC of the code in Laravel's configured cache. A code expires after 10 minutes by default, can be attempted five times, and is removed after successful verification. Do not use an in-memory cache in production.

## Twilio Console setup

### Sandbox testing

Twilio's shared Sandbox sender is `+14155238886`. Activate the WhatsApp Sandbox in Twilio Console, then have every test recipient send `join <your sandbox code>` to that number before requesting an OTP. The Sandbox is for testing only; messages to recipients who have not joined will fail.

The configured Content Template must contain the verification code as variable `{{1}}`. The supplied template SID starts with `HX` and matches this integration.

### Production

1. Register and approve a production WhatsApp sender in Twilio instead of using the shared Sandbox number.
2. Submit the verification Content Template for WhatsApp approval and wait until it is approved.
3. Set `TWILIO_WHATSAPP_FROM` to the approved sender and `TWILIO_WHATSAPP_CONTENT_SID` to its `HX...` Content SID.
4. Create a Twilio API key for the backend. Twilio recommends an API key and secret for production; Account SID/Auth Token authentication is intended for local testing.

## Backend credentials

For local development, put these values in `/Users/tajawal/Documents/automind_backend/.env`. In production, put them in the hosting provider's encrypted secret/environment-variable settings:

```dotenv
TWILIO_WHATSAPP_ENABLED=true
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_WHATSAPP_FROM=+14155238886
TWILIO_WHATSAPP_CONTENT_SID=HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Recommended in production
TWILIO_API_KEY=SKxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_API_SECRET=your_api_key_secret

# Local fallback: leave API key/secret blank and provide the Auth Token
TWILIO_AUTH_TOKEN=

TWILIO_TIMEOUT_SECONDS=10
TWILIO_OTP_CODE_TTL_SECONDS=600
TWILIO_OTP_CHALLENGE_TTL_SECONDS=1800
TWILIO_OTP_RESEND_COOLDOWN_SECONDS=30
TWILIO_OTP_MAX_ATTEMPTS=5
```

`TWILIO_WHATSAPP_FROM` is written as bare E.164 in the environment; the backend adds the required `whatsapp:` prefix to both sender and recipient. The outbound request uses `ContentSid` and `ContentVariables` without `Body`, as required by Twilio's Content Template API.

Never put the Auth Token, API secret, or OTP logic in the Flutter app or source control. The mobile app calls this backend, and only the backend calls Twilio.

After changing the deployed environment variables, reload Laravel's cached configuration:

```bash
php artisan optimize:clear
php artisan automind:check-production-config
php artisan migrate --force
php artisan optimize
```

## API lifecycle

- `POST /api/v1/auth/register` creates an unverified account, sends the WhatsApp code, and returns an opaque verification token. It does not issue a bearer token.
- `POST /api/v1/auth/login` returns `OTP_REQUIRED` after valid credentials when the registered phone is still unverified. A fresh WhatsApp code is sent unless the resend cooldown is active.
- `POST /api/v1/auth/otp/resend` replaces the prior code with a newly generated code after the cooldown.
- `POST /api/v1/auth/otp/verify` validates the code, marks `phone_verified_at`, removes the code so it cannot be reused, and issues the first bearer token.

The opaque verification token is encrypted with Laravel's `APP_KEY`, expires independently of the WhatsApp code, and is bound to the user and current phone number.
