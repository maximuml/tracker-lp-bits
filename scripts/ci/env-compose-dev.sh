#!/usr/bin/env bash
# Prepare .env for CI jobs that run the dev docker-compose stack
# (smoke-test, backup-restore).
set -euo pipefail

cp .env.example .env
sed -i 's|DB_HOST=127.0.0.1|DB_HOST=mysql|g' .env
sed -i 's|REDIS_HOST=127.0.0.1|REDIS_HOST=redis|g' .env
sed -i 's|LOG_FILE=.*|LOG_FILE=php://stdout|g' .env
# .env.example uses a placeholder DB_PASSWORD — set the real value
# to match the MySQL service container.
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=nexusphp/' .env
# .env.example leaves MEILISEARCH_MASTER_KEY empty so users must set
# their own. For the smoke-test stack, restore the compose default.
sed -i 's|^MEILISEARCH_MASTER_KEY=.*|MEILISEARCH_MASTER_KEY=nexusphp_default_key|' .env
# Use an isolated E2E testing database so Feature tests (including
# CriticalPathTest) never mutate the dev/production database.
# The MySQL container creates this DB automatically via
# MYSQL_DATABASE in docker-compose.yml. The name carries the
# "testing" marker required by DestructiveEnvironmentGuard.
sed -i 's/^DB_DATABASE=.*/DB_DATABASE=nexusphp_e2e_testing/' .env
# Use dev overrides (bind-mounts, openresty config mounts)
echo "COMPOSE_FILE=docker-compose.yml:docker-compose.dev.yml" >> "$GITHUB_ENV"
