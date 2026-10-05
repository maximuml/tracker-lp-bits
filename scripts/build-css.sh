#!/bin/sh
# Build public/css/nxt.css from resources/css/nxt.css with the standalone
# Tailwind CLI (no Node toolchain). Fetches a pinned binary into bin/ on
# first use; re-runs are offline.
set -eu

VERSION="4.3.3"
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
BIN_DIR="$ROOT/bin"
INPUT="$ROOT/resources/css/nxt.css"
OUTPUT="$ROOT/public/css/nxt.css"

case "$(uname -m)" in
    x86_64|amd64) arch="x64" ;;
    aarch64|arm64) arch="arm64" ;;
    *) echo "unsupported arch: $(uname -m)" >&2; exit 1 ;;
esac

if ldd --version 2>&1 | grep -qi musl; then
    target="linux-${arch}-musl"
else
    target="linux-${arch}"
fi

BIN="$BIN_DIR/tailwindcss-${target}-v${VERSION}"

if [ ! -x "$BIN" ]; then
    mkdir -p "$BIN_DIR"
    base="https://github.com/tailwindlabs/tailwindcss/releases/download/v${VERSION}"
    tmp="$(mktemp -d)"
    trap 'rm -rf "$tmp"' EXIT
    curl -fsSL "$base/tailwindcss-${target}" -o "$tmp/tailwindcss"
    curl -fsSL "$base/sha256sums.txt" -o "$tmp/sha256sums.txt"
    expected="$(grep "tailwindcss-${target}\$" "$tmp/sha256sums.txt" | awk '{print $1}')"
    actual="$(sha256sum "$tmp/tailwindcss" | awk '{print $1}')"
    if [ "$expected" != "$actual" ]; then
        echo "checksum mismatch for tailwindcss-${target}" >&2
        exit 1
    fi
    mv "$tmp/tailwindcss" "$BIN"
    chmod +x "$BIN"
fi

mkdir -p "$(dirname "$OUTPUT")"
"$BIN" -i "$INPUT" -o "$OUTPUT" --minify "$@"
