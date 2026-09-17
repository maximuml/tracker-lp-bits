#!/usr/bin/env bash
# Prepare .env for the production-compose smoke test
# (docker-compose.prod.yml, APP_ENV=production).
set -euo pipefail

cp .env.example .env
sed -i 's|DB_HOST=127.0.0.1|DB_HOST=mysql|g' .env
sed -i 's|REDIS_HOST=127.0.0.1|REDIS_HOST=redis|g' .env
sed -i 's|LOG_FILE=.*|LOG_FILE=php://stdout|g' .env
# The prod stack asserts a non-default DB password.
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=prod_test_strong_pass_2026/' .env
sed -i 's|^MEILISEARCH_MASTER_KEY=.*|MEILISEARCH_MASTER_KEY=nexusphp_default_key|' .env
sed -i 's/^DB_DATABASE=.*/DB_DATABASE=nexusphp_e2e_testing/' .env
# Production-shaped runtime settings.
sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env
sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env
sed -i 's/^SESSION_SECURE_COOKIE=.*/SESSION_SECURE_COOKIE=true/' .env
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
echo "COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml" >> "$GITHUB_ENV"
