#!/bin/sh
# Watches /certs for changes (certbot issuance/renewal) and reloads openresty
# after re-rendering the vhost: the first issuance upgrades the config from
# the HTTP fallback to TLS, renewals rotate the key material in place.
# Started in the background by entrypoint.sh before it execs openresty.

CERT_DIR="/certs"
INTERVAL="${CERT_WATCH_INTERVAL:-60}"

fingerprint() {
    md5sum "$CERT_DIR/fullchain.pem" "$CERT_DIR/private.key" 2>/dev/null \
        | md5sum | cut -d' ' -f1
}

prev="$(fingerprint)"

while :; do
    sleep "$INTERVAL"
    cur="$(fingerprint)"
    if [ "$cur" != "$prev" ]; then
        if /usr/local/bin/render-config.sh && openresty -t -q; then
            openresty -s reload && prev="$cur"
            echo "cert-watcher: reloaded openresty after cert change"
        else
            echo "cert-watcher: render/config test failed, retrying" >&2
        fi
    fi
done
