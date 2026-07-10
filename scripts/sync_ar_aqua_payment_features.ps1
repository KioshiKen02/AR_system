param(
    [string]$SourceRoot = "C:\laragon\www\ar_system",
    [string]$TargetRoot = "C:\laragon\www\ar_aqua",
    [switch]$RunMigrate,
    [switch]$RunTests
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Assert-Path {
    param(
        [string]$Path,
        [string]$Label
    )

    if (-not (Test-Path $Path)) {
        throw "$Label not found: $Path"
    }
}

function Copy-RelativeFile {
    param([string]$RelativePath)

    $source = Join-Path $SourceRoot $RelativePath
    $target = Join-Path $TargetRoot $RelativePath

    Assert-Path $source "Source file"

    $targetDir = Split-Path $target -Parent
    if (-not (Test-Path $targetDir)) {
        New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
    }

    Copy-Item $source $target -Force
    Write-Host "Copied $RelativePath"
}

function Update-FileContent {
    param(
        [string]$RelativePath,
        [scriptblock]$Transform
    )

    $path = Join-Path $TargetRoot $RelativePath
    Assert-Path $path "Target file"

    $content = Get-Content $path -Raw
    $updated = & $Transform $content

    if ($updated -ne $content) {
        $utf8NoBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($path, $updated, $utf8NoBom)
        Write-Host "Patched $RelativePath"
    }
    else {
        Write-Host "No change for $RelativePath"
    }
}

Assert-Path $SourceRoot "Source root"
Assert-Path $TargetRoot "Target root"

$safeCopyFiles = @(
    "app\Http\Controllers\TransactionControllers\PaymentController.php",
    "app\Http\Controllers\UtilityControllers\CheckClearedController.php",
    "app\Http\Controllers\UtilityControllers\WHTClearedController.php",
    "app\Http\Controllers\UtilityControllers\CancelPaymentController.php",
    "app\Http\Controllers\ReportControllers\CustomerLedgerController.php",
    "app\Http\Controllers\ExportToGLController.php",
    "app\Jobs\GenerateTextFile.php",
    "app\Models\TransactionModels\PaymentDetails.php",
    "app\Models\UtilityModels\WHTClearedItems.php",
    "app\Models\ReportModels\CustomerLedger.php",
    "resources\js\Pages\Payment.vue",
    "resources\js\Pages\CustomerLedger.vue",
    "resources\js\Modals\TransactionModals\AddPayment.vue",
    "resources\js\Modals\TransactionModals\DocumentNumberList.vue",
    "resources\js\Modals\TransactionModals\ViewPayment.vue",
    "resources\js\Modals\UtilityModals\CheckClearing.vue",
    "resources\js\Modals\UtilityModals\WHTClearing.vue",
    "resources\js\Modals\UtilityModals\ViewCheckClearing.vue",
    "database\migrations\2026_04_13_132103_add_wht_status_to_payment_details_table.php",
    "database\migrations\2026_04_13_150000_add_wht_clearing_date_to_payment_details_table.php",
    "database\migrations\2026_06_18_130000_add_overpayment_amount_to_customer_ledger.php",
    "database\migrations\2026_06_18_140000_add_type_to_wht_cleared_items_table.php",
    "database\migrations\2026_06_18_141000_make_wht_no_nullable_in_wht_cleared_items_table.php",
    "database\migrations\2026_06_18_150000_recreate_wht_cleared_tables.php",
    "database\migrations\2026_06_19_120000_add_overpayment_amount_to_payment_details_table.php",
    "database\migrations\2026_06_19_130000_add_floating_deducted_amount_to_payment_details_table.php",
    "database\migrations\2026_06_19_180000_add_allow_overpayment_to_app_settings_table.php",
    "tests\Feature\PaymentWhtLogicTest.php"
)

foreach ($relativePath in $safeCopyFiles) {
    Copy-RelativeFile $relativePath
}

Update-FileContent "app\Models\AppSetting.php" {
    param($content)

    if ($content -notmatch "allow_overpayment") {
        $content = $content -replace "'is_active',\r?\n", "'is_active',`r`n        'allow_overpayment',`r`n"
        $content = $content -replace "'is_active' => 'boolean',\r?\n", "'is_active' => 'boolean',`r`n        'allow_overpayment' => 'boolean',`r`n"
    }

    return $content
}

Update-FileContent "app\Http\Controllers\MasterfileControllers\AppSettingController.php" {
    param($content)

    if ($content -notmatch "allow_overpayment") {
        $content = $content -replace "'is_active' => 'boolean',", "'is_active' => 'boolean',`r`n            'allow_overpayment' => 'boolean',"
    }

    return $content
}

Update-FileContent "app\Http\Middleware\HandleInertiaRequests.php" {
    param($content)

    if ($content -notmatch "use Illuminate\\Support\\Facades\\Schema;") {
        $content = $content -replace "use Illuminate\\Http\\Request;\r?\n", "use Illuminate\Http\Request;`r`nuse Illuminate\Support\Facades\Schema;`r`n"
    }

    if ($content -notmatch "'tenantSettings' => function \(\)") {
        $tenantSettingsBlock = @"
            'tenantSettings' => function () {
                `$appSettingId = config('tenant.current_app_setting_id');

                if (!`$appSettingId) {
                    return [
                        'allow_overpayment' => true,
                    ];
                }

                if (!Schema::connection('mysql')->hasColumn('app_settings', 'allow_overpayment')) {
                    return [
                        'allow_overpayment' => true,
                    ];
                }

                `$setting = AppSetting::on('mysql')
                    ->select('allow_overpayment')
                    ->find(`$appSettingId);

                return [
                    'allow_overpayment' => `$setting?->allow_overpayment ?? true,
                ];
            },
"@
        $content = $content -replace '''theme'' => \$request->user\(\)\?->theme \?\? ''light'',', "'theme' => `$request->user()?->theme ?? 'light',`r`n$tenantSettingsBlock"
    }

    return $content
}

Update-FileContent "resources\js\Modals\MasterfileModals\AppSettingModal.vue" {
    param($content)

    if ($content -notmatch "allow_overpayment") {
        $content = $content -replace "(<div class=""sm:col-span-2"">\s*<label class=""flex items-center space-x-2 cursor-pointer"">\s*<input type=""checkbox"" v-model=""form\.is_active""[\s\S]*?</div>)", "`$1`r`n                        <div class=""sm:col-span-2"">`r`n                            <label class=""flex items-center space-x-2 cursor-pointer"">`r`n                                <input type=""checkbox"" v-model=""form.allow_overpayment"" class=""form-checkbox h-5 w-5 text-[var(--color-primary)] rounded border-[var(--color-border)] bg-[var(--color-bg-primary)] focus:ring-[var(--color-primary)]"">`r`n                                <span class=""text-sm font-medium text-[var(--color-text-primary)]"">Allow Overpayment For This Tenant</span>`r`n                            </label>`r`n                        </div>"
        $content = $content -replace "is_active: true,", "is_active: true,`r`n    allow_overpayment: true,"
        $content = $content -replace "form\.is_active = !!newVal\.is_active;", "form.is_active = !!newVal.is_active;`r`n            form.allow_overpayment = newVal.allow_overpayment ?? true;"
        $content = $content -replace "form\.is_active = true;", "form.is_active = true;`r`n            form.allow_overpayment = true;"
    }

    return $content
}

Update-FileContent "resources\js\Pages\AppSettings.vue" {
    param($content)

    if ($content -notmatch "OVERPAYMENT") {
        $content = $content -replace "<th class=""px-3 py-2 text-center font-semibold tracking-wider"">STATUS</th>", "<th class=""px-3 py-2 text-center font-semibold tracking-wider"">OVERPAYMENT</th>`r`n                        <th class=""px-3 py-2 text-center font-semibold tracking-wider"">STATUS</th>"
        $content = $content -replace "<td class=""px-3 py-2 text-center"">\r?\n\s*<span class=""inline-flex items-center"">", "<td class=""px-3 py-2 text-center"">`r`n                            <span class=""capitalize"">`r`n                                {{ setting.allow_overpayment ? 'Allowed' : 'Blocked' }}`r`n                            </span>`r`n                        </td>`r`n                        <td class=""px-3 py-2 text-center"">`r`n                            <span class=""inline-flex items-center"">"
        $content = $content -replace 'colspan="5"', 'colspan="6"'
    }

    return $content
}

Update-FileContent "routes\web.php" {
    param($content)

    if ($content -notmatch "checkclearing\.applyLedger") {
        $content = $content -replace "Route::post\('/check-clearing', \[CheckClearedController::class, 'clearChecks'\]\)->name\('checkclearing'\);", "Route::post('/check-clearing', [CheckClearedController::class, 'clearChecks'])->name('checkclearing');`r`n        Route::post('/check-clearing/{clearing_no}/apply-ledger', [CheckClearedController::class, 'applyToLedger'])`r`n            ->middleware('check.permission:0401-CHKCLR,update')`r`n            ->name('checkclearing.applyLedger');"
    }

    return $content
}

Push-Location $TargetRoot
try {
    if (Test-Path ".\artisan") {
        try {
            php artisan ziggy:generate resources/js/ziggy.js | Out-Null
            Write-Host "Regenerated Ziggy routes"
        }
        catch {
            Write-Warning "Unable to regenerate Ziggy routes automatically. Run 'php artisan ziggy:generate resources/js/ziggy.js' manually if needed."
        }

        if ($RunMigrate) {
            php artisan migrate
        }

        if ($RunTests) {
            php artisan test --filter=PaymentWhtLogicTest
        }
    }
}
finally {
    Pop-Location
}

Write-Host ""
Write-Host "Sync complete."
Write-Host "Source: $SourceRoot"
Write-Host "Target: $TargetRoot"
