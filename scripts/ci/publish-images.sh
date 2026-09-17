#!/usr/bin/env bash
# Build + push the production php/openresty images to GHCR and export
# their digests to $GITHUB_OUTPUT/$GITHUB_ENV for the cosign sign +
# attestation steps that follow.
set -euo pipefail

NS=$(echo "$IMAGE_NS" | tr '[:upper:]' '[:lower:]')
SHORT=${GITHUB_SHA::7}
OCI_LABELS="--label org.opencontainers.image.source=https://github.com/${GITHUB_REPOSITORY} \
            --label org.opencontainers.image.revision=${GITHUB_SHA}"

docker build -f .docker/php/Dockerfile.prod \
    --build-arg NEXUSPHP_ENV=prod \
    $OCI_LABELS \
    -t "${NS}/php:sha-${SHORT}" -t "${NS}/php:latest" .

docker build -f .docker/openresty/Dockerfile \
    $OCI_LABELS \
    -t "${NS}/openresty:sha-${SHORT}" -t "${NS}/openresty:latest" .

docker push "${NS}/php:sha-${SHORT}"
docker push "${NS}/php:latest"
docker push "${NS}/openresty:sha-${SHORT}"
docker push "${NS}/openresty:latest"

# RepoDigests on the local image is populated by `docker push`.
PHP_DIGEST=$(docker inspect --format='{{index .RepoDigests 0}}' "${NS}/php:sha-${SHORT}" | cut -d@ -f2)
OPENRESTY_DIGEST=$(docker inspect --format='{{index .RepoDigests 0}}' "${NS}/openresty:sha-${SHORT}" | cut -d@ -f2)
[ -n "$PHP_DIGEST" ] && [ -n "$OPENRESTY_DIGEST" ] || { echo "digest resolution failed"; exit 1; }
echo "php-digest=${PHP_DIGEST}" >> "$GITHUB_OUTPUT"
echo "openresty-digest=${OPENRESTY_DIGEST}" >> "$GITHUB_OUTPUT"
echo "image-ns=${NS}" >> "$GITHUB_OUTPUT"
echo "PHP_IMAGE_REF=${NS}/php@${PHP_DIGEST}" >> "$GITHUB_ENV"
echo "OPENRESTY_IMAGE_REF=${NS}/openresty@${OPENRESTY_DIGEST}" >> "$GITHUB_ENV"
