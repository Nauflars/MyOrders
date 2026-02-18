#!/bin/sh
# Messenger Worker Startup Script
# This script starts Symfony Messenger workers to consume async messages from RabbitMQ

set -e

echo "🚀 Starting Messenger Workers..."
echo "================================"

# Configuration
WORKER_COUNT=${WORKER_COUNT:-2}
TIME_LIMIT=${TIME_LIMIT:-3600}
MEMORY_LIMIT=${MEMORY_LIMIT:-512M}
QUEUE=${QUEUE:-async}

echo "Workers: $WORKER_COUNT"
echo "Time Limit: ${TIME_LIMIT}s"
echo "Memory Limit: $MEMORY_LIMIT"
echo "Queue: $QUEUE"
echo "================================"

# Start workers
php bin/console messenger:consume $QUEUE \
    --time-limit=$TIME_LIMIT \
    --memory-limit=$MEMORY_LIMIT \
    -vv

echo "✅ Worker stopped gracefully"
