# Retention and deletion

Defaults are configuration, not legal advice: raw diagnostic media 30 days, AI run metadata 90 days, audit logs 365 days, and deleted-account grace data 30 days. `PurgeExpiredData` runs daily and removes expired raw media and metadata while preserving referential integrity. Production owners must replace these defaults with jurisdiction- and consent-appropriate periods.

Account deletion immediately revokes Sanctum tokens, invalidates device tokens, cancels active diagnostic sessions, and marks the account for deletion. A scheduled purge deletes or anonymizes remaining user data after the configured grace period. Backups have their own encrypted lifecycle and must age out independently. Legal holds must be implemented as an explicit deployment policy before use in regulated environments.
