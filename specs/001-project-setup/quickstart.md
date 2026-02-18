# QuickStart Guide: MyOrders Development Environment

**Feature**: 001-project-setup  
**Last Updated**: 2026-02-17  
**Time to Complete**: ~15 minutes (first run), ~30 seconds (subsequent runs)

## Prerequisites

Before starting, ensure you have installed:

- **Docker**: Version 20.10+ ([Install Docker](https://docs.docker.com/get-docker/))
- **Docker Compose**: Version 2.0+ ([Install Docker Compose](https://docs.docker.com/compose/install/))
- **Git**: Version 2.0+ ([Install Git](https://git-scm.com/downloads))

**System Requirements**:
- 4GB RAM minimum (8GB recommended)
- 10GB free disk space
- Available ports: 80, 3306, 5672, 15672, 27017

---

## Quick Start (TL;DR)

```bash
# Clone the repository
git clone <repository-url>
cd MyOrders

# Start all services
docker compose up -d

# Verify containers are running
docker compose ps

# Install Symfony dependencies
docker compose exec php composer install

# Access the application
# Open browser: http://localhost
```

---

## Detailed Setup Instructions

### Step 1: Clone the Repository

```bash
git clone <repository-url>
cd MyOrders
```

### Step 2: Configure Environment Variables

Copy the environment template and review settings:

```bash
cp .env.example .env
```

**Default `.env` values** (for local development):

```env
# Application
APP_ENV=dev
APP_SECRET=change-this-secret-in-production

# Database (MySQL)
DATABASE_URL="mysql://myorders_user:myorders_password@mysql:3306/myorders_db?serverVersion=8.0"

# MongoDB
MONGODB_URL=mongodb://myorders_user:myorders_password@mongodb:27017
MONGODB_DB=myorders

# RabbitMQ
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f

# Docker MySQL (root credentials)
MYSQL_ROOT_PASSWORD=root_password
MYSQL_DATABASE=myorders_db
MYSQL_USER=myorders_user
MYSQL_PASSWORD=myorders_password

# Docker MongoDB
MONGO_INITDB_ROOT_USERNAME=myorders_user
MONGO_INITDB_ROOT_PASSWORD=myorders_password
MONGO_INITDB_DATABASE=myorders

# RabbitMQ
RABBITMQ_DEFAULT_USER=guest
RABBITMQ_DEFAULT_PASS=guest
```

> ⚠️ **Security Note**: These are development defaults. Change all passwords for production environments.

### Step 3: Build and Start Services

Build the Docker images and start all containers:

```bash
docker compose up -d --build
```

**Expected output**:
```
[+] Building 45.2s (12/12) FINISHED
[+] Running 6/6
 ✔ Network myorders_default       Created
 ✔ Container myorders-mysql       Started
 ✔ Container myorders-mongodb     Started
 ✔ Container myorders-rabbitmq    Started
 ✔ Container myorders-php         Started
 ✔ Container myorders-nginx       Started
```

**Wait for services to be healthy** (~30-60 seconds for first run):

```bash
docker compose ps
```

All services should show `running (healthy)` status.

### Step 4: Install PHP Dependencies

Install Symfony and application dependencies via Composer:

```bash
docker compose exec php composer install
```

This will install:
- Symfony 7.4 framework
- Doctrine ORM (MySQL)
- Doctrine MongoDB ODM
- Symfony Messenger
- Twig template engine
- PHPUnit testing framework

**Expected completion time**: 2-3 minutes (first run)

### Step 5: Initialize Databases

Create the MySQL database and schema:

```bash
# Create database
docker compose exec php bin/console doctrine:database:create

# Run migrations (if any exist)
docker compose exec php bin/console doctrine:migrations:migrate --no-interaction
```

MongoDB database is automatically created on first connection (no schema required).

### Step 6: Verify Installation

**Check Symfony environment**:

```bash
docker compose exec php bin/console about
```

**Expected output**:
```
-------------------- -----------------------------------------
 Symfony
-------------------- -----------------------------------------
 Version              7.4.x
 Architecture         64 bits
 PHP                  8.3.x
 Environment          dev
 Debug                true
-------------------- -----------------------------------------
```

**Access the welcome page**:

Open your browser and navigate to: **http://localhost**

You should see:
```
Welcome to MyOrders
```

If you see this, congratulations! Your development environment is running.

### Step 7: Access Service Management Interfaces

**RabbitMQ Management UI**:
- URL: http://localhost:15672
- Username: `guest`
- Password: `guest`

**MySQL Database** (via CLI):
```bash
docker compose exec mysql mysql -u myorders_user -pmyorders_password myorders_db
```

**MongoDB Database** (via CLI):
```bash
docker compose exec mongodb mongosh -u myorders_user -p myorders_password --authenticationDatabase admin myorders
```

---

## Common Commands

### Container Management

```bash
# Start all services
docker compose up -d

# Stop all services
docker compose down

# Restart a specific service
docker compose restart php

# View service logs
docker compose logs -f php

# View all logs
docker compose logs -f

# Check service status
docker compose ps

# Rebuild containers after Dockerfile changes
docker compose up -d --build
```

### Symfony Commands

All Symfony console commands must be executed inside the PHP container:

```bash
# General syntax
docker compose exec php bin/console <command>

# Examples:
docker compose exec php bin/console about
docker compose exec php bin/console cache:clear
docker compose exec php bin/console debug:router
docker compose exec php bin/console debug:container
```

### Database Commands

```bash
# Create database
docker compose exec php bin/console doctrine:database:create

# Drop database (WARNING: deletes all data)
docker compose exec php bin/console doctrine:database:drop --force

# Run migrations
docker compose exec php bin/console doctrine:migrations:migrate

# Generate migration from entities
docker compose exec php bin/console doctrine:migrations:diff
```

### Composer Commands

```bash
# Install dependencies
docker compose exec php composer install

# Update dependencies
docker compose exec php composer update

# Add a new package
docker compose exec php composer require <package-name>

# Validate composer.json
docker compose exec php composer validate
```

### Testing Commands

```bash
# Run all tests
docker compose exec php bin/phpunit

# Run specific test file
docker compose exec php bin/phpunit tests/Unit/ExampleTest.php

# Run with coverage
docker compose exec php bin/phpunit --coverage-html coverage
```

---

## Troubleshooting

### Port Conflicts

**Problem**: Docker reports port already in use

```
Error: bind: address already in use
```

**Solution**: Check which process is using the port and stop it, or modify `docker-compose.yml` to use different ports:

```bash
# Check what's using port 80
# Linux/Mac:
sudo lsof -i :80
# Windows:
netstat -ano | findstr :80

# Modify docker-compose.yml if needed:
services:
  nginx:
    ports:
      - "8080:80"  # Change host port from 80 to 8080
```

### Containers Not Starting

**Problem**: Containers exit immediately or show `Exited` status

**Solution**: Check logs for error messages:

```bash
docker compose logs <service-name>

# Examples:
docker compose logs php
docker compose logs mysql
```

Common issues:
- Missing `xdebug.ini` or PHP configuration (check `docker/php/` directory)
- Incorrect environment variables (verify `.env` file)
- Insufficient system resources (check Docker Desktop settings)

### Database Connection Errors

**Problem**: Symfony reports "Connection refused" to MySQL or MongoDB

**Solution**: 
1. Verify containers are running: `docker compose ps`
2. Check if databases are accepting connections:

```bash
# Test MySQL
docker compose exec php php -r "new PDO('mysql:host=mysql;dbname=myorders_db', 'myorders_user', 'myorders_password');"

# Test MongoDB
docker compose exec php php -r "\$client = new MongoDB\Client('mongodb://myorders_user:myorders_password@mongodb:27017'); var_dump(\$client->listDatabases());"
```

3. Wait longer - databases may still be initializing (check logs)
4. Verify credentials in `.env` match `docker-compose.yml`

### RabbitMQ Connection Errors

**Problem**: Symfony Messenger cannot connect to RabbitMQ

**Solution**:
1. Verify RabbitMQ is running: `docker compose ps rabbitmq`
2. Check RabbitMQ logs: `docker compose logs rabbitmq`
3. Access management UI (http://localhost:15672) to verify server is operational
4. Check `MESSENGER_TRANSPORT_DSN` in `.env` matches credentials

### Permission Issues

**Problem**: Symfony complains about write permissions for `var/cache` or `var/log`

**Solution**: Fix directory permissions:

```bash
# From host machine:
sudo chmod -R 777 var/

# Or from inside container:
docker compose exec php chmod -R 777 var/
```

For persistent fix, ensure your Docker PHP container runs with appropriate UID/GID.

### Composer Install Fails

**Problem**: `composer install` fails with dependency resolution errors

**Solution**:
1. Ensure PHP 8.3 is being used: `docker compose exec php php -v`
2. Clear Composer cache: `docker compose exec php composer clear-cache`
3. Delete `vendor/` and `composer.lock`: `rm -rf vendor composer.lock`
4. Reinstall: `docker compose exec php composer install`

---

## Resetting the Environment

To completely reset your development environment:

```bash
# Stop and remove all containers, networks, and volumes
docker compose down -v

# Remove all unused Docker resources (optional but recommended)
docker system prune -a --volumes

# Restart fresh
docker compose up -d --build
docker compose exec php composer install
docker compose exec php bin/console doctrine:database:create
```

> ⚠️ **Warning**: This will permanently delete all data in MySQL, MongoDB, and RabbitMQ.

---

## Next Steps

Now that your development environment is running:

1. **Explore the project structure**: Review `src/Domain/`, `src/Application/`, `src/Infrastructure/`, `src/UI/` directories
2. **Read the constitution**: Review `.specify/memory/constitution.md` to understand architectural principles
3. **Start building features**: Follow the specification workflow to add new capabilities
4. **Run tests**: Execute `docker compose exec php bin/phpunit` to ensure everything works

---

## Additional Resources

- [Symfony Documentation](https://symfony.com/doc/7.4/index.html)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [RabbitMQ Tutorials](https://www.rabbitmq.com/getstarted.html)
- [DDD with Symfony](https://symfony.com/doc/current/getting_started_with_symfony/ddd.html)

---

**Questions or Issues?**

If you encounter problems not covered in this guide, please:
1. Check Docker and Symfony logs for error messages
2. Review the specification: [spec.md](spec.md)
3. Consult the implementation plan: [plan.md](plan.md)
4. Open an issue in the project repository

---

**Last Updated**: 2026-02-17  
**Tested On**: Docker 24.0.x, Docker Compose 2.x, Ubuntu 20.04, macOS 14, Windows 11 + WSL2
