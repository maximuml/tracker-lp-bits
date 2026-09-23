#!/bin/sh
# Certbot sidecar: issues the initial cert via the ACME webroot that openresty
# serves on :80, installs it into the shared /certs volume, then renews in a
# loop. The cert-watcher inside the openresty container picks up changes and
# reloads nginx.
set -e

: "${NP_DOMAIN:?NP_DOMAIN is required}"
: "${CERTBOT_EMAIL:?CERTBOT_EMAIL is required}"

LE_LIVE="/etc/letsencrypt/live/${NP_DOMAIN}"
STAGING_FLAG=""
if [ "${CERTBOT_STAGING:-no}" = "yes" ]; then
    STAGING_FLAG="--staging"
    echo "certbot: using Let's Encrypt STAGING environment"
fi

install_certs() {
    # -L dereferences the live/ -> archive/ symlinks certbot manages.
    cp -L "$LE_LIVE/fullchain.pem" /certs/fullchain.pem
    cp -L "$LE_LIVE/privkey.pem" /certs/private.key
    chmod 644 /certs/fullchain.pem /certs/private.key
}

if [ ! -f "$LE_LIVE/fullchain.pem" ]; then
    echo "certbot: requesting certificate for ${NP_DOMAIN}"
    # shellcheck disable=SC2086
    certbot certonly --webroot -w /var/www/acme -d "$NP_DOMAIN" \
        --email "$CERTBOT_EMAIL" --agree-tos --no-eff-email \
        --non-interactive $STAGING_FLAG
    install_certs
    echo "certbot: certificate installed into /certs"
else
    echo "certbot: certificate for ${NP_DOMAIN} already present"
fi

trap 'exit 0' TERM INT
echo "certbot: entering renewal loop (checks every 12h)"
while :; do
    # shellcheck disable=SC2086
    certbot renew --non-interactive $STAGING_FLAG \
        --deploy-hook /usr/local/lib/certbot/deploy-hook.sh
    sleep 12h & wait $!
done
