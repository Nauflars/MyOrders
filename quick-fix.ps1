#!/usr/bin/env pwsh
# Quick fix script for "File not found" error

Write-Host "=== Quick Fix for MyOrders ===" -ForegroundColor Cyan
Write-Host ""

# Step 1: Ensure public/index.php exists
Write-Host "[1/5] Creating/verifying public/index.php..." -ForegroundColor Yellow
$indexContent = @'
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
'@

New-Item -ItemType Directory -Force -Path "public" | Out-Null
$indexContent | Out-File -FilePath "public/index.php" -Encoding UTF8 -NoNewline
Write-Host "✓ public/index.php created" -ForegroundColor Green
Write-Host ""

# Step 2: Fix nginx configuration
Write-Host "[2/5] Fixing nginx configuration..." -ForegroundColor Yellow
$nginxPath = "docker/nginx/default.conf"
$nginxContent = Get-Content $nginxPath -Raw
if ($nginxContent -match "internal;") {
    $nginxContent = $nginxContent -replace "        # Prevent exposing \.php files in URLs\s+internal;", "        # Don't cache PHP files`n        fastcgi_buffering off;"
    $nginxContent | Out-File -FilePath $nginxPath -Encoding UTF8
    Write-Host "✓ Removed 'internal' directive" -ForegroundColor Green
} else {
    Write-Host "✓ Configuration already fixed" -ForegroundColor Green
}
Write-Host ""

# Step 3: Restart containers
Write-Host "[3/5] Restarting containers..." -ForegroundColor Yellow
docker compose restart nginx php
Start-Sleep -Seconds 5
Write-Host "✓ Containers restarted" -ForegroundColor Green
Write-Host ""

# Step 4: Install dependencies
Write-Host "[4/5] Installing Composer dependencies..." -ForegroundColor Yellow
Write-Host "This may take a few minutes..." -ForegroundColor Gray
$composerOutput = docker compose exec -T php composer install --no-interaction --quiet 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Dependencies installed" -ForegroundColor Green
} else {
    Write-Host "⚠ Composer had some issues, but continuing..." -ForegroundColor Yellow
    Write-Host $composerOutput -ForegroundColor Gray
}
Write-Host ""

# Step 5: Test the site
Write-Host "[5/5] Testing http://localhost..." -ForegroundColor Yellow
Start-Sleep -Seconds 2
try {
    $response = Invoke-WebRequest -Uri "http://localhost" -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop
    Write-Host "✓ SUCCESS! Website is responding" -ForegroundColor Green
    Write-Host "Status Code: $($response.StatusCode)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "You can now open http://localhost in your browser!" -ForegroundColor Green
} catch {
    Write-Host "✗ Still getting error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Let's check the logs:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "=== PHP-FPM Logs ===" -ForegroundColor Cyan
    docker compose logs php --tail 15
    Write-Host ""
    Write-Host "=== Nginx Logs ===" -ForegroundColor Cyan
    docker compose logs nginx --tail 15
    Write-Host ""
    Write-Host "Additional debugging:" -ForegroundColor Yellow
    Write-Host "  docker compose exec php ls -la /var/www/html/public/" -ForegroundColor Gray
    Write-Host "  docker compose exec php cat /var/www/html/public/index.php" -ForegroundColor Gray
}
