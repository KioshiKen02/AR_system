# BU Backup Restore Guide

This guide describes how to restore a database backup for a specific Business Unit (BU).

## Prerequisites

- You know the BU identifier (`bu_id` or `base_url`) you want to restore.
- You have access to the encrypted backup ZIP file for that BU (local / network disk / S3).
- You have the backup archive password (`BACKUP_ARCHIVE_PASSWORD`) stored securely (password manager / secrets manager).
- You have MySQL client tools available on the restore host:
  - `mysql` and `mysqldump` (or equivalent MariaDB client tools).

## Locate the Backup

By default, backups are created as a single combined ZIP archive containing one SQL dump per BU.

Default on-server path:

- `storage/app/backups/bu-backups/*.zip`

## Decrypt + Extract the SQL

Backups are encrypted ZIP archives when `BACKUP_ARCHIVE_PASSWORD` is configured. Use a tool that supports encrypted ZIP (AES).

### Windows (Recommended: 7‑Zip)

1. Install 7‑Zip.
2. Extract:
   - Right‑click ZIP → 7‑Zip → Extract Here
   - Enter the password when prompted.

### Linux/macOS (7z / unzip)

Prefer `7z` for AES encrypted ZIP:

```bash
7z x -p"$BACKUP_ARCHIVE_PASSWORD" /path/to/backup.zip -o/tmp/bu_restore
```

The SQL dump is typically under:

- `db-dumps/*.sql`

If restoring a specific BU from a combined ZIP, choose the BU-specific dump file:

- `db-dumps/<bu_slug>_bu<bu_id>.sql`

## Restore to a Target Database

Create an empty database, then import the SQL dump.

```bash
mysql -h <HOST> -P <PORT> -u <USER> -e "CREATE DATABASE IF NOT EXISTS <TARGET_DB> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -h <HOST> -P <PORT> -u <USER> <TARGET_DB> < /tmp/bu_restore/db-dumps/<dump>.sql
```

If your MySQL client requires a password:

- Use `MYSQL_PWD` environment variable (avoids leaking password into shell history):

```bash
export MYSQL_PWD="<PASSWORD>"
mysql -h <HOST> -P <PORT> -u <USER> <TARGET_DB> < /tmp/bu_restore/db-dumps/<dump>.sql
unset MYSQL_PWD
```

## Post‑Restore Validation Checklist

- Confirm tables exist:

```bash
mysql -h <HOST> -P <PORT> -u <USER> -e "USE <TARGET_DB>; SHOW TABLES;"
```

- Run a few critical domain checks (examples):
  - row counts for key tables
  - recent transactions exist
  - application login and key reports work against the restored DB

## Automated Validation (ZIP + Encryption + Optional Staging Restore)

Validate ZIP integrity + encryption readability for the latest BU backup:

```bash
php artisan backup:bu-validate --bu=<bu_id_or_base_url> --disk=backups
```

Restore the latest BU backup into a staging server for a smoke test:

1. Configure staging restore credentials in environment variables:
   - `STAGING_DB_HOST`
   - `STAGING_DB_PORT`
   - `STAGING_DB_USERNAME`
   - `STAGING_DB_PASSWORD`
   - Optional: `STAGING_DB_DATABASE` (if omitted, a temporary DB name is generated)

2. Run:

```bash
php artisan backup:bu-validate --bu=<bu_id_or_base_url> --disk=backups --restore=1 --cleanup=1
```
