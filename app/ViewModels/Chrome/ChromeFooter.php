<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Support\AssetAppender;
use App\Support\Html\SafeHtml;
use App\Support\QueryLog;
use App\Support\PageLayoutContext;

/**
 * Footer chrome data (ADR 0018): copyright/version line, page-generation
 * stats, the SQL/Redis debug block and the per-variant footer script list
 * plus the keyboard-shortcut and analytics snippets.
 */
final class ChromeFooter
{
    /**
     * @param  list<string>  $footScripts
     * @param  list<array{query: string, time: string}>  $debugQueries
     * @param  list<array{query: string, time: string}>  $debugLaravelQueries
     * @param  array<string, int>  $debugRedisReads
     * @param  array<string, int>  $debugRedisWrites
     */
    private function __construct(
        public readonly string $yearFounded,
        public readonly string $icpLicense,
        public readonly SafeHtml $versionHtml,
        public readonly bool $debugEnabled,
        public readonly array $debugQueries,
        public readonly array $debugLaravelQueries,
        public readonly array $debugRedisReads,
        public readonly array $debugRedisWrites,
        public readonly array $footScripts,
        public readonly SafeHtml $keyShortcutHtml,
        public readonly SafeHtml $analyticsHtml,
    ) {}

    public static function load(
        PageLayoutContext $context,
        string $variant,
        string $cspNonce,
    ): self {
        $debugEnabled = $context->enableSqlDebugTweak === 'yes' && $context->userClass() >= $context->sqlDebugTweak;
        $laravelQueries = [];
        if ($debugEnabled) {
            $laravelQueries = (array) QueryLog::all();
        }

        $keyShortcut = '';
        if ($context->addKeyShortcut !== '') {
            $keyShortcut = $context->addKeyShortcut;
            if ($cspNonce !== '') {
                $keyShortcut = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $keyShortcut);
            }
        }

        $analyticsCode = '';
        if ($context->analyticsCodeTweak !== '') {
            $analyticsCode = $context->analyticsCodeTweak;
            if ($cspNonce !== '') {
                $analyticsCode = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.$cspNonce.'"', $analyticsCode);
            }
            $analyticsCode = "\n".$analyticsCode."\n";
        }

        // site.js reads this once at parse time (footer scripts run before
        // AssetAppender footer entries), so the lang map lives in the head.
        $siteLang = json_encode([
            'scrollTop' => __('legacy/index.scroll_to_top'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
        AssetAppender::js("window.NX_SITE_LANG = $siteLang;", 'header', false, 'site-lang');

        return new self(
            yearFounded: substr($context->dateFounded, 0, 4) ?: '2007',
            icpLicense: $context->icpLicenseMain,
            versionHtml: SafeHtml::fromTrustedHtml(defined('VERSION') ? (string) \constant('VERSION') : ''),
            debugEnabled: $debugEnabled,
            debugQueries: array_values(array_map(
                static fn (array $query): array => ['query' => (string) ($query['query'] ?? ''), 'time' => (string) ($query['time'] ?? '')],
                $context->queryName,
            )),
            debugLaravelQueries: array_values(array_map(
                static fn (array $query): array => ['query' => (string) ($query['raw_query'] ?? ''), 'time' => (string) ($query['time'] ?? '')],
                $laravelQueries,
            )),
            debugRedisReads: $context->cache?->getKeyHits('read') ?? [],
            debugRedisWrites: $context->cache?->getKeyHits('write') ?? [],
            footScripts: self::footScripts($variant),
            keyShortcutHtml: SafeHtml::fromTrustedHtml($keyShortcut),
            analyticsHtml: SafeHtml::fromTrustedHtml($analyticsCode),
        );
    }

    /**
     * Footer script list per variant: the modern chrome defers all shared
     * libraries to the footer, while the legacy variant keeps its
     * historical head-loaded set (page bodies may reference them during
     * parse).
     *
     * @return list<string>
     */
    private static function footScripts(string $variant): array
    {
        if ($variant === 'auth') {
            // Standalone auth pages (ADR 0020): CSRF wiring, the
            // consolidated toolkit bundle, and the auth bindings.
            return ['js/csrf.js', 'js/site.js', 'js/auth.js'];
        }

        // site.js is the consolidated always-on toolkit (ajax, ajaxbasic,
        // nx-tooltip, nx-zoom, common, goup, theme-toggle, nx-chrome,
        // nexus) — one footer request instead of nine. csrf.js stays
        // standalone (layui admin pages load it alone), nx-layer.js stays
        // head-loaded, and toast.js stays opt-in via AssetAppender.
        return ['js/csrf.js', 'js/site.js'];
    }
}
