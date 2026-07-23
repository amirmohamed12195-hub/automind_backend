# Architecture

## Domain map

```mermaid
erDiagram
    USERS ||--o{ SOCIAL_IDENTITIES : authenticates
    USERS ||--o{ PERSONAL_ACCESS_TOKENS : owns
    USERS ||--o{ DEVICE_TOKENS : registers
    USERS ||--o{ VEHICLES : owns
    VEHICLE_MAKES ||--o{ VEHICLE_MODELS : contains
    USERS ||--o| USER_SELECTED_VEHICLES : selects
    VEHICLES ||--o{ DIAGNOSTIC_SESSIONS : diagnosed
    DIAGNOSTIC_SESSIONS }o--o{ SYMPTOM_DEFINITIONS : reports
    DIAGNOSTIC_SESSIONS ||--o{ DIAGNOSTIC_MEDIA : uploads
    DIAGNOSTIC_SESSIONS ||--o{ OBD_SNAPSHOTS : captures
    OBD_SNAPSHOTS ||--o{ OBD_TROUBLE_CODES : contains
    DIAGNOSTIC_SESSIONS ||--o{ AI_RUNS : executes
    DIAGNOSTIC_SESSIONS ||--o| DIAGNOSTIC_REPORTS : produces
    DIAGNOSTIC_REPORTS ||--o{ DIAGNOSTIC_REPORT_TRANSLATIONS : localizes
    DIAGNOSTIC_REPORTS ||--o{ SUSPECTED_FAULTS : contains
    SUSPECTED_FAULTS ||--o{ FAULT_CAUSES : explains
    SUSPECTED_FAULTS ||--o{ PART_RECOMMENDATIONS : recommends
    DIAGNOSTIC_REPORTS ||--o{ REPORT_ACTIONS : advises
    DIAGNOSTIC_REPORTS ||--o{ REPORT_EVIDENCE : supports
    DIAGNOSTIC_REPORTS ||--o| SERVICE_ESTIMATES : estimates
    SERVICE_ESTIMATES ||--o{ SERVICE_ESTIMATE_LINE_ITEMS : totals
    USERS ||--o{ LABOR_RATE_SOURCES : maintains
    WEB_SOURCES ||--o{ LABOR_RATE_SOURCES : supports
    CURRENCY_RATES {
        string base_currency
        string quote_currency
        decimal rate
        datetime effective_at
    }
    DIAGNOSTIC_REPORTS ||--o{ PRICE_SEARCHES : researches
    PRICE_SEARCHES ||--o{ WEB_SOURCES : cites
    PART_RECOMMENDATIONS ||--o{ PART_PRICE_QUOTES : prices
    VEHICLES ||--o{ VEHICLE_MAINTENANCE_RECORDS : records
    VEHICLES ||--o{ MAINTENANCE_REMINDERS : schedules
    MAINTENANCE_SERVICE_DEFINITIONS ||--o{ MAINTENANCE_REMINDERS : defines
    MECHANICS }o--o{ MECHANIC_SPECIALTIES : specializes
    MECHANICS ||--o{ APPOINTMENTS : receives
    USERS ||--o{ APPOINTMENTS : books
    APPOINTMENTS ||--o| MECHANIC_REVIEWS : receives
    USERS ||--o{ NOTIFICATIONS : receives
    USERS ||--o{ AUDIT_LOGS : acts
```

Framework cache, jobs, sessions, reset tokens, webhook receipts, media observations, translations, and idempotency fields support this graph without leaking provider payloads into mobile-facing models.

## Diagnostic sequence

```mermaid
sequenceDiagram
    actor App as Flutter app
    participant API as Laravel API
    participant Media as Media worker
    participant AI as Diagnostic AI worker
    participant OA as OpenAI API
    participant DB as MySQL
    App->>API: Create draft with consent and Idempotency-Key
    API->>DB: Persist session
    App->>API: Upload photo/audio and OBD evidence
    API-->>Media: Queue private media processing
    Media->>DB: Mark ready or safely failed
    App->>API: POST /diagnoses/{id}/analyze
    API->>DB: Freeze manifest and mark queued
    API-->>AI: Dispatch locked workflow
    AI->>OA: Optional transcription, audio, and vision stages
    AI->>OA: Responses API strict diagnostic schema
    AI->>AI: PHP schema validation and safety escalation
    AI->>DB: Transactionally persist bilingual report graph
    opt Replaceable parts
        AI->>OA: Responses API web_search with citations
        AI->>DB: Persist sources and decimal estimate
    end
    AI->>DB: Mark completed and enqueue generic notification
    loop Until terminal
        App->>API: GET /diagnoses/{id}/status
        API-->>App: progress, step, status
    end
    App->>API: GET /diagnoses/{id}/report
```

Locks, idempotency keys, state-transition checks, and a final cancellation check prevent duplicate or stale completion. A failed price search leaves the validated diagnostic report readable with an unavailable/partial estimate. Source prices retain their original currency; normalization occurs only when an administrator has appended a provider-attributed currency rate. Labor is included only when current configured hours and hourly-rate ranges cover the job, otherwise the estimate remains partial and explicitly excludes it.
