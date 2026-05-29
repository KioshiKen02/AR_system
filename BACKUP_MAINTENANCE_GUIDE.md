# BU Backup Maintenance Guide

This guide explains how to operate, monitor, and maintain the BU‑segmented backup system.

## What Runs Automatically

Laravel Scheduler runs these commands (configured in `routes/console.php`):

- Daily BU backups (single combined ZIP by default):
  - `php artisan backup:bu-run`
  - Schedule time: `BU_BACKUP_DAILY_AT` (default: `00:00`)
- Monthly BU backups (first day of month, single combined ZIP):
  - `php artisan backup:bu-run --monthly`
  - Schedule time: `BU_BACKUP_MONTHLY_AT` (default: `00:00`)
- Retention cleanup:
  - `php artisan backup:bu-clean`
  - Schedule time: `BU_BACKUP_CLEAN_AT` (default: `03:30`)
- Backup health monitoring:
  - `php artisan backup:bu-monitor`
  - Schedule time: `BU_BACKUP_MONITOR_AT` (default: `04:00`)

## Where Backups Are Stored

By default, backups are written to:

- `storage/app/private/backups/`

By default, `backup:bu-run` produces one ZIP containing one SQL dump per BU (under `db-dumps/` inside the ZIP).

If you want a different location, set:

- `BU_BACKUP_DISKS=local` (default)
- Optional: `--folder=your-folder-name` when running the command

## File Naming

- Daily combined backup filename:
  - `May-30-2026.zip`
- Monthly combined backup filename:
  - `May-2026.zip`

If the file already exists (e.g., you ran it twice), the system will create `May-30-2026 (2).zip`, etc.

## Monthly Timing

- The monthly job runs at **00:00 on the 1st day of the month**.
- The file name uses the **previous month** when it runs on the 1st (example: runs on June 1, 00:00 → `May-2026.zip`).

## Folder Layout

- Daily backups:
  - `storage/app/private/backups/daily/May-30-2026.zip`
- Monthly backups:
  - `storage/app/private/backups/monthly/May-2026.zip`

## Why “backup-temp” Exists

`storage/app/backup-temp/` is used as a working folder while generating SQL dumps and building the ZIP file. The combined backup command deletes its temporary working folder after finishing.

## Required Configuration (Production)

### Encryption

- Set `BACKUP_ARCHIVE_PASSWORD` to enable AES‑256 encrypted ZIP archives.
- Enforce encryption:
  - `BU_BACKUP_REQUIRE_ENCRYPTION=1` (default behavior)

### Backup Disks

Configure where BU backups are stored using `BU_BACKUP_DISKS`:

- Example (local + network):
  - `BU_BACKUP_DISKS=backups,network_backup`
- Example (include S3 off‑site):
  - `BU_BACKUP_DISKS=backups,network_backup,s3`

### Off‑Site (S3) Requirements

- Configure Laravel S3 disk:
  - `AWS_ACCESS_KEY_ID`
  - `AWS_SECRET_ACCESS_KEY`
  - `AWS_DEFAULT_REGION`
  - `AWS_BUCKET`
  - Optional: `AWS_ENDPOINT`, `AWS_URL`
- Enable bucket versioning in AWS S3 console (required for versioned backup history).

### Alerting (Email)

- Configure recipients:
  - `BACKUP_ALERT_TO=eng1@company.com,eng2@company.com`
  - Fallback used if empty: `BACKUP_NOTIFICATION_TO`
- Ensure SMTP is correctly configured in `config/mail.php` via env vars.

## Retention Policy

Retention is enforced by `backup:bu-clean` for the combined ZIP archives:

- Keep all backups for N days:
  - `BACKUP_KEEP_ALL_DAYS=90`
- Keep monthly backups long‑term:
  - `BACKUP_KEEP_MONTHLY_MONTHS=120` (adjust to policy)

To adjust retention without code changes, change the env vars above and redeploy.

## Monitoring and Logs

### Logs

Backup operations log to:

- `storage/logs/laravel.log` (look for `backup.bu.*` event keys)

Log events include:

- per‑BU start/success/failure
- archive path, size, last‑modified timestamps per destination disk
- monitor results (unhealthy/healthy)

### Health Monitoring

`backup:bu-monitor` checks the latest combined ZIP on each configured disk:

- Missing backups => unhealthy
- Latest backup older than the threshold => unhealthy

Threshold:

- `--max-age-hours` (default: 30)

## Credential Rotation

### Backup Archive Password Rotation

Rotating `BACKUP_ARCHIVE_PASSWORD` affects decryptability of existing archives.

Recommended approach:

- Keep the old password available for decrypting historical backups until policy allows them to expire.
- Rotate to a new password for all new backups.

### Cloud Credential Rotation

- Rotate AWS keys in your secrets manager / environment configuration.
- Validate by running a manual backup to S3:

```bash
php artisan backup:bu-run --bu=<bu_id> --disks=s3 --notify=0
```

## Manual Operations

### Run a Backup for a Single BU

```bash
php artisan backup:bu-run --bu=<bu_id_or_base_url>
```

### Run Cleanup

```bash
php artisan backup:bu-clean
```

### Check Health

```bash
php artisan backup:bu-monitor
```

## Troubleshooting

### Common Causes of Failure

- `BACKUP_ARCHIVE_PASSWORD` not set (encryption enforced)
- `mysqldump` not found (set `DB_DUMP_PATH`)
- Tenant DB credentials unreachable (network / firewall / DNS)
- Off‑site upload fails (AWS credentials or bucket policy)

### Quick Debug Steps

- Run audit:

```bash
php artisan backup:bu-audit
```

- Run a single BU backup with logs:

```bash
php artisan backup:bu-run --bu=<bu_id_or_base_url> --notify=0
```
