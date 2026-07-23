# OpenAI API integration

All OpenAI API calls originate on the trusted backend. The Flutter app never receives a provider key. Provider-specific code implements application contracts so endpoints and model families can be upgraded without changing the domain workflow.

## Endpoint and capability matrix

| Stage | Endpoint | Default model | Required capability | Persistence |
|---|---|---|---|---|
| Diagnostic synthesis | `POST /v1/responses` | `gpt-5.6-terra` | text, Structured Outputs | strict bilingual report graph |
| Photo observations | `POST /v1/responses` | `gpt-5.6-terra` | image input, Structured Outputs | evidence observations and run metadata |
| Engine acoustic observations | `POST /v1/chat/completions` | `gpt-audio-1.5` | audio input, JSON schema | cautious acoustic evidence |
| Spoken symptom transcript | `POST /v1/audio/transcriptions` | `gpt-4o-mini-transcribe` | transcription | original-language user evidence |
| Part-price research | `POST /v1/responses` | `gpt-5.6-luna` | `web_search`, Structured Outputs | attributable sources, quotes, estimate |

Terra is the balanced diagnosis/vision choice and Luna is used for efficient web research. All IDs are environment-driven. `OPENAI_MODEL_CAPABILITIES_JSON` is the deployment-approved allowlist; invalid stage/model combinations fail production boot and `automind:check-provider-config`. Capability changes must be verified against current official model documentation before updating it.

## Request behavior

- Diagnostic schema version: `diagnostic-report-v1`; prompt version: `diagnostic-v1`. Every object rejects additional properties.
- Responses requests set `store: false` by default, a hashed `safety_identifier`, bounded output tokens, and no raw email, phone, VIN, or database ID.
- Images use resized, re-encoded private bytes as data URLs and default to `high` detail for vehicle damage and component inspection; deployments may explicitly lower it only after an image-quality and cost evaluation. Engine audio is normalized and submitted as audio, never fake-transcribed.
- Web search is capped at three tool calls, includes the full source list, deduplicates by URL hash, rejects currency/part incompatibility, and returns unavailable when evidence is insufficient.
- User text, OCR, OBD descriptions, transcripts, and web pages are untrusted evidence. Prompts explicitly prohibit following instructions found inside that evidence.

## Errors, retries, and webhooks

Refusals, incomplete output, missing structured output, schema errors, authentication/configuration failures, 429s, and 5xx responses are categorized separately. Only transient errors retry, with bounded exponential backoff. A provider `Retry-After` value schedules the next attempt at that delay. Exhausted jobs become reviewable failed sessions; permanent failures do not consume repeated provider calls.

Provider calls remain foreground within asynchronous Laravel queue jobs, so `OPENAI_BACKGROUND_MODE` must remain `false`; the deployment validator rejects `true` rather than allowing an incomplete provider-resume path. The public webhook endpoint is retained for provider events and forward-compatible background processing. When configured, it validates the exact raw body using Standard Webhooks headers, acknowledges with 202, queues processing, and deduplicates by webhook ID and provider object ID. Duplicate or out-of-order events cannot publish the same result twice.

## Cost and observability

Each provider call stores task, endpoint, model, prompt version, response ID, input/output/cached/reasoning tokens, latency, status, safe error category, source/tool counts, pricing version, and estimated USD cost. Full sensitive request/response bodies are not logged.

Rates live in versioned `OPENAI_PRICING_MODELS_JSON`, never business logic. Example shape:

```json
{
  "gpt-5.6-terra": {"input": "2.5", "cachedInput": "0.25", "output": "15"},
  "gpt-audio-1.5": {"input": "32", "output": "10"},
  "gpt-4o-mini-transcribe": {"input": "1.25", "output": "5"},
  "gpt-5.6-luna": {"input": "1", "cachedInput": "0.1", "output": "6", "webSearchCall": "0.01"}
}
```

This `openai-api-standard-2026-07-23` snapshot uses standard-tier USD rates
per million tokens and $0.01 per web-search call. The audio-analysis request
uses the audio-input rate and text-output rate. Re-verify the snapshot against
the [official API pricing](https://developers.openai.com/api/docs/pricing) page
before a later production release. The deployment validator requires pricing
entries for every configured model. Daily
per-user and global spend guards stop new calls after configured limits.
Request/queue/provider latency, error categories, refusal/schema rates, source
count, and approximate spend are emitted through structured logs and database
metadata.

No ordinary health check makes a paid request. Live provider tests must be separately tagged and explicitly enabled with `RUN_OPENAI_LIVE_TESTS=true`; unit and CI tests bind fake adapters.
