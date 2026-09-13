#!/usr/bin/env bash
# Post-migration verification for the previous-release → HEAD upgrade test.
#
# Usage: verify-migration-upgrade.sh <upgraded_db> <fresh_db>
#
# Checks, in order:
#   1. every migration file is recorded as Ran in the upgraded database;
#   2. sentinel fixture rows were transformed correctly by data migrations
#      (enum → tinyint/boolean conversions, orphan cleanup, partition backfill);
#   3. the upgraded schema is identical to a fresh HEAD schema
#      (order-insensitive: column order may legitimately differ).
#
# Requires the mysql client; reads MYSQL_* env vars with CI defaults.

set -euo pipefail

UPGRADED_DB="${1:?upgraded database name required}"
FRESH_DB="${2:?fresh reference database name required}"

MYSQL="mysql -h ${MYSQL_HOST:-127.0.0.1} -u ${MYSQL_USER:-root} -p${MYSQL_PASSWORD:-root} -N -B"

fail() { echo "::error::$1"; exit 1; }

# ── 1. every migration file Ran ───────────────────────────────────────────────
$MYSQL -e "SELECT migration FROM \`$UPGRADED_DB\`.migrations" | sort > /tmp/ran_migrations.txt
ls database/migrations/*.php | xargs -n1 basename | sed 's/\.php$//' | sort > /tmp/wanted_migrations.txt
MISSING=$(comm -23 /tmp/wanted_migrations.txt /tmp/ran_migrations.txt | wc -l)
[ "$MISSING" = "0" ] || { comm -23 /tmp/wanted_migrations.txt /tmp/ran_migrations.txt; fail "$MISSING migration files not recorded as ran"; }
echo "OK: all $(wc -l < /tmp/wanted_migrations.txt) migration files ran"

# ── 2. sentinel data assertions ───────────────────────────────────────────────
check() { # $1 = description, $2 = sql, $3 = expected
    local got
    got=$($MYSQL -e "$2" "$UPGRADED_DB" | tail -1)
    [ "$got" = "$3" ] || fail "$1: expected '$3', got '$got'"
    echo "OK: $1 = $got"
}

check "users.enabled ('yes'→1)" \
    "SELECT enabled FROM users WHERE id=10001" "1"
check "users.status ('confirmed'→1)" \
    "SELECT status FROM users WHERE id=10001" "1"
check "users.privacy ('low'→2)" \
    "SELECT privacy FROM users WHERE id=10001" "2"
check "users.gender ('Male'→0)" \
    "SELECT gender FROM users WHERE id=10001" "0"
check "messages.unread ('yes'→1)" \
    "SELECT unread FROM messages WHERE subject='fixture'" "1"
check "messages.saved ('yes'→1)" \
    "SELECT saved FROM messages WHERE subject='fixture'" "1"
check "messages.sender (0→NULL, system messages)" \
    "SELECT IFNULL(sender,'~NULL~') FROM messages WHERE subject='fixture'" "~NULL~"
check "topics.locked ('yes'→1)" \
    "SELECT locked FROM topics WHERE id=1" "1"
check "topics.sticky ('yes'→1)" \
    "SELECT sticky FROM topics WHERE id=1" "1"
check "torrents.type ('multi'→1)" \
    "SELECT type FROM torrents WHERE id=1" "1"
check "torrents.visible ('yes'→1)" \
    "SELECT visible FROM torrents WHERE id=1" "1"
check "snatched.finished ('yes'→1)" \
    "SELECT finished FROM snatched WHERE userid=10001" "1"
check "peers.seeder ('yes'→1)" \
    "SELECT seeder FROM peers WHERE userid=10001" "1"
check "agent_allowed_family.agent_matchtype ('hex'→1)" \
    "SELECT agent_matchtype FROM agent_allowed_family LIMIT 1" "1"
check "user partition backfill (user_preferences rows = users rows)" \
    "SELECT (SELECT COUNT(*) FROM user_preferences) = (SELECT COUNT(*) FROM users)" "1"
check "user partition backfill (user_activity rows = users rows)" \
    "SELECT (SELECT COUNT(*) FROM user_activity) = (SELECT COUNT(*) FROM users)" "1"
check "activity_log.attribute_changes exists" \
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$UPGRADED_DB' AND TABLE_NAME='activity_log' AND COLUMN_NAME='attribute_changes'" "1"
check "activity_log.batch_uuid removed (activitylog v5)" \
    "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$UPGRADED_DB' AND TABLE_NAME='activity_log' AND COLUMN_NAME='batch_uuid'" "0"
check "no orphan peers after FK creation" \
    "SELECT COUNT(*) FROM peers WHERE torrent NOT IN (SELECT id FROM torrents)" "0"

# ── 3. schema parity: upgraded == fresh ──────────────────────────────────────
dump_cols() {
    $MYSQL -e "
        SELECT CONCAT(TABLE_NAME,'|',COLUMN_NAME,'|',COLUMN_TYPE,'|',
                      IS_NULLABLE,'|',IFNULL(COLUMN_DEFAULT,'~'))
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA='$1' ORDER BY TABLE_NAME, COLUMN_NAME"
}
dump_idx() {
    $MYSQL -e "
        SELECT CONCAT(TABLE_NAME,'|',INDEX_NAME,'|',COLUMN_NAME,'|',
                      NON_UNIQUE,'|',IFNULL(SUB_PART,0))
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA='$1'
        ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
}
dump_fk() {
    $MYSQL -e "
        SELECT CONCAT(k.TABLE_NAME,'|',k.CONSTRAINT_NAME,'|',k.COLUMN_NAME,'|',
                      k.REFERENCED_TABLE_NAME,'|',k.REFERENCED_COLUMN_NAME,'|',
                      r.UPDATE_RULE,'|',r.DELETE_RULE)
        FROM information_schema.KEY_COLUMN_USAGE k
        JOIN information_schema.REFERENTIAL_CONSTRAINTS r
          ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
         AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         AND r.TABLE_NAME = k.TABLE_NAME
        WHERE k.CONSTRAINT_SCHEMA='$1' AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION"
}
dump_tables() {
    $MYSQL -e "
        SELECT CONCAT(TABLE_NAME,'|',ENGINE)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA='$1' ORDER BY TABLE_NAME"
}

for what in cols idx fk tables; do
    dump_$what "$UPGRADED_DB" > /tmp/parity_upgraded_$what.txt
    dump_$what "$FRESH_DB"    > /tmp/parity_fresh_$what.txt
    if ! diff -u /tmp/parity_fresh_$what.txt /tmp/parity_upgraded_$what.txt > /tmp/parity_diff_$what.txt; then
        cat /tmp/parity_diff_$what.txt
        fail "schema parity check failed for $what (upgraded vs fresh differ)"
    fi
    echo "OK: $what parity ($(wc -l < /tmp/parity_fresh_$what.txt) entries)"
done

echo "All migration-upgrade checks passed."
