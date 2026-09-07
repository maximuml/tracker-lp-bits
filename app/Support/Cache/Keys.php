<?php

declare(strict_types=1);

namespace App\Support\Cache;

/**
 * Registry of all cache keys used across the application.
 *
 * Each case declares:
 *   - The key pattern (with placeholders like `{id}`, `{hash}`, `{passkey}`)
 *   - A default TTL in seconds
 *   - One or more cache tags for grouped invalidation
 *
 * Usage:
 *   $key = Keys::USER_CONTENT->key(42);              // "user_42_content"
 *   $ttl = Keys::USER_CONTENT->ttl();                 // 3600
 *   $tags = Keys::USER_CONTENT->tags();               // ['user', 'user:42']
 *   Cache::tags($tags)->remember($key, $ttl, $callback);
 *
 * Tags enable bulk invalidation:
 *   Cache::tags(['user'])->flush();                   // flush all user keys
 *   Cache::tags(['settings'])->flush();               // flush all settings
 *
 * The registry is intentionally exhaustive: every cache key in the codebase
 * should have a corresponding case here so that drift can be detected by
 * static analysis and tests.
 */
enum Keys: string
{
    // ─── User ───────────────────────────────────────────────────────
    case USER_CONTENT = 'user_{id}_content';
    case USER_ROLES = 'user_{id}_roles';
    case USER_ROLE_IDS = 'user_role_ids:{id}';
    case USER_DIRECT_PERMISSIONS = 'direct_permissions:{id}';
    case USER_PASSKEY_CONTENT = 'user_passkey_{passkey}_content';
    case USER_PASSKEY_RSS = 'user_passkey_{passkey}_rss';
    case USER_INBOX_COUNT = 'user_{id}_inbox_count';
    case USER_UNREAD_MESSAGE_COUNT = 'user_{id}_unread_message_count';
    case USER_ENABLE_LATELY = 'user_enable_lately_{id}';
    case ANNOUNCE_USER_PASSKEY = 'announce_user_passkey_{id}';

    // ─── Settings ───────────────────────────────────────────────────
    case SETTINGS_LARAVEL = 'nexus_settings_in_laravel';
    case SETTINGS_NEXUS = 'nexus_settings_in_nexus';
    case SETTING_PROTECTED_FORUM = 'setting_protected_forum';

    // ─── Category / Taxonomy ────────────────────────────────────────
    case CATEGORY_CONTENT = 'category_content';
    case CATEGORY_LIST_MODE = 'category_list_mode_{id}';
    case CATEGORY_ICON_CONTENT = 'category_icon_content';
    case TAXONOMY_LIST_MODE = '{table}_list_mode_{id}';

    // ─── Search Box ─────────────────────────────────────────────────
    case SEARCH_BOX_CONTENT = 'search_box_content';

    // ─── Torrent ────────────────────────────────────────────────────
    case TORRENT_HASH_CONTENT = 'torrent_hash_{hash}_content';
    case TORRENT_NOT_EXISTS = 'torrent_not_exists:{hash}';
    case TORRENT_GLOBAL_STATE = 'torrent_global_state';

    // ─── Forum ──────────────────────────────────────────────────────
    case FORUM_MODERATOR_ARRAY = 'forum_moderator_array';
    case FORUMS_LIST = 'forums_list';
    case OVERFORUMS_LIST = 'overforums_list';
    case TOPIC_POST_COUNT = 'topic_{id}_post_count';
    case TOTAL_POSTS_COUNT = 'total_posts_count';
    case TOTAL_TOPICS_COUNT = 'total_topics_count';
    case ACTIVE_FORUM_USER_COUNT = 'active_forum_user_count';

    // ─── Poll ───────────────────────────────────────────────────────
    case CURRENT_POLL_CONTENT = 'current_poll_content';
    case CURRENT_POLL_RESULT = 'current_poll_result';

    // ─── Staff / Reports ────────────────────────────────────────────
    case STAFF_NEW_REPORT_COUNT = 'staff_new_report_count';
    case STAFF_REPORT_COUNT = 'staff_report_count';
    case STAFF_NEW_CHEATER_COUNT = 'staff_new_cheater_count';
    case STAFF_CHEATER_COUNT = 'staff_cheater_count';
    case STAFF_MESSAGE_NEW = 'staff_message_new_count';
    case STAFF_MESSAGE_TOTAL = 'staff_message_total_count';

    // ─── Agent Allow/Deny ───────────────────────────────────────────
    case AGENT_ALLOW = 'all_agent_allows';
    case AGENT_DENY = 'all_agent_denies';

    // ─── Auth / Security ────────────────────────────────────────────
    case AUTHKEY_TO_PASSKEY = 'authkey2passkey:{authkey}';
    case CHALLENGE_KEY = 'challenge_{username}';
    case RESEND_RATE_LIMIT = 'resend_rate_limit:{email}';

    // ─── GeoIP / Network ────────────────────────────────────────────
    case LOCATIONS_IP = 'locations_{ip}';

    // ─── Stylesheet ─────────────────────────────────────────────────
    case STYLESHEET_CONTENT = 'stylesheet_content';

    // ─── Complaints ─────────────────────────────────────────────────
    case COMPLAINTS_COUNT = 'complaints_count';

    // ─── Hit and Run ────────────────────────────────────────────────
    case HIT_AND_RUN = 'hit_and_run_{uid}_{tid}';

    // ─── Scrape ─────────────────────────────────────────────────────
    case SCRAPE = 'scrape_{infohash}';

    // ─── Events / Outbox ────────────────────────────────────────────
    case EVENT_MODEL = 'event_model_{id}';

    // ─── Today (date-based) ─────────────────────────────────────────
    case TODAY = 'today_{date}';

    // ─── Offer ──────────────────────────────────────────────────────
    case OFFER = 'offer_{id}';

    /**
     * Build the concrete cache key by substituting placeholders.
     *
     * @param  array<string, int|string>  $params
     */
    public function key(array $params = []): string
    {
        $key = $this->value;
        foreach ($params as $placeholder => $value) {
            $key = str_replace('{'.$placeholder.'}', (string) $value, $key);
        }

        return $key;
    }

    /**
     * Default TTL in seconds for this cache key.
     */
    public function ttl(): int
    {
        return match ($this) {
            // User — 1 hour
            self::USER_CONTENT,
            self::USER_ROLES,
            self::USER_PASSKEY_CONTENT,
            self::ANNOUNCE_USER_PASSKEY => 3600,

            // User — 24 hours
            self::USER_ENABLE_LATELY,
            self::AUTHKEY_TO_PASSKEY => 86400,

            // Settings — 24 hours (flushed on save)
            self::SETTINGS_LARAVEL,
            self::SETTINGS_NEXUS,
            self::SETTING_PROTECTED_FORUM => 86400,

            // Category / Taxonomy — 1 hour
            self::CATEGORY_CONTENT,
            self::CATEGORY_LIST_MODE,
            self::TAXONOMY_LIST_MODE,
            self::SEARCH_BOX_CONTENT => 3600,

            // Category icons — 24 hours
            self::CATEGORY_ICON_CONTENT => 86400,

            // Torrent — 5-6 minutes (short for announce freshness)
            self::TORRENT_HASH_CONTENT => 350,
            self::TORRENT_NOT_EXISTS => 350,
            self::TORRENT_GLOBAL_STATE => 3600,

            // Forum — 2 hours
            self::FORUM_MODERATOR_ARRAY,
            self::FORUMS_LIST,
            self::OVERFORUMS_LIST,
            self::TOPIC_POST_COUNT => 7200,

            // Forum counts — 1 hour
            self::TOTAL_POSTS_COUNT,
            self::TOTAL_TOPICS_COUNT,
            self::ACTIVE_FORUM_USER_COUNT => 3600,

            // Poll — 2 hours
            self::CURRENT_POLL_CONTENT,
            self::CURRENT_POLL_RESULT => 7226,

            // Staff counts — 1 hour
            self::STAFF_NEW_REPORT_COUNT,
            self::STAFF_REPORT_COUNT,
            self::STAFF_NEW_CHEATER_COUNT,
            self::STAFF_CHEATER_COUNT,
            self::STAFF_MESSAGE_NEW,
            self::STAFF_MESSAGE_TOTAL => 3600,

            // Agent allow/deny — 1 hour
            self::AGENT_ALLOW,
            self::AGENT_DENY => 3600,

            // Auth — 24 hours
            self::CHALLENGE_KEY => 86400,

            // Rate limit — 1 hour
            self::RESEND_RATE_LIMIT => 3600,

            // GeoIP — 10 days
            self::LOCATIONS_IP => 864000,

            // Stylesheet — 24 hours
            self::STYLESHEET_CONTENT => 86400,

            // Complaints — 1 hour
            self::COMPLAINTS_COUNT => 3600,

            // Hit and Run — 1-3 days (randomised in caller)
            self::HIT_AND_RUN => 86400,

            // Scrape — 20 minutes
            self::SCRAPE => 1200,

            // Events — 30 days
            self::EVENT_MODEL => 2592000,

            // Today — 1 hour
            self::TODAY => 3600,

            // Offer — 1 hour
            self::OFFER => 3600,

            // User passkey RSS — 1 hour
            self::USER_PASSKEY_RSS => 3600,

            // User role IDs / direct permissions — 1 hour
            self::USER_ROLE_IDS,
            self::USER_DIRECT_PERMISSIONS => 3600,

            // User inbox/unread — 1 hour
            self::USER_INBOX_COUNT,
            self::USER_UNREAD_MESSAGE_COUNT => 3600,
        };
    }

    /**
     * Cache tags for grouped invalidation.
     *
     * Tags allow flushing all keys belonging to a domain at once:
     *   Cache::tags(['user'])->flush();
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return match ($this) {
            // User keys tagged by domain + entity id
            self::USER_CONTENT,
            self::USER_ROLES,
            self::USER_ROLE_IDS,
            self::USER_DIRECT_PERMISSIONS,
            self::USER_INBOX_COUNT,
            self::USER_UNREAD_MESSAGE_COUNT,
            self::ANNOUNCE_USER_PASSKEY,
            self::USER_ENABLE_LATELY => ['user'],

            self::USER_PASSKEY_CONTENT,
            self::USER_PASSKEY_RSS => ['user', 'passkey'],

            // Settings
            self::SETTINGS_LARAVEL,
            self::SETTINGS_NEXUS,
            self::SETTING_PROTECTED_FORUM => ['settings'],

            // Category / Taxonomy
            self::CATEGORY_CONTENT,
            self::CATEGORY_LIST_MODE,
            self::CATEGORY_ICON_CONTENT,
            self::TAXONOMY_LIST_MODE => ['category'],

            // Search box
            self::SEARCH_BOX_CONTENT => ['searchbox'],

            // Torrent
            self::TORRENT_HASH_CONTENT,
            self::TORRENT_NOT_EXISTS,
            self::TORRENT_GLOBAL_STATE => ['torrent'],

            // Forum
            self::FORUM_MODERATOR_ARRAY,
            self::FORUMS_LIST,
            self::OVERFORUMS_LIST,
            self::TOPIC_POST_COUNT,
            self::TOTAL_POSTS_COUNT,
            self::TOTAL_TOPICS_COUNT,
            self::ACTIVE_FORUM_USER_COUNT => ['forum'],

            // Poll
            self::CURRENT_POLL_CONTENT,
            self::CURRENT_POLL_RESULT => ['poll'],

            // Staff
            self::STAFF_NEW_REPORT_COUNT,
            self::STAFF_REPORT_COUNT,
            self::STAFF_NEW_CHEATER_COUNT,
            self::STAFF_CHEATER_COUNT,
            self::STAFF_MESSAGE_NEW,
            self::STAFF_MESSAGE_TOTAL => ['staff'],

            // Agent
            self::AGENT_ALLOW,
            self::AGENT_DENY => ['agent'],

            // Auth
            self::AUTHKEY_TO_PASSKEY,
            self::CHALLENGE_KEY,
            self::RESEND_RATE_LIMIT => ['auth'],

            // GeoIP
            self::LOCATIONS_IP => ['geoip'],

            // Stylesheet
            self::STYLESHEET_CONTENT => ['stylesheet'],

            // Complaints
            self::COMPLAINTS_COUNT => ['complaints'],

            // Hit and Run
            self::HIT_AND_RUN => ['hitandrun'],

            // Scrape
            self::SCRAPE => ['scrape'],

            // Events
            self::EVENT_MODEL => ['events'],

            // Today
            self::TODAY => ['today'],

            // Offer
            self::OFFER => ['offer'],
        };
    }

    /**
     * Build the key with locale prefix for per-language caching.
     *
     * @param  array<string, int|string>  $params
     */
    public function keyWithLocale(string $locale, array $params = []): string
    {
        return $locale.'_'.$this->key($params);
    }
}
