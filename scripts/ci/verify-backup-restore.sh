#!/usr/bin/env bash
# W8-03: backup/restore verification.
#
# Runs against a live stack (dev bind-mounts or prod named volumes):
#   1. create sentinel files in torrents/ attachments/ bitbucket/
#   2. run `php artisan backup:all --method=tar` (the production code path:
#      web-root tar + mysqldump bundled into one archive)
#   3. verify the dump restores into a scratch DB via `backup:restore-drill
#      --compare` (per-table row counts vs the live source)
#   4. snapshot CHECKSUM TABLE + sha256 manifest of the data directories
#   5. DESTROY: drop the database, delete all data-dir contents
#   6. restore from the backup bundle (sql + web tar member extraction)
#   7. verify: checksums match, data-dir manifest matches, HTTP smoke passes
#
# Required env: DB_DATABASE, DB_USERNAME, DB_PASSWORD (sourced from .env when
# run locally). Docker compose services needed: mysql, php, openresty.
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ -f .env ]; then
    set -a; # shellcheck disable=SC1091
    . ./.env
    set +a
fi
DB="${DB_DATABASE:?DB_DATABASE is required}"
DB_USER="${DB_USERNAME:-nexusphp}"
DB_PASS="${DB_PASSWORD:?DB_PASSWORD is required}"
WORK=/tmp/w8-03-backup-restore
DRILL_DB="nexusphp_restore_test"

dc() { docker compose "$@"; }
php_sh() { dc exec -T php sh -c "$1"; }
mysql_root() { dc exec -T mysql mysql -uroot -p"$DB_PASS" "$@"; }

LAST_TS=$SECONDS
step() { local now=$SECONDS; echo; echo "=== $* (+$((now - LAST_TS))s) ==="; LAST_TS=$now; }
fail() { echo "FAIL: $*" >&2; exit 1; }

rm -rf "$WORK"; mkdir -p "$WORK"
trap 'rm -rf "$WORK"; php_sh "rm -rf /tmp/w8-03-r" >/dev/null 2>&1 || true' EXIT

step "1/8 Create sentinel files in data directories"
php_sh 'echo "w8-03-sentinel-torrent" > torrents/w8_03_sentinel.torrent
        echo "w8-03-sentinel-attachment" > attachments/w8_03_sentinel.txt
        echo "w8-03-sentinel-bitbucket" > bitbucket/w8_03_sentinel.bin'

step "2/8 Run backup:all (production code path)"
dc exec -T php php artisan backup:all --method=tar
BUNDLE=$(php_sh 'ls -t storage/app/backups/*.tar.gz 2>/dev/null | grep -v "\.web\." | head -1' | tr -d '\r')
[ -n "$BUNDLE" ] || fail "backup:all produced no bundle archive"
echo "Bundle: $BUNDLE"

step "3/8 Extract bundle and restore-drill the SQL dump"
php_sh "rm -rf /tmp/w8-03-r && mkdir -p /tmp/w8-03-r && tar -xzf '$BUNDLE' -C /tmp/w8-03-r"
SQL_FILE=$(php_sh 'ls /tmp/w8-03-r/*.database.*.sql | head -1' | tr -d '\r')
WEB_TAR=$(php_sh 'ls /tmp/w8-03-r/*.web.*.tar.gz | head -1' | tr -d '\r')
[ -n "$SQL_FILE" ] && [ -n "$WEB_TAR" ] || fail "bundle missing sql or web tar member"
echo "SQL: $SQL_FILE"; echo "Web: $WEB_TAR"
# The drill needs a scratch database; the app user only has rights on $DB.
mysql_root -e "DROP DATABASE IF EXISTS \`$DRILL_DB\`; CREATE DATABASE \`$DRILL_DB\`;
               GRANT ALL PRIVILEGES ON \`nexusphp\_restore\_test\`.* TO '$DB_USER'@'%'; FLUSH PRIVILEGES;"
dc exec -T php php artisan backup:restore-drill --file="$SQL_FILE" --test-db="$DRILL_DB" --compare

step "4/8 Snapshot integrity manifests (post-backup, pre-destroy)"
php_sh 'find torrents attachments bitbucket -type f -exec sha256sum {} + | sort -k2' > "$WORK/files_pre.sha256"
wc -l < "$WORK/files_pre.sha256" | xargs echo "Files captured:"
TABLES=$(mysql_root -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE'" | tr -d '\r' | sed 's/.*/`&`/' | paste -sd, -)
[ -n "$TABLES" ] || fail "no tables found in $DB"
mysql_root "$DB" -e "CHECKSUM TABLE $TABLES" | tr -d '\r' | sort > "$WORK/db_pre.checksum"
wc -l < "$WORK/db_pre.checksum" | xargs echo "Tables checksummed:"

step "5/8 DESTROY: drop database and wipe data directories"
mysql_root -e "DROP DATABASE \`$DB\`; CREATE DATABASE \`$DB\`"
php_sh 'find torrents attachments bitbucket -mindepth 1 -delete'
php_sh 'find torrents attachments bitbucket -mindepth 1 | wc -l' | tr -d '\r' | grep -q '^0$' \
    || fail "data directories not empty after wipe"

step "6/8 Restore database dump and data directories from bundle"
php_sh "cat '$SQL_FILE'" | dc exec -T mysql mysql -uroot -p"$DB_PASS" "$DB"
php_sh "tar -xzf '$WEB_TAR' -C /var/www html/torrents html/attachments html/bitbucket"

step "7/8 Verify checksums and file manifest"
php_sh 'find torrents attachments bitbucket -type f -exec sha256sum {} + | sort -k2' > "$WORK/files_post.sha256"
diff "$WORK/files_pre.sha256" "$WORK/files_post.sha256" && echo "FILES_PARITY_OK"
mysql_root "$DB" -e "CHECKSUM TABLE $TABLES" | tr -d '\r' | sort > "$WORK/db_post.checksum"
diff "$WORK/db_pre.checksum" "$WORK/db_post.checksum" && echo "DB_CHECKSUM_OK"
grep -q 'w8_03_sentinel' "$WORK/files_post.sha256" || fail "sentinel files missing after restore"

step "8/8 Critical smoke"
for page in /index /login /torrents; do
    curl -sfL --max-time 15 "http://localhost$page" -o /dev/null || fail "page $page not responding after restore"
    echo "OK: $page"
done

echo
echo "BACKUP_RESTORE_VERIFY_OK"
