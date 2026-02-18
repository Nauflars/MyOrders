#!/usr/bin/env pwsh
# Quick deployment and testing script for MyOrders

Write-Host "=== MyOrders Deployment Script ===" -ForegroundColor Cyan
Write-Host ""

# Step 1: Check Docker is running
Write-Host "[1/6] Checking Docker..." -ForegroundColor Yellow
try {
    docker info > $null 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: Docker is not running" -ForegroundColor Red
        exit 1
    }
    Write-Host "✓ Docker is running" -ForegroundColor Green
} catch {
    Write-Host "ERROR: Cannot connect to Docker" -ForegroundColor Red
    exit 1
}

# Step 2: Stop existing containers
Write-Host "[2/6] Stopping existing containers..." -ForegroundColor Yellow
docker compose down
Write-Host "✓ Containers stopped" -ForegroundColor Green

# Step 3: Build and start containers
Write-Host "[3/6] Building and starting containers..." -ForegroundColor Yellow
docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    Write-Host "ERROR: Failed to start containers" -ForegroundColor Red
    exit 1
}
Write-Host "✓ Containers started" -ForegroundColor Green

# Step 4: Wait for containers to be healthy
Write-Host "[4/6] Waiting for containers to be ready..." -ForegroundColor Yellow
Start-Sleep -Seconds 10
$containers = docker compose ps --format json | ConvertFrom-Json
$running = ($containers | Where-Object { $_.State -eq "running" }).Count
Write-Host "✓ Running containers: $running" -ForegroundColor Green

# Step 5: Install Composer dependencies
Write-Host "[5/6] Installing Composer dependencies..." -ForegroundColor Yellow
docker compose exec -T php composer install --no-interaction --optimize-autoloader
if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠ Composer install had issues, but continuing..." -ForegroundColor Yellow
} else {
    Write-Host "✓ Dependencies installed" -ForegroundColor Green
}

# Step 6: Test the application
Write-Host "[6/6] Testing application..." -ForegroundColor Yellow
Start-Sleep -Seconds 2
try {
    $response = Invoke-WebRequest -Uri "http://localhost" -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200) {
        Write-Host "✓ Application is accessible at http://localhost" -ForegroundColor Green
        Write-Host ""
        Write-Host "=== SUCCESS ===" -ForegroundColor Green
        Write-Host "Open http://localhost in your browser to see the welcome page!" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "Container status:" -ForegroundColor Yellow
        docker compose ps
    } else {
        Write-Host "⚠ Application responded with status: $($response.StatusCode)" -ForegroundColor Yellow
    }
} catch {
    Write-Host "⚠ Could not reach application at http://localhost" -ForegroundColor Yellow
    Write-Host "Check container logs with: docker compose logs" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Useful commands:" -ForegroundColor Cyan
Write-Host "  docker compose ps              - Check container status" -ForegroundColor Gray
Write-Host "  docker compose logs php        - View PHP logs" -ForegroundColor Gray
Write-Host "  docker compose logs nginx      - View Nginx logs" -ForegroundColor Gray
Write-Host "  docker compose exec php bash   - Enter PHP container" -ForegroundColor Gray
