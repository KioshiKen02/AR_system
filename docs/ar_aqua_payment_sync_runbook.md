# AR Aqua Payment Sync Runbook

## Why this exists

The current workspace can inspect `C:\laragon\www\ar_aqua` but cannot write to it directly.
To keep the implementation moving, the payment-alignment changes were packaged into a local script in `ar_system`.

## Script

`[scripts/sync_ar_aqua_payment_features.ps1](file:///c:/laragon/www/ar_system/scripts/sync_ar_aqua_payment_features.ps1)`

## What it does

It syncs the `ar_system` implementation for:

- payment posting with inline WHT handling
- WHT clearing
- check clearing
- cancelled payments
- overpayment tracking
- payment export logic
- payment-related UI files
- required migrations
- the `PaymentWhtLogicTest`

It also applies targeted patches in `ar_aqua` for:

- `AppSetting` model support for `allow_overpayment`
- `HandleInertiaRequests` tenant setting sharing
- app-setting controller validation
- app-setting UI toggle
- check-clearing apply-to-ledger route

## How to run

From PowerShell:

```powershell
pwsh -NoProfile -ExecutionPolicy Bypass -File "C:\laragon\www\ar_system\scripts\sync_ar_aqua_payment_features.ps1"
```

To also run migrations and the WHT regression test:

```powershell
pwsh -NoProfile -ExecutionPolicy Bypass -File "C:\laragon\www\ar_system\scripts\sync_ar_aqua_payment_features.ps1" -RunMigrate -RunTests
```

## Recommended follow-up

After the script finishes:

1. Open `ar_aqua`
2. Smoke test:
   - Payment entry with WHT
   - WHT clearing
   - Check clearing
   - Cancel payment
   - Export to GL for payment
3. Review the app-setting toggle for overpayment
