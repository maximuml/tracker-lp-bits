#!/usr/bin/env bash
# W8-05: verify a GHCR image's cosign keyless signature before deploying it.
#
# Usage:
#   scripts/verify-image.sh ghcr.io/<owner>/<repo>/php@sha256:<digest>
#   scripts/verify-image.sh ghcr.io/<owner>/<repo>/openresty:sha-abc1234
#
# The signature is expected from this repo's ci.yml publish job on php8
# (keyless OIDC identity, issuer https://token.actions.githubusercontent.com).
# Requires cosign >= 2.4 on PATH. Override NP_REPO for forks.
#
# On success the last line prints the verified digest reference:
#   VERIFIED_REF=ghcr.io/<owner>/<repo>/php@sha256:<digest>
# Deploy by THAT reference — a tag can be re-pointed after signing, a
# digest cannot lie about the bytes it names.
set -euo pipefail

IMAGE="${1:?usage: verify-image.sh <image-ref>[:tag|@digest]}"
REPO="${NP_REPO:-maximuml/tracker-lp-bits}"
WORKFLOW="ci.yml"
BRANCH="php8"

command -v cosign >/dev/null || {
    echo "cosign not found — install: https://docs.sigstore.dev/cosign/system_config/installation/" >&2
    exit 2
}

# Escape regex-special chars in repo name for the identity regexp.
REPO_RE=$(printf '%s' "$REPO" | sed 's/[.[\*^$]/\\&/g')

JSON=$(cosign verify "$IMAGE" \
    --certificate-identity-regexp "^https://github\.com/${REPO_RE}/\.github/workflows/${WORKFLOW}@refs/heads/${BRANCH}$" \
    --certificate-oidc-issuer "https://token.actions.githubusercontent.com" \
    -o json 2>/dev/null) || {
    echo "VERIFY_IMAGE_FAILED: $IMAGE" >&2
    exit 1
}

REF=$(printf '%s' "$JSON" | grep -oE '"docker-reference": *"[^"]+"' | head -1 | cut -d'"' -f4)
[ -n "$REF" ] || { echo "VERIFY_IMAGE_FAILED: no docker-reference in payload" >&2; exit 1; }

echo "VERIFY_IMAGE_OK: $IMAGE"
echo "VERIFIED_REF=$REF"
