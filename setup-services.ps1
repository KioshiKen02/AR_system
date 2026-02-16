# Laravel Windows Services Setup Script
# Run this script as Administrator

$phpPath = Read-Host "Enter the full path to your PHP executable (e.g., C:\laragon\bin\php\php-8.2.4\php.exe)"
if (-not (Test-Path $phpPath)) {
    Write-Error "PHP path not found! Please check the path and try again."
    exit
}

$projectRoot = Get-Location
Write-Host "Project Root: $projectRoot" -ForegroundColor Cyan

# 1. Install Reverb Service
Write-Host "Setting up bilar_reverb..." -ForegroundColor Green
nssm install bilar_reverb "$phpPath" "artisan reverb:start --host=0.0.0.0 --port=8081"
nssm set bilar_reverb AppDirectory "$projectRoot"
nssm start bilar_reverb

# 2. Install Queue Service
Write-Host "Setting up bilar_queue..." -ForegroundColor Green
nssm install bilar_queue "$phpPath" "artisan queue:work --sleep=3 --tries=3 --timeout=600"
nssm set bilar_queue AppDirectory "$projectRoot"
nssm start bilar_queue

Write-Host "Services installed and started successfully!" -ForegroundColor Cyan
Write-Host "Note: You still need to manually set up the Windows Task Scheduler for the 'artisan schedule:run' command." -ForegroundColor Yellow
