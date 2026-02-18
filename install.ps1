#!/usr/bin/env pwsh
# Complete installation script

$ErrorActionPreference = "Continue"

Write-Host "=== MyOrders Complete Installation ===" -ForegroundColor Cyan
Write-Host "Time: $(Get-Date -Format 'HH:mm:ss')" -ForegroundColor Gray
Write-Host ""

# Step 1: Check containers
Write-Host "[1/6] Checking Docker containers..." -ForegroundColor Yellow
$containerCheck = docker ps --filter "name=myorders" --format "{{.Names}}" 2>&1
if ($containerCheck -match "myorders-php") {
    Write-Host "✓ Containers are running" -ForegroundColor Green
    docker ps --filter "name=myorders" --format "table {{.Names}}\t{{.Status}}"
} else {
    Write-Host "✗ Containers not running" -ForegroundColor Red
    Write-Host "Starting containers..." -ForegroundColor Yellow
    docker compose up -d 2>&1 | Out-Null
    Start-Sleep -Seconds 10
}
Write-Host ""

# Step 2: Create public/index.php in WSL
Write-Host "[2/6] Creating public/index.php..." -ForegroundColor Yellow
wsl -d Ubuntu-20.04 bash -c @"
cd /var/www2/MyOrders
mkdir -p public
cat > public/index.php << 'PHPEOF'
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array \`$context) {
    return new Kernel(\`$context['APP_ENV'], (bool) \`$context['APP_DEBUG']);
};
PHPEOF
chmod 644 public/index.php
ls -lh public/index.php
"@ 2>&1
Write-Host "✓ index.php created" -ForegroundColor Green
Write-Host ""

# Step 3: Fix nginx config
Write-Host "[3/6] Checking nginx config..." -ForegroundColor Yellow
$nginxConf = Get-Content "docker/nginx/default.conf" -Raw
if ($nginxConf -match "internal;") {
    Write-Host "Fixing nginx configuration..." -ForegroundColor Yellow
    $nginxConf -replace "        # Prevent exposing \.php files in URLs\s+internal;", "        fastcgi_buffering off;" | 
        Set-Content "docker/nginx/default.conf" -NoNewline
    docker compose restart nginx 2>&1 | Out-Null
    Start-Sleep -Seconds 3
    Write-Host "✓ Nginx config fixed and restarted" -ForegroundColor Green
} else {
    Write-Host "✓ Config OK" -ForegroundColor Green
}
Write-Host ""

# Step 4: Run composer install
Write-Host "[4/6] Installing Composer dependencies..." -ForegroundColor Yellow
Write-Host "This will take 2-3 minutes..." -ForegroundColor Gray
$composerStart = Get-Date
docker compose exec -T php composer install --no-interaction --no-progress 2>&1 | Tee-Object -Variable composerOutput | Out-Null
$composerDuration = ((Get-Date) - $composerStart).TotalSeconds
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ Dependencies installed in $([Math]::Round($composerDuration, 1))s" -ForegroundColor Green
} else {
    Write-Host "✗ Composer failed. Output:" -ForegroundColor Red
    $composerOutput | Select-Object -Last 20 | Write-Host
}
Write-Host ""

# Step 5: Verify vendor directory
Write-Host "[5/6] Verifying vendor directory..." -ForegroundColor Yellow
$vendorCheck = wsl -d Ubuntu-20.04 bash -c "cd /var/www2/MyOrders && ls -la vendor/ 2>&1 | head -5"
if ($vendorCheck -match "autoload") {
    Write-Host "✓ Vendor directory exists" -ForegroundColor Green
    Write-Host $vendorCheck
} else {
    Write-Host "✗ Vendor directory not found" -ForegroundColor Red
}
Write-Host ""

# Step 6: Test the application
Write-Host "[6/6] Testing application..." -ForegroundColor Yellow
Start-Sleep -Seconds 2
try {
    $response = Invoke-WebRequest -Uri "http://localhost" -TimeoutSec 10 -UseBasicParsing -ErrorAction Stop
    Write-Host "✓ SUCCESS!" -ForegroundColor Green
    Write-Host "Status: $($response.StatusCode)" -ForegroundColor Cyan
    Write-Host "Content length: $($response.Content.Length) bytes" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Preview:" -ForegroundColor Cyan
    $response.Content.Substring(0, [Math]::Min(300, $response.Content.Length))
} catch {
    Write-Host "✗ Error: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host ""
    Write-Host "Checking logs..." -ForegroundColor Yellow
    Write-Host "--- PHP Logs ---" -ForegroundColor Cyan
    docker compose logs php --tail 10
    Write-Host ""
    Write-Host "--- Nginx Logs ---" -ForegroundColor Cyan
    docker compose logs nginx --tail 10
}

Write-Host ""
Write-Host "=== Installation Complete ===" -ForegroundColor Cyan
Write-Host "Open http://localhost in your browser" -ForegroundColor Green
