#!/usr/bin/env bash
# Prepare .env for CI jobs backed by GitHub service containers
# (mysql/redis on 127.0.0.1). Used by unit-tests, coverage, migrations,
# infection.
set -euo pipefail

cp .env.example .env
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=nexusphp/' .env
sed -i 's/^CACHE_DRIVER=.*/CACHE_DRIVER=array/' .env
sed -i 's/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/' .env
sed -i 's/^CHANNEL_NAME_SETTING=.*/CHANNEL_NAME_SETTING=/' .env
sed -i 's/^CHANNEL_NAME_MODEL_EVENT=.*/CHANNEL_NAME_MODEL_EVENT=/' .env
sed -i 's/^REDIS_PASSWORD=.*/REDIS_PASSWORD=/' .env
# Redis isolation: use DB index 15 for tests to avoid clobbering
sed -i 's/^REDIS_DB=.*/REDIS_DB=15/' .env
# MeiliSearch isolation: disable Scout for unit/coverage tests
echo "SCOUT_DRIVER=null" >> .env

# Optional extras used by the mutation-testing job.
if [ "${1:-}" = "--with-key" ]; then
    php artisan key:generate
    composer run-script post-autoload-dump
fi
