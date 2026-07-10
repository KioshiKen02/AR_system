# Payment Handling Alignment Guide

## Scope

This document explains the current `ar_system` handling for:

- overpayment
- WHT application and WHT clearing
- check clearing
- cancelled payments
- payment export behavior that depends on those values

It also documents what is missing in `C:\laragon\www\ar_aqua` and the safest order for aligning it.

## Current `ar_system` behavior

### 1. Payment entry and WHT application

Main logic:

- `app/Http/Controllers/TransactionControllers/PaymentController.php:33-120`
- `app/Http/Controllers/TransactionControllers/PaymentController.php:643-1164`

Key rules:

- WHT is now handled inside the normal payment types `5A - Cash`, `5B - Journal Voucher`, `5C - Online Deposit`, and `5D - Check`.
- The older dedicated `5E - Creditable(WHT)` flow is no longer the source of truth.
- `resolveWhtStatus()` sets WHT to:
  - `Cleared` when `apply_bir_2307 = true`
  - `Floating` when `apply_bir_2307 = false`
- For single-use document types, WHT can only be applied once per document unless the old WHT row was cancelled.
- `ensureOverpaymentAllowed()` blocks positive overpayment when tenant setting `app_settings.allow_overpayment` is false.

Stored values:

- `payment.amount_paid` = gross applied amount (`net + wht`)
- `payment.total_amount_less_wht` = net cash portion
- `payment_details.amount_paid` = gross applied amount
- `payment_details.wht_amount` = WHT portion
- `payment_details.wht_status` = `Floating` or `Cleared`
- `payment_details.floating_deducted_amount` = amount already reserved by floating transactions
- `payment_details.overpayment_amount` = overflow beyond available document balance
- `customer_ledger.overpayment_amount` = document-level overpayment snapshot

Important design detail:

- Ledger `running_balance` is reduced only by the net collectible payment during initial payment posting.
- WHT becomes part of the ledger credit only when it is already cleared, or when WHT clearing later marks it cleared.

### 2. Check clearing process

Main logic:

- `app/Http/Controllers/UtilityControllers/CheckClearedController.php:26-178`
- `app/Http/Controllers/UtilityControllers/CheckClearedController.php:233-347`
- route: `routes/web.php:273-279`

Process flow:

1. User fetches floating checks from `getFloatingChecks`.
2. User posts `/check-clearing`.
3. Matching `payment_details` row is updated from `Floating` to the selected clearing status.
4. If cleared, `syncLedgerForPayment()` recomputes the ledger from all surviving payment rows for that document.
5. If cancelled during clearing, advance payment is returned to the customer.

Why "check clearing uses net amount excluding WHT":

- `getEffectivePaidAmount()` subtracts `wht_amount` from `amount_paid` before adding to ledger paid amount.
- `totalWhtApplied` is recomputed separately and written to `customer_ledger.wht_amount`.
- Result:
  - `customer_ledger.amount_paid` reflects the net collectible payment only
  - `customer_ledger.wht_amount` carries the WHT credit separately

This is the current confirmed rule for the project.

### 3. WHT clearing process

Main logic:

- `app/Http/Controllers/UtilityControllers/WHTClearedController.php:26-160`
- `app/Http/Controllers/UtilityControllers/WHTClearedController.php:203-336`
- `app/Http/Controllers/ReportControllers/CustomerLedgerController.php` diff shows the floating-WHT source was updated
- route: `routes/web.php:286-290`

Process flow:

1. User fetches floating WHT rows from `getFloatingWht`.
2. User posts `/wht-clearing`.
3. The system writes a `wht_cleared` header plus `wht_cleared_items`.
4. The target `payment_details` row is found by `payment_no + type + document_no` where either:
   - `status = Floating`, or
   - `wht_status = Floating`
5. If `wht_status` was floating and is being cleared:
   - `payment_details.wht_status` becomes `Cleared`
   - `payment_details.wht_clearing_date` is stored when available
   - the payment detail gross `amount_paid` is topped up when needed
   - the parent `payment.amount_paid` is also increased by the WHT amount when needed
6. `syncLedgerForPayment()` recalculates:
   - net paid amount
   - cleared WHT amount
   - running balance
   - overpayment

Why this matters:

- WHT can float independently from the cash portion.
- Clearing WHT must not just change a status flag; it must also bring payment totals and ledger credits back into agreement.

### 4. Cancelled payments

Main logic:

- `app/Http/Controllers/UtilityControllers/CancelPaymentController.php:22-155`
- `app/Http/Controllers/UtilityControllers/CancelPaymentController.php:177-378`
- route: `routes/web.php:295-302`

Supported cancellation entry points:

- cancel by document number
- cancel by payment number

Process flow:

1. Insert audit trail into:
   - `cancelled_payments`
   - `cancelled_payment_items`
2. Mark each affected `payment_details` row as:
   - `status = Cancelled`
   - `wht_status = Cancelled` when column exists
   - `remarks = Cancelled`
3. Recompute the ledger using only non-cancelled rows.
4. Return any `advpy_amount_paid` to `customer.advanced_payment_balance`.
5. Recompute `payment_details.overpayment_amount` for the remaining rows.

Important behavior:

- Cancellation is not just a status update.
- The ledger, WHT totals, overpayment values, and advance payment balance are all re-synced.

### 5. Payment export to GL / text file

Main logic:

- `app/Jobs/GenerateTextFile.php:525-770`
- `app/Jobs/GenerateTextFile.php:1154-1724`

Current export rules:

- Floating checks are skipped.
- Floating WHT is skipped.
- Export only marks a payment as exported when at least one detail line was actually exported.
- WHT is exported inline inside each payment-mode generator, not through the old standalone `5E` flow.

Accounting mappings:

- WHT receivable line uses `10.07.01.01`
- overpayment line uses `90.02.09` with description `Cash Overage`

Overpayment export behavior:

- The customer detail line is reduced by `overpayment_amount`
- a separate balancing G/L line is created for `90.02.09`

This is the project-confirmed handling for overpayments.

## Current regression protection

Feature tests exist in `ar_system`:

- `tests/Feature/PaymentWhtLogicTest.php:227-335`
- `tests/Feature/PaymentWhtLogicTest.php:338-439`
- `tests/Feature/PaymentWhtLogicTest.php:442-520`
- `tests/Feature/PaymentWhtLogicTest.php:523-620`

Covered scenarios:

- WHT is stored as net + WHT correctly
- `apply_bir_2307 = false` keeps WHT floating
- floating WHT retrieval uses `wht_status`
- WHT clearing updates both payment header/detail totals

`ar_aqua` currently has no matching `PaymentWhtLogicTest.php`.

## `ar_aqua` gap analysis

### A. Schema gaps

Present in `ar_system` but missing in `ar_aqua` migrations:

- `2026_04_13_132103_add_wht_status_to_payment_details_table.php`
- `2026_04_13_150000_add_wht_clearing_date_to_payment_details_table.php`
- `2026_06_18_130000_add_overpayment_amount_to_customer_ledger.php`
- `2026_06_18_140000_add_type_to_wht_cleared_items_table.php`
- `2026_06_18_141000_make_wht_no_nullable_in_wht_cleared_items_table.php`
- `2026_06_18_150000_recreate_wht_cleared_tables.php`
- `2026_06_19_120000_add_overpayment_amount_to_payment_details_table.php`
- `2026_06_19_130000_add_floating_deducted_amount_to_payment_details_table.php`
- `2026_06_19_180000_add_allow_overpayment_to_app_settings_table.php`

Impact:

- without these columns, `ar_aqua` cannot safely support the newer WHT lifecycle, overpayment tracking, or tenant-level overpayment control

### B. Backend code gaps

Files that are materially behind in `ar_aqua`:

- `app/Http/Controllers/TransactionControllers/PaymentController.php`
- `app/Http/Controllers/UtilityControllers/CheckClearedController.php`
- `app/Http/Controllers/UtilityControllers/WHTClearedController.php`
- `app/Http/Controllers/UtilityControllers/CancelPaymentController.php`
- `app/Http/Controllers/ReportControllers/CustomerLedgerController.php`
- `app/Jobs/GenerateTextFile.php`
- `app/Models/TransactionModels/PaymentDetails.php`
- `app/Models/UtilityModels/WHTClearedItems.php`

Important observed differences:

- `ar_aqua` still contains the old dedicated `5E - Creditable(WHT)` payment handling path.
- `ar_aqua` does not yet persist `wht_status`, `floating_deducted_amount`, or `overpayment_amount` in `PaymentDetails`.
- `ar_aqua` check clearing lacks the ledger re-apply endpoint that exists in `ar_system`.
- `ar_aqua` ledger reporting still mixes floating and cleared behavior differently than `ar_system`.
- `ar_aqua` export logic does not yet match the newer overpayment and floating-WHT rules from `ar_system`.

### C. Model mismatch that should be fixed during porting

In `ar_aqua`:

- `app/Models/UtilityModels/WHTClearedItems.php` still relates using `clearing_no`
- current `ar_system` correctly relates using `wht_clearing_no`
- current `ar_system` also includes `type` in fillable fields

This is a quiet but important mismatch because the newer WHT clearing flow stores item type and expects the correct relation key.

### D. Route gap

Current route only in `ar_system`:

- `routes/web.php:276-278`
- `/check-clearing/{clearing_no}/apply-ledger`

`ar_aqua` does not currently expose this repair/re-apply route.

## Recommended implementation order for `ar_aqua`

### Phase 1: Schema first

Port the missing migrations from `ar_system` to `ar_aqua` before touching controller logic.

Minimum required columns:

- `payment_details.wht_status`
- `payment_details.wht_clearing_date`
- `payment_details.overpayment_amount`
- `payment_details.floating_deducted_amount`
- `customer_ledger.overpayment_amount`
- `app_settings.allow_overpayment`
- `wht_cleared_items.type`
- `wht_cleared_items.wht_no` nullable

### Phase 2: Port backend logic

Port these files from `ar_system` to `ar_aqua` and then re-check tenant-specific customizations:

- `app/Http/Controllers/TransactionControllers/PaymentController.php`
- `app/Http/Controllers/UtilityControllers/CheckClearedController.php`
- `app/Http/Controllers/UtilityControllers/WHTClearedController.php`
- `app/Http/Controllers/UtilityControllers/CancelPaymentController.php`
- `app/Http/Controllers/ReportControllers/CustomerLedgerController.php`
- `app/Jobs/GenerateTextFile.php`
- `app/Models/TransactionModels/PaymentDetails.php`
- `app/Models/UtilityModels/WHTClearedItems.php`

### Phase 3: Routes

Add the missing route:

- `POST /check-clearing/{clearing_no}/apply-ledger`

Keep the existing WHT clearing and cancel payment routes, but make sure they now point to the updated controller behavior.

### Phase 4: Tests

Copy and adapt:

- `tests/Feature/PaymentWhtLogicTest.php`

This should be treated as mandatory, not optional, because the risky part of this alignment is not the UI; it is the accounting math and lifecycle transitions.

### Phase 5: Verification scenarios

After porting to `ar_aqua`, verify these cases manually and by test:

1. Cash payment with WHT cleared immediately
2. Cash payment with WHT left floating
3. WHT clearing of a previously floating payment
4. Check payment cleared later
5. Check payment cancellation
6. Payment with overpayment and export to GL
7. Cancelled payment after WHT existed

Expected results:

- ledger `amount_paid` is net only
- ledger `wht_amount` is tracked separately
- ledger `running_balance` reaches zero only after net + cleared WHT fully cover debit
- overpayment goes to `payment_details.overpayment_amount`, `customer_ledger.overpayment_amount`, and GL `90.02.09`
- floating items never export to GL

## Recommended alignment direction

Align `ar_aqua` to `ar_system`, not the other way around.

Reason:

- `ar_system` already contains the newer WHT status lifecycle, overpayment tracking, cancellation re-sync, export handling, and feature tests.
- Porting the older `ar_aqua` behavior back would reintroduce accounting inconsistencies that `ar_system` has already solved.

## Summary

If you want `ar_aqua` to behave like the current project for overpayment, WHT clearing, and cancelled payments, the implementation is not a single controller copy. It is a package of:

- schema changes
- payment posting logic
- clearing logic
- cancellation logic
- customer ledger reporting logic
- export logic
- model updates
- regression tests

That full package already exists in `ar_system` and should be used as the source of truth.
