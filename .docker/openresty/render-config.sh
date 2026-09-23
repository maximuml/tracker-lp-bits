#!/bin/sh
# Renders nginx vhost configs from templates based on cert availability.
# Called by entrypoint.sh at boot and by cert-watcher.sh when /certs changes
# (e.g. certbot issues or renews a cert, upgrading the site from HTTP to TLS).
set -e

CERT_DIR="/certs"
FULLCHAIN="fullchain.pem"
PRIVATE_KEY="private.key"
USE_HTTPS="1"

if [ -z "$NP_DOMAIN" ]; then
  echo "render-config: NP_DOMAIN is required" >&2
  exit 1
fi

if [ -f "$CERT_DIR/$FULLCHAIN" ] && [ -f "$CERT_DIR/$PRIVATE_KEY" ]; then
    chmod 644 "$CERT_DIR/$FULLCHAIN" "$CERT_DIR/$PRIVATE_KEY" 2>/dev/null || true
else
    USE_HTTPS="0"
fi

echo "render-config: NP_DOMAIN=$NP_DOMAIN USE_HTTPS=$USE_HTTPS NP_PORT=$NP_PORT"

# 组合子域名变量
dot_count_tmp="${NP_DOMAIN//[^.]/}"
dot_count="${#dot_count_tmp}"
PHPMYADMIN="phpmyadmin"
if [ "$dot_count" -eq 1 ]; then
    PHPMYADMIN="${PHPMYADMIN}."
else
    PHPMYADMIN="${PHPMYADMIN}-"
fi
export PHPMYADMIN_SERVER_NAME="${PHPMYADMIN}${NP_DOMAIN}"

APP_CONF="/etc/nginx/conf.d/app.conf"
envsubst '$NP_DOMAIN' < /etc/nginx/conf.d/sites/app.conf.template > "$APP_CONF"

# phpMyAdmin vhost — disabled by default, opt-in via ENABLE_PHPMYADMIN=true
PMA_CONF="/etc/nginx/conf.d/phpmyadmin.conf"
if [ "${ENABLE_PHPMYADMIN:-false}" = "true" ]; then
    envsubst '$PHPMYADMIN_SERVER_NAME' < /etc/nginx/conf.d/sites/phpmyadmin.conf.template > "$PMA_CONF"

    if [ "$USE_HTTPS" = "0" ]; then
        sed -i '/ssl_certificate/d' "$PMA_CONF"
        sed -i '/http2/d' "$PMA_CONF"
        sed -i "s/listen.*/listen $NP_PORT;/g" "$PMA_CONF"
    else
        sed -i "s/listen.*/listen $NP_PORT ssl;/g" "$PMA_CONF"
    fi
else
    rm -f "$PMA_CONF"
fi

# App vhost: the TLS listener is the `listen 443 ssl` line; `listen 80`
# (marked `# acme`) always stays for ACME challenges and, when TLS is on,
# the http→https redirect.
if [ "$USE_HTTPS" = "0" ]; then
    sed -i '/ssl_certificate/d' "$APP_CONF"
    sed -i '/http2/d' "$APP_CONF"
    sed -i '/scheme = http/d' "$APP_CONF"
    sed -i "s/listen 443 ssl;/listen $NP_PORT;/" "$APP_CONF"
    if [ "$NP_PORT" = "80" ]; then
        # `listen 443 ssl` already became `listen 80` — drop the acme duplicate.
        sed -i '/# acme/d' "$APP_CONF"
    fi
else
    sed -i "s/listen 443 ssl;/listen $NP_PORT ssl;/" "$APP_CONF"
    if [ "$NP_PORT" = "80" ]; then
        # TLS lands on :80 — the plain acme listener would collide with it.
        sed -i '/# acme/d' "$APP_CONF"
    fi
fi
