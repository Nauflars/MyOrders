#!/usr/bin/env pwsh
# Diagnostic script for MyOrders "File not found" issue

Write-Host "=== MyOrders Diagnostic Script ===" -ForegroundColor Cyan
Write-Host ""

# Check 1: Docker containers
Write-Host "[1/8] Checking Docker containers..." -ForegroundColor Yellow
$containers = docker compose ps --format json 2>&1 | ConvertFrom-Json
if ($containers) {
    $running = ($containers | Where-Object { $_.State -eq "running" }).Count
    Write-Host "✓ Running containers: $running/5" -ForegroundColor Green
    docker compose ps
} else {
    Write-Host "✗ Cannot read container status" -ForegroundColor Red
}
Write-Host ""

# Check 2: public/index.php exists
Write-Host "[2/8] Checking public/index.php..." -ForegroundColor Yellow
if (Test-Path "public/index.php") {
    $size = (Get-Item "public/index.php").Length
    Write-Host "✓ File exists ($size bytes)" -ForegroundColor Green
    Get-Content "public/index.php" -Head 3
} else {
    Write-Host "✗ File NOT found" -ForegroundColor Red
    Write-Host "Creating public/index.php..." -ForegroundColor Yellow
    @"
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array `$context) {
    return new Kernel(`$context['APP_ENV'], (bool) `$context['APP_DEBUG']);
};
"@ | Out-File -FilePath "public/index.php" -Encoding UTF8 -NoNewline
    Write-Host "✓ Created" -ForegroundColor Green
}
Write-Host ""

# Check 3: vendor directory
Write-Host "[3/8] Checking vendor directory..." -ForegroundColor Yellow
if (Test-Path "vendor/autoload_runtime.php") {
    Write-Host "✓ Composer dependencies installed" -ForegroundColor Green
} else {
    Write-Host "✗ Dependencies NOT installed" -ForegroundColor Red
    Write-Host "Installing dependencies..." -ForegroundColor Yellow
    docker compose exec -T php composer install --no-interaction
}
Write-Host ""

# Check 4: Nginx configuration
Write-Host "[4/8] Checking nginx configuration..." -ForegroundColor Yellow
$nginxConfig = Get-Content "docker/nginx/default.conf" -Raw
if ($nginxConfig -match "internal;") {
    Write-Host "⚠ Found 'internal;' directive - this blocks external access" -ForegroundColor Yellow
    Write-Host "Fixing configuration..." -ForegroundColor Yellow
    $nginxConfig = $nginxConfig -replace "        # Prevent exposing \.php files in URLs\s+internal;", "        # Don't cache PHP files`n        fastcgi_buffering off;"
    $nginxConfig | Out-File -FilePath "docker/nginx/default.conf" -Encoding UTF8
    Write-Host "✓ Fixed - restarting nginx..." -ForegroundColor Green
    docker compose restart nginx
    Start-Sleep -Seconds 3
} else {
    Write-Host "✓ Configuration looks good" -ForegroundColor Green
}
Write-Host ""

# Check 5: PHP container response
Write-Host "[5/8] Checking PHP container..." -ForegroundColor Yellow
$phpVersion = docker compose exec -T php php -v 2>&1 | Select-String "PHP 8" | Select-Object -First 1
if ($phpVersion) {
    Write-Host "✓ PHP is running: $phpVersion" -ForegroundColor Green
} else {
    Write-Host "✗ PHP container not responding" -ForegroundColor Red
}
Write-Host ""

# Check 6: File permissions inside container
Write-Host "[6/8] Checking file permissions in container..." -ForegroundColor Yellow
$perms = docker compose exec -T php ls -la /var/www/html/public/index.php 2>&1
if ($perms -match "index.php") {
    Write-Host "✓ File accessible in container" -ForegroundColor Green
    Write-Host $perms
} else {
    Write-Host "✗ File not found in container" -ForegroundColor Red
    Write-Host "This suggests volume mounting issue"
}
Write-Host ""

# Check 7: Nginx logs
Write-Host "[7/8] Checking nginx error logs..." -ForegroundColor Yellow
docker compose logs nginx --tail 10 2>&1 | Write-Host
Write-Host ""

# Check 8: HTTP test
Write-Host "[8/8] Testing HTTP request..." -ForegroundColor Yellow
try {
    $response = Invoke-WebRequest -Uri "http://localhost" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
    Write-Host "✓ SUCCESS! Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "Content preview:" -ForegroundColor Cyan
    $response.Content.Substring(0, [Math]::Min(200, $response.Content.Length))
} catch {
    Write-Host "✗ FAILED: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        Write-Host "Status Code: $($_.Exception.Response.StatusCode)" -ForegroundColor Yellow
    }
}
Write-Host ""

Write-Host "=== Summary ===" -ForegroundColor Cyan
Write-Host "If still not working, try:" -ForegroundColor Yellow
Write-Host "  1. docker compose restart" -ForegroundColor Gray
Write-Host "  2. docker compose logs php" -ForegroundColor Gray
Write-Host "  3. docker compose logs nginx" -ForegroundColor Gray
Write-Host "  4. docker compose exec php ls -la /var/www/html/public/" -ForegroundColor Gray
Write-Host ""
