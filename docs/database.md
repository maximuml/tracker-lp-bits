# Database Architecture

## Overview

The tracker uses MySQL 8.0+ as its primary database, with Redis 7 for
caching and queue management. The database configuration supports
read/write splitting for horizontal scaling.

## Connection Configuration

### Read/Write Split

The MySQL connection in `config/database.php` is configured with
separate read and write host arrays:

```php
'mysql' => [
    // ... common settings ...

    'read' => [
        'host' => [env('DB_READ_HOST', env('DB_HOST', '127.0.0.1'))],
    ],
    'write' => [
        'host' => [env('DB_HOST', '127.0.0.1'))],
    ],
    'sticky' => true,
],
```

### Environment Variables

| Variable | Purpose | Default |
|---|---|---|
| `DB_HOST` | Write host (primary) | `127.0.0.1` |
| `DB_READ_HOST` | Read host (replica) | Falls back to `DB_HOST` |
| `DB_PORT` | MySQL port | `3306` |
| `DB_DATABASE` | Database name | `nexusphp` |
| `DB_USERNAME` | MySQL user | `root` |
| `DB_PASSWORD` | MySQL password | (empty) |

### Sticky Reads

The `sticky` option is enabled (`true`). This means that any read
operation performed in the same request **after** a write operation
will be routed to the **write** connection (primary), not the read
replica. This prevents replication lag from causing stale reads
immediately after writes.

Example:
```php
// Write goes to primary
DB::table('users')->update(['class' => 5]);

// This read goes to primary (sticky), not replica
$user = DB::table('users')->where('id', 1)->first();
// $user->class will be 5, not a stale value
```

### Multiple Read Replicas

The `host` key accepts an array. Laravel will randomly select one host
from the array for each read connection:

```php
'read' => [
    'host' => [
        'read1.example.com',
        'read2.example.com',
        'read3.example.com',
    ],
],
```

### Setting Up a Read Replica

1. Configure MySQL replication (primary → replica).
2. Set `DB_READ_HOST` in your `.env` to the replica hostname:
   ```
   DB_HOST=primary.example.com
   DB_READ_HOST=replica.example.com
   ```
3. Ensure the replica has the same database user/password as the primary.
4. Verify with `php artisan tinker`:
   ```php
   DB::connection()->getReadPdo(); // connects to replica
   DB::connection()->getPdo();     // connects to primary
   ```

## Test Databases

Tests use isolated databases to prevent accidental data loss:

| Database | Suite | CI Job |
|---|---|---|
| `nexusphp_unit_testing` | Unit | `unit-tests`, `coverage`, `octane` |
| `nexusphp_feature_testing` | Feature (no OpenResty) | `coverage` |
| `nexusphp_e2e_testing` | Feature + OpenResty | `smoke-test`, `a11y`, `perf-budget` |

The `DestructiveEnvironmentGuard` prevents destructive operations
(`migrate:fresh`, `db:wipe`, etc.) on non-testing databases.

## Query Budgets

W3-05 established query-count budgets for key pages to catch N+1
regressions:

| Page | Budget | Test |
|---|---|---|
| `/index` | ≤ 25 queries | `QueryBudgetTest::test_index_page_query_budget` |
| `/torrents` | ≤ 15 queries | `QueryBudgetTest::test_torrents_listing_query_budget` |
| `/details/{id}` | ≤ 20 queries | `QueryBudgetTest::test_torrent_details_query_budget` |
| `/announce` | ≤ 8 queries | `QueryBudgetTest::test_announce_query_budget` |
| `/usercp` | ≤ 15 queries | `QueryBudgetTest::test_usercp_query_budget` |

## Log Retention

W3-06 established retention policies for log tables:

| Table | Retention | Pruned By |
|---|---|---|
| `activity_log` | 90 days | `PruneActivityLogJob` (daily at 03:00) |
| `iplog` | 180 days | `PruneActivityLogJob` |
| `login_logs` | 180 days | `PruneActivityLogJob` |

Manual pruning:
```bash
# Dry run — show what would be deleted
php artisan log:prune --dry-run

# Prune a specific table
php artisan log:prune --table=activity_log

# Override retention period
php artisan log:prune --days=30
```

## Cache Layer

W3-04 introduced a unified cache key registry (`App\Support\Cache\Keys`)
and `TaggedCacheService` for tag-based cache invalidation. See
`app/Support/Cache/Keys.php` for the full list of registered keys,
their TTLs, and invalidation tags.
