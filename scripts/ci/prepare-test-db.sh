#!/usr/bin/env bash
# Wait for the MySQL service container, migrate the seed database and
# clone schema + seed rows into each named test database.
#
# Usage: prepare-test-db.sh <db> [<db> ...]
#   e.g. prepare-test-db.sh nexusphp_unit_testing nexusphp_feature_testing
#
# Test DB names must carry the "testing" marker required by
# DestructiveEnvironmentGuard.
set -euo pipefail

for i in $(seq 1 60); do
    nc -z 127.0.0.1 3306 && break
    sleep 1
done
nc -z 127.0.0.1 3306 || { echo "MySQL did not become ready in time"; exit 1; }

php artisan migrate:fresh --seed --force

# Seed rows the suites depend on: settings/users — config + auth,
# categories/forums — FK targets and inner joins,
# searchbox/searchbox_fields — browse mode,
# agent_allowed_* — announce client check.
SEED_TABLES="settings users categories forums searchbox searchbox_fields agent_allowed_family agent_allowed_exception"

for db in "$@"; do
    mysql -h 127.0.0.1 -u root -proot -e "
        CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        GRANT ALL PRIVILEGES ON $db.* TO 'nexusphp'@'%';
        FLUSH PRIVILEGES;
    "
    mysqldump -h 127.0.0.1 -u root -proot --no-data --skip-comments --set-gtid-purged=OFF --single-transaction nexusphp | mysql -h 127.0.0.1 -u root -proot "$db"
    mysqldump -h 127.0.0.1 -u root -proot --no-create-info --skip-comments --set-gtid-purged=OFF --single-transaction nexusphp $SEED_TABLES | mysql -h 127.0.0.1 -u root -proot "$db"
done
