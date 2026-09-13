#!/bin/sh
# Production entrypoint — vendor and assets are pre-baked in the image.
# No composer install, no install-script copy. Only waits for deps and starts the service.

set -e

COLOR_RED='\033[0;31m'
COLOR_GREEN='\033[0;32m'
COLOR_YELLOW='\033[1;33m'
COLOR_BLUE='\033[0;34m'
COLOR_RESET='\033[0m'

echo_info()    { echo -e "${COLOR_BLUE}[INFO]${COLOR_RESET} $*"; }
echo_success() { echo -e "${COLOR_GREEN}[SUCCESS]${COLOR_RESET} $*"; }
echo_warn()    { echo -e "${COLOR_YELLOW}[WARN]${COLOR_RESET} $*"; }
echo_error()   { echo -e "${COLOR_RED}[ERROR]${COLOR_RESET} $*"; }

wait_for_service() {
  name="$1"
  host="$2"
  port="$3"
  maxWaitSeconds="$4"
  waited=0

  echo_info "Checking $name at $host:$port..."

  until nc -z "$host" "$port" >/dev/null 2>&1; do
    if [ "$waited" -ge "$maxWaitSeconds" ]; then
      echo_error "$name not available after ${maxWaitSeconds}s. Exiting."
      exit 1
    fi
    echo_info "Waiting for $name... (${waited}s elapsed)"
    sleep 2
    waited=$((waited + 2))
  done

  echo_success "$name is available."
}

# Only wait for deps if relevant (php needs both; queue/scheduler need both)
wait_for_service "MySQL" mysql 3306 30
wait_for_service "Redis" redis 6379 30

echo_info "Starting container for SERVICE_NAME=$SERVICE_NAME..."

ROOT_PATH="/var/www/html"

# Ensure writable directories exist (these are mounted as volumes in prod)
mkdir -p ${ROOT_PATH}/attachments ${ROOT_PATH}/torrents \
    ${ROOT_PATH}/storage/framework/views ${ROOT_PATH}/storage/logs \
    ${ROOT_PATH}/storage/framework/sessions ${ROOT_PATH}/storage/framework/cache/data \
    ${ROOT_PATH}/storage/app/public ${ROOT_PATH}/bootstrap/cache

# Writable dirs are volumes — chmod is safe (we're www-data or root)
if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data ${ROOT_PATH}/attachments ${ROOT_PATH}/torrents \
      ${ROOT_PATH}/storage ${ROOT_PATH}/bootstrap/cache
  chmod -R 775 ${ROOT_PATH}/storage ${ROOT_PATH}/bootstrap/cache \
      ${ROOT_PATH}/attachments ${ROOT_PATH}/torrents
fi

if [ "$SERVICE_NAME" = "php" ]; then
    # Drop any cached config inherited from the image or a previous
    # release's volume: while bootstrap/cache/config.php exists Laravel
    # skips .env entirely (configurationIsCached short-circuits
    # LoadEnvironmentVariables), so a stale cache would mask runtime
    # secrets. Only the php service does this — it is the one that
    # re-warms the shared bootstrap-cache volume right after; letting
    # queue/scheduler delete it too raced a cache file they never rebuild.
    rm -f ${ROOT_PATH}/bootstrap/cache/config.php

    # T-14: Validate production config before starting — fail fast if
    # APP_KEY is missing, APP_DEBUG is on, or writable paths are broken.
    echo_info "Validating production configuration..."
    php artisan app:validate-production
    echo_success "Production config validated."

    # Laravel caches are pre-baked at build time, but storage:link and cache
    # warming need to run after volumes are mounted.
    # T-14: No `|| true` — if cache warming fails, the container should
    # not start with stale/missing caches.
    # Note: storage:link is NOT re-run here because the symlink is baked
    # into the image at build time and the rootfs is read-only in prod.
    # The storage/app/public directory is created by mkdir above.
    echo_info "Warming caches..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan icons:cache
    php artisan filament:cache-components
    echo_success "Caches warmed."

    exec php-fpm

elif [ "$SERVICE_NAME" = "queue" ]; then
    # T-14: Validate production config before starting queue worker.
    echo_info "Validating production configuration..."
    php artisan app:validate-production
    echo_success "Production config validated."

    echo_info "Start Queue Worker..."
    exec php artisan horizon

elif [ "$SERVICE_NAME" = "scheduler" ]; then
    # T-14: Validate production config before starting scheduler.
    echo_info "Validating production configuration..."
    php artisan app:validate-production
    echo_success "Production config validated."

    echo_info "Start Scheduler..."
    # exec + schedule:work = foreground minute-ticker as PID 1, so SIGTERM
    # stops the scheduler between ticks instead of SIGKILLing a mid-flight
    # schedule:run inside a shell wrapper (which ignores signals as init).
    exec php artisan schedule:work --verbose --no-interaction

else
    echo_error "Unknown SERVICE_NAME: $SERVICE_NAME, exiting."
    exit 1
fi
