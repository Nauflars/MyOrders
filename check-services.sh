#!/usr/bin/env bash
# Service connectivity verification script
# Checks MySQL, MongoDB, and RabbitMQ connectivity

set -e

echo "=== MyOrders Service Connectivity Check ==="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# MySQL Check
echo -n "Checking MySQL... "
if docker compose exec -T mysql mysqladmin ping -h localhost -u root -proot_password &> /dev/null; then
    echo -e "${GREEN}✓ Connected${NC}"
    echo "  - Host: mysql:3306"
    echo "  - Database: myorders_db"
else
    echo -e "${RED}✗ Failed${NC}"
fi

echo ""

# MongoDB Check
echo -n "Checking MongoDB... "
if docker compose exec -T mongodb mongosh --quiet --eval "db.adminCommand('ping')" &> /dev/null; then
    echo -e "${GREEN}✓ Connected${NC}"
    echo "  - Host: mongodb:27017"
    echo "  - Database: myorders"
else
    echo -e "${RED}✗ Failed${NC}"
fi

echo ""

# RabbitMQ Check
echo -n "Checking RabbitMQ... "
if docker compose exec -T rabbitmq rabbitmqctl status &> /dev/null; then
    echo -e "${GREEN}✓ Running${NC}"
    echo "  - AMQP: rabbitmq:5672"
    echo "  - Management: http://localhost:15672"
    echo "  - Credentials: guest/guest"
else
    echo -e "${RED}✗ Failed${NC}"
fi

echo ""
echo "=== PHP Container Check ==="
echo -n "Checking PHP extensions... "
docker compose exec -T php php -m | grep -E "pdo_mysql|opcache" > /dev/null
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Installed${NC}"
    docker compose exec -T php php -m | grep -E "pdo_mysql|opcache|mongodb|amqp" | sed 's/^/  - /'
else
    echo -e "${RED}✗ Missing extensions${NC}"
fi

echo ""
echo "=== Symfony Console Check ==="
if docker compose exec -T php test -f bin/console; then
    echo -e "${GREEN}✓ Console available${NC}"
    echo ""
    echo "Try: docker compose exec php bin/console list"
else
    echo -e "${RED}✗ Console not found${NC}"
fi

echo ""
echo "=== Summary ==="
echo "To install dependencies: docker compose exec php composer install"
echo "To create database: docker compose exec php bin/console doctrine:database:create"
echo "To run migrations: docker compose exec php bin/console doctrine:migrations:migrate"
echo "To check Symfony: docker compose exec php bin/console about"
