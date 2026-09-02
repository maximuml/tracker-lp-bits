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
    ${ROOT_PATH}/storage/app ${ROOT_PATH}/bootstrap/cache

# Writable dirs are volumes — chmod is safe (we're www-data or root)
if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data ${ROOT_PATH}/attachments ${ROOT_PATH}/torrents \
      ${ROOT_PATH}/storage ${ROOT_PATH}/bootstrap/cache
  chmod -R 775 ${ROOT_PATH}/storage ${ROOT_PATH}/bootstrap/cache \
      ${ROOT_PATH}/attachments ${ROOT_PATH}/torrents
fi

if [ "$SERVICE_NAME" = "php" ]; then
    # Laravel caches are pre-baked at build time, but storage:link needs the
    # storage volume to exist. Re-run safe-to-repeat commands.
    echo_info "Linking storage + warming caches..."
    php artisan storage:link --force 2>/dev/null || true

    # Re-cache config/routes/views in case .env changed at runtime
    # (these write to bootstrap/cache which is a writable volume)
    php artisan config:cache 2>/dev/null || true
    php artisan route:cache 2>/dev/null || true
    php artisan view:cache 2>/dev/null || true
    php artisan icons:cache 2>/dev/null || true
    php artisan filament:cache-components 2>/dev/null || true
    echo_success "Caches warmed."

    exec php-fpm

elif [ "$SERVICE_NAME" = "queue" ]; then
    echo_info "Start Queue Worker..."
    exec php artisan horizon

elif [ "$SERVICE_NAME" = "scheduler" ]; then
    echo_info "Start Scheduler..."
    while true; do
        echo_success "[Scheduler] Running schedule:run at $(date '+%Y-%m-%d %H:%M:%S')"
        php artisan schedule:run --verbose --no-interaction 2>&1 || true
        sleep 60
    done

else
    echo_error "Unknown SERVICE_NAME: $SERVICE_NAME, exiting."
    exit 1
fi
