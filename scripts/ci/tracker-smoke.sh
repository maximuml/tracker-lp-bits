#!/usr/bin/env bash
# Announce/scrape smoke against a running server (Octane/RoadRunner in CI).
# Interleaves two users and a bad passkey so per-request user/torrent state
# that leaks across worker requests shows up as a wrong verdict.
#
# Usage: scripts/ci/tracker-smoke.sh <base-url> [php-cmd]
#   php-cmd defaults to "php"; locally e.g. "docker compose exec -T php php".
set -euo pipefail

BASE_URL=${1:?base url required}
PHP=${2:-php}
UA="qBittorrent/4.5.2"

# shellcheck disable=SC2086
FIXTURE=$($PHP artisan tinker --execute='
$a = App\Models\User::factory()->create();
$b = App\Models\User::factory()->create();
$t = App\Models\Torrent::factory()->owner($a)->create();
echo "FIXTURE ".$a->passkey." ".$b->passkey." ".rawurlencode($t->info_hash).PHP_EOL;
' | grep '^FIXTURE ' | tr -d '\r')
read -r _ PASSKEY_A PASSKEY_B INFO_HASH <<<"$FIXTURE"
[ -n "${INFO_HASH:-}" ] || { echo "FAIL: could not create fixture"; exit 1; }

announce() { # passkey peer-id-suffix event
  curl -sS --max-time 10 -H "User-Agent: $UA" \
    "$BASE_URL/announce?passkey=$1&info_hash=$INFO_HASH&peer_id=-qB4520-$2&port=51413&uploaded=0&downloaded=0&left=100&compact=1&event=$3" | tr -d '\0'
}

expect_ok() { # label body
  case "$2" in
    d*8:intervali*) ;;
    *) echo "FAIL: $1: not a bencoded announce dict: $2"; exit 1 ;;
  esac
  case "$2" in
    *"failure reason"*|*"warning message"*) echo "FAIL: $1: $2"; exit 1 ;;
  esac
  echo "ok: $1"
}

expect_reject() { # label body
  case "$2" in
    *"failure reason"*|*"warning message"*) echo "ok: $1" ;;
    *) echo "FAIL: $1: bad passkey was accepted: $2"; exit 1 ;;
  esac
}

expect_ok "user A started" "$(announce "$PASSKEY_A" AAAAAAAAAAAA started)"
expect_reject "bad passkey after A" "$(announce 0123456789abcdef0123456789abcdef CCCCCCCCCCCC started)"
expect_ok "user B started" "$(announce "$PASSKEY_B" BBBBBBBBBBBB started)"
expect_reject "bad passkey after B" "$(announce 0123456789abcdef0123456789abcdef DDDDDDDDDDDD started)"

SCRAPE=$(curl -sS --max-time 10 -H "User-Agent: $UA" "$BASE_URL/scrape?passkey=$PASSKEY_B&info_hash=$INFO_HASH")
case "$SCRAPE" in
  d5:filesd20:*8:completei*) echo "ok: scrape" ;;
  *) echo "FAIL: scrape: $SCRAPE"; exit 1 ;;
esac

expect_ok "user A stopped" "$(announce "$PASSKEY_A" AAAAAAAAAAAA stopped)"
expect_ok "user B stopped" "$(announce "$PASSKEY_B" BBBBBBBBBBBB stopped)"
echo "Tracker smoke passed"
