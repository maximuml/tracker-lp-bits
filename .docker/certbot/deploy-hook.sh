#!/bin/sh
# Runs after every successful renewal: refreshes the copies openresty reads.
set -e

LE_LIVE="/etc/letsencrypt/live/${NP_DOMAIN:?NP_DOMAIN is required}"

cp -L "$LE_LIVE/fullchain.pem" /certs/fullchain.pem
cp -L "$LE_LIVE/privkey.pem" /certs/private.key
chmod 644 /certs/fullchain.pem /certs/private.key

echo "certbot deploy-hook: refreshed /certs for ${NP_DOMAIN}"
