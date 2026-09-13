-- Migration-upgrade fixture applied on top of the previous-release schema
-- snapshot (database/schema/v2.0.1.mysql.sql) BEFORE running HEAD migrations.
--
-- Two kinds of rows:
--   * sentinel rows with non-default enum values — asserted post-migration to
--     prove data transformations convert them correctly;
--   * bulk rows — make the ALTER-heavy migrations (enum conversions, foreign
--     keys, partition backfill) run against a production-like volume so the
--     job reports a meaningful duration.
--
-- FK note: HEAD adds FKs on peers.torrent, snatched.torrentid,
-- topics.forumid, posts.topicid — bulk rows reference the parents below.

-- Recursive CTE bulk generators exceed the 1000-iteration default.
SET SESSION cte_max_recursion_depth = 100000;

-- parents for FK targets -----------------------------------------------------
INSERT INTO forums (id, sort, name, forid) VALUES (1, 0, 'Fixture forum', 0);

INSERT INTO torrents (id, info_hash, name, filename, save_as, category, size,
                      added, type, visible, banned, anonymous, owner)
VALUES (1, UNHEX(REPEAT('ab', 20)), 'fixture torrent', 'fixture.mkv',
        'fixture.mkv', 1, 1024, NOW(), 'multi', 'yes', 'no', 'no', 10001);

-- sentinel user: non-default values for every transformed column -------------
-- expected after HEAD migrations:
--   enabled=1 donor=0 warned=0 parked=0 savepms=0 (bool),
--   status=1 privacy=2 gender=0 tooltip=2 timetype=0 (enum index)
INSERT INTO users (id, username, passhash, secret, auth_key, passkey, email,
                   added, enabled, donor, warned, parked, savepms,
                   status, privacy, gender, tooltip, timetype)
VALUES (10001, 'fixture_user', 'x', 'x', 'x', SUBSTR(SHA2('fixture',256),1,32), 'fixture@example.com',
        NOW(), 'yes', 'no', 'no', 'no', 'no',
        'confirmed', 'low', 'Male', 'off', 'timeadded');

INSERT INTO topics (id, userid, subject, locked, forumid, sticky)
VALUES (1, 10001, 'fixture topic', 'yes', 1, 'yes');

INSERT INTO posts (topicid, userid, body) VALUES (1, 10001, 'fixture post');

INSERT INTO messages (sender, receiver, added, subject, msg, unread, saved)
VALUES (0, 10001, NOW(), 'fixture', 'fixture message', 'yes', 'yes');

INSERT INTO snatched (torrentid, userid, ip, port, finished)
VALUES (1, 10001, '127.0.0.1', 6881, 'yes');

INSERT INTO peers (torrent, peer_id, ip, port, userid, seeder, connectable, passkey)
VALUES (1, UNHEX(REPEAT('cd', 20)), '127.0.0.1', 6881, 10001, 'yes', 'yes', SUBSTR(SHA2('fixture',256),1,32));

INSERT INTO invites (inviter, invitee, hash, time_invited)
VALUES (10001, '', SUBSTR(SHA2('invite1',256),1,32), NOW());

INSERT INTO agent_allowed_family (family, start_name, peer_id_pattern,
                                  agent_pattern, agent_matchtype, allowhttps)
VALUES ('fixture', 'f', 'f', 'f', 'hex', 'yes');

-- bulk rows: production-like volume ------------------------------------------
-- users ids 20001..25000 (explicit ids so snatched can reference them)
INSERT INTO users (id, username, passhash, secret, passkey, email, added)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 5000)
SELECT 20000 + n, CONCAT('bulk_user_', n), 'x', 'x', SUBSTR(SHA2(n,256),1,32),
       CONCAT('bulk', n, '@example.com'), NOW()
FROM seq;

INSERT INTO peers (torrent, peer_id, ip, port, userid, seeder, connectable, passkey)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 30000)
SELECT 1, UNHEX(LPAD(HEX(n), 40, '0')), '10.0.0.1', 6000 + (n % 1000),
       20001, IF(n % 3 = 0, 'yes', 'no'), 'yes', SUBSTR(SHA2(n,256),1,32)
FROM seq;

INSERT INTO snatched (torrentid, userid, ip, port, finished)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 5000)
SELECT 1, 20000 + n, '10.0.0.1', 6881, IF(n % 4 = 0, 'yes', 'no')
FROM seq;

INSERT INTO iplog (ip, userid, access, uri, count)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 30000)
SELECT CONCAT('10.', n % 255, '.', (n / 255) % 255, '.1'), 20001,
       NOW(), '/announce', 1
FROM seq;

INSERT INTO messages (sender, receiver, added, subject, msg, unread, saved)
WITH RECURSIVE seq AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM seq WHERE n < 3000)
SELECT 20001, 20002, NOW(), 'bulk', 'bulk message body',
       IF(n % 2 = 0, 'yes', 'no'), 'no'
FROM seq;
