# Database Backup Post‑Audit Report (BU‑Segmented)

## Scope

This report covers the application’s database backup posture for the primary (“master”) database and all tenant / Business Unit (BU) databases configured via `app_settings`, and documents the implemented per‑BU automated backup workflow.

## Initial Findings (Before This Implementation)

### Configuration / Workflow Gaps

- Backups were scheduled as a single daily `backup:run --only-db` job, which can produce a combined backup set across multiple configured connections.
- The scheduled job did not enforce per‑BU isolation as a first‑class requirement (one archive per BU).
- Retention was configured for short windows (default strategy values), not aligned to a 90‑day daily retention requirement with long‑term monthly retention.
- Backup success notifications were enabled, which can be noisy at scale when running per‑BU backups.

### Security Gaps / Risks

- Backup archive encryption depended on configuration being present (e.g., `BACKUP_ARCHIVE_PASSWORD`). Without it, backups could be stored unencrypted at rest.
- Off‑site upload depended on destination disk configuration (e.g., S3), but was not enforced as part of a BU‑segmented workflow.

### Operational Observations

- The system uses tenant databases (one database per BU) selected dynamically via `app_settings` and `SetTenantDatabase` middleware. This architecture is compatible with strict BU‑segmented backups (database‑level isolation).

## Implemented Solution

### Per‑BU Segmentation

- Implemented BU‑segmented backups as a single encrypted ZIP archive containing one SQL dump per BU (stored under `db-dumps/` inside the ZIP).
- Backup source is limited to each BU database dump, ensuring logical separation inside the combined archive.

### Compression

- ZIP compression is enabled via Spatie backup configuration.
- Compression level is configurable through `BACKUP_ZIP_COMPRESSION_LEVEL`.

### Encryption (AES‑256 at Rest)

- Backup ZIP archives are encrypted using the configured Spatie backup encryption settings.
- The system enforces presence of `BACKUP_ARCHIVE_PASSWORD` when `BU_BACKUP_REQUIRE_ENCRYPTION=1`.

### Off‑Site Upload (Cloud Storage)

- Off‑site upload is achieved by including `s3` in `BACKUP_DISKS`.
- Cloud bucket versioning must be enabled at the bucket level (provider configuration).

### Logging + Alerting

- All BU backup operations log to `storage/logs/laravel.log` with BU identifiers, timestamps, duration, and file metadata (look for event keys like `backup.bu.run.*`).
- Failure alerting is performed via email to recipients configured in `BACKUP_ALERT_TO` (or `BACKUP_NOTIFICATION_TO`).
- Health monitoring alerts when BU backups are missing or exceed an age threshold.

### Retention Policy

- Retention is enforced per BU using the cleanup strategy:
  - Daily backups: retained for 90 days (keep all for 90 days).
  - Monthly backups: retained long‑term (default configured to 120 months; adjust as needed).
- Cleanup runs per BU backup set to ensure consistent enforcement.

## How This Resolves the Gaps

- BU isolation is guaranteed by running one backup job per BU and storing results in BU‑specific backup sets.
- Encryption is enforced (configurable strictness), preventing accidental unencrypted archives in production workflows.
- Retention aligns with cost control requirements while preserving monthly archives.
- Logging and alerting provide operational visibility and fast failure detection.

## Audit Command (Operational Verification)

Run the audit command to capture environment‑specific details (engine versions, storage capacity per disk, encryption capability):

```bash
php artisan backup:bu-audit
```

The output is written to `storage/app/private/backup-audit/*.json` by default.

## Backup Storage Location

Backups are stored on the configured backup disks. The default on‑server disk is `backups`, which writes to:

- `storage/app/private/backups/`
