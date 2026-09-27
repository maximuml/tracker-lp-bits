<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\ShoutboxRepository;
use App\Support\Html\SafeHtml;
use Illuminate\Database\Query\Builder;

/**
 * Helpers for the shoutbox / live chat UI: formatting, reactions,
 * edit/delete controls, toolbar markup and shared rendering.
 */
final class Shoutbox
{
    public const EDIT_WINDOW = 120;

    public const MAX_MESSAGE_LENGTH = 1000;

    /** @var list<string> */
    public const REACTIONS = ['👍', '🔥', '❤️', '😂', '😮', '😢'];

    /**
     * CSRF token for shoutbox actions. Derived from the app key and a short
     * rotating time window so a leaked token is only usable for ~1 hour.
     */
    public static function csrfToken(int $userId): string
    {
        $secret = self::getAppKey();
        if ($secret === '') {
            throw new \RuntimeException('Shoutbox CSRF requires APP_KEY to be configured');
        }
        $window = (string) floor(time() / 3600);
        $payload = 'shoutbox:'.$userId.':'.$window;

        return $window.':'.hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Read the Laravel app key, falling back to the APP_KEY environment
     * variable. Legacy/FPM bootstrap may not have loaded `config()` yet.
     */
    private static function getAppKey(): string
    {
        $secret = '';
        if (function_exists('config')) {
            $secret = (string) (config('app.key') ?: '');
        }
        if ($secret === '') {
            $secret = (string) (getenv('APP_KEY') ?: '');
        }
        if ($secret === '' && function_exists('nexus_env')) {
            $secret = (string) (Env::get('APP_KEY', null) ?: '');
        }

        return $secret;
    }

    /**
     * Only regular shoutbox messages are visible. Helpbox has been removed,
     * so older rows with type 'hb' are always excluded from filters and streams.
     *
     * @param  Builder  $query
     * @param  array<string, mixed>|object|null  $user
     */
    public static function applyTypeFilter($query, string $type, $user = null): void
    {
        app(ShoutboxRepository::class)->applyTypeFilter($query, $type, $user);
    }

    /**
     * Build the message-formatting toolbar and emoji picker for the
     * shoutbox input form. Returns a small chunk of HTML that is
     * placed right above the input in public/index.php.
     *
     * @param  string  $formName  Name of the form wrapping the textarea
     * @param  string  $fieldName  Name of the textarea element
     */
    public static function toolbar(string $formName = 'shbox', string $fieldName = 'shbox_text'): string
    {
        return view('shoutbox._toolbar', [
            'formName' => $formName,
            'fieldName' => $fieldName,
            'smiliesHtml' => SafeHtml::fromTrustedHtml(Smilies::quickRow($formName, $fieldName)),
        ])->render();
    }

    /**
     * Format a raw shoutbox message into safe HTML: BBCode, smilies,
     *
     * @mentions, #torrent links.
     *
     * @param  string  $text  Raw message text
     * @param  int  $currentUserId  Id of the viewing user
     * @param  bool  $mentionsMe  Set to true when the message mentions the viewer
     */
    public static function formatMessage(string $text, int $currentUserId, bool &$mentionsMe = false): SafeHtml
    {
        if ($text === '') {
            return SafeHtml::fromTrustedHtml('');
        }

        $html = (string) Comment::format($text, true, false, true, true, 600, true, false);
        $html = self::renderMentions($html, $currentUserId, $mentionsMe);
        $html = self::renderTorrents($html);

        return SafeHtml::fromTrustedHtml($html);
    }

    /**
     * Build a small role badge for staff/VIP-tier classes.
     * Returns empty string for regular users.
     */
    public static function classBadge(int $class): SafeHtml
    {
        static $map = null;
        if ($map === null) {
            $map = [
                UC_VIP => ['VIP', 'vip'],
                UC_RETIREE => ['RET', 'retiree'],
                UC_UPLOADER => ['UPL', 'uploader'],
                UC_MODERATOR => ['MOD', 'moderator'],
                UC_ADMINISTRATOR => ['ADM', 'administrator'],
                UC_SYSOP => ['SYS', 'sysop'],
                UC_STAFFLEADER => ['CHIEF', 'staffleader'],
            ];
        }
        $class = (int) $class;
        if (! isset($map[$class])) {
            return SafeHtml::fromTrustedHtml('');
        }
        [$label, $modifier] = $map[$class];
        $tooltip = '';
        if (function_exists('get_user_class_name')) {
            $tooltip = (string) UserClass::name($class, false, false, true);
        }

        return SafeHtml::fromTrustedHtml(view('shoutbox._class_badge', [
            'label' => $label,
            'modifier' => $modifier,
            'tooltip' => $tooltip,
        ])->render());
    }

    /**
     * Build the [edit]/[delete] action links for a single message.
     *
     * @param  array<string, mixed>  $message  Shoutbox row
     */
    public static function renderActions(array $message, int $currentUserId, bool $isStaff): string
    {
        $msgUserId = (int) ($message['userid'] ?? 0);
        $msgDate = (int) ($message['date'] ?? 0);
        $msgId = (int) ($message['id'] ?? 0);

        if ($msgId <= 0) {
            return '';
        }

        $now = defined('TIMENOW') ? (int) TIMENOW : time();
        $inWindow = ($now - $msgDate) <= self::EDIT_WINDOW;
        $isOwn = $msgUserId > 0 && $msgUserId === $currentUserId;

        $canEdit = $isOwn && $inWindow;
        $canDelete = $isStaff || ($isOwn && $inWindow);

        if (! $canEdit && ! $canDelete) {
            return '';
        }

        return view('shoutbox._actions', [
            'msgId' => $msgId,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
        ])->render();
    }

    /**
     * Batch-fetch reaction counts and (for tooltips) a limited list of
     * reactor names for each reaction to avoid the N+1 query pattern.
     *
     * @param  list<int>  $shoutIds
     * @return array{counts: array<int, array<string, int>>, mine: array<int, list<string>>, users: array<int, array<string, list<string>>>}
     */
    public static function prefetchReactions(array $shoutIds, int $currentUserId): array
    {
        if ($shoutIds === []) {
            return ['counts' => [], 'mine' => [], 'users' => []];
        }

        return app(ShoutboxRepository::class)->prefetchReactions($shoutIds, $currentUserId);
    }

    /**
     * Build the reaction button bar for a message (Discord/Slack style).
     *
     * @param  int  $shoutId  Message id
     * @param  int  $currentUserId  Id of the viewing user
     * @param  array<string, int>|null  $countsMap  Reaction counts (from prefetchReactions)
     * @param  list<string>|null  $myReactionsMap  Current user's reactions
     * @param  array<string, list<string>>|null  $reactorMap  Reactor names per emoji
     */
    public static function renderReactions(int $shoutId, int $currentUserId, ?array $countsMap = null, ?array $myReactionsMap = null, ?array $reactorMap = null): string
    {
        if ($shoutId <= 0) {
            return '';
        }

        if ($countsMap !== null || $myReactionsMap !== null) {
            $counts = $countsMap ?? [];
            $myReactions = $myReactionsMap ?? [];
            $reactors = $reactorMap ?? [];
        } else {
            $counts = app(ShoutboxRepository::class)->getReactionCounts($shoutId);
            $myReactions = app(ShoutboxRepository::class)->getMyReactions($shoutId, $currentUserId);

            $reactors = [];
        }

        $reactionRows = [];
        foreach (self::REACTIONS as $emoji) {
            $cnt = (int) ($counts[$emoji] ?? 0);
            if ($cnt <= 0) {
                continue;
            }
            $reactionRows[] = [
                'emoji' => $emoji,
                'count' => $cnt,
                'active' => in_array($emoji, $myReactions, true),
                'title' => self::buildReactorTooltip($cnt, $reactors[$emoji] ?? []),
            ];
        }

        return view('shoutbox._reactions', [
            'shoutId' => $shoutId,
            'reactions' => $reactionRows,
            'showPicker' => $currentUserId > 0,
            'pickerEmojis' => self::REACTIONS,
        ])->render();
    }

    /**
     * Build a tooltip string for a reaction button.
     *
     * @param  list<string>  $names
     */
    private static function buildReactorTooltip(int $count, array $names): string
    {
        $visible = array_slice($names, 0, 10);
        $text = implode(', ', $visible);
        $remaining = $count - count($visible);
        if ($remaining > 0) {
            $text .= ($text === '' ? '' : ', ').'+'.$remaining.' more';
        }
        if ($text === '') {
            return (string) __('legacy/shoutbox.title_react');
        }

        return ((string) __('legacy/shoutbox.title_reacted_by')).': '.$text;
    }

    /**
     * Render a formatted relative/absolute timestamp for a shoutbox row.
     */
    public static function formatTime(int $timestamp, bool $oneUnit = true): string
    {
        $timeString = date('Y-m-d H:i:s', $timestamp);

        return (string) Time::format($timeString, true, false, true, $oneUnit);
    }

    /**
     * Replace plain @username tokens with links to userdetails.
     *
     * @param  string  $html  Already-rendered HTML
     */
    private static function renderMentions(string $html, int $currentUserId, bool &$mentionsMe = false): string
    {
        if ($html === '' || strpos($html, '@') === false) {
            return $html;
        }

        /** @var array<string, array{id:int, name:string}|false> $cache */
        static $cache = [];

        return (string) preg_replace_callback(
            '/(?<![\w\-\[\]\(\)])@([\w\-\[\]\(\)]{2,40})(?![\w\-\[\]\(\)])/u',
            function (array $m) use ($currentUserId, &$mentionsMe, &$cache): string {
                $nick = $m[1];
                $key = strtolower($nick);
                if (! array_key_exists($key, $cache)) {
                    $cache[$key] = app(ShoutboxRepository::class)->findUserByUsername($nick) ?? false;
                }
                if (! $cache[$key]) {
                    return $m[0];
                }
                $isMe = $currentUserId > 0 && $cache[$key]['id'] === $currentUserId;
                if ($isMe) {
                    $mentionsMe = true;
                }
                $cls = $isMe ? 'shout-mention shout-mention-me' : 'shout-mention';
                $name = $cache[$key]['name'];
                $title = '';
                $tooltip = __('legacy/shoutbox.tooltip_nick_reply');
                if ($tooltip !== 'legacy/shoutbox.tooltip_nick_reply') {
                    $title = ' title="'.htmlspecialchars((string) $tooltip, ENT_QUOTES).'"';
                }
                if ($currentUserId > 0) {
                    return '<a class="'.$cls.' shout-nick-reply" href="userdetails.php?id='.$cache[$key]['id'].'" data-nick="'.htmlspecialchars($name, ENT_QUOTES).'"'.$title.'>@'.htmlspecialchars($name).'</a>';
                }

                return '<a class="'.$cls.'" href="userdetails.php?id='.$cache[$key]['id'].'">@'.htmlspecialchars($name).'</a>';
            },
            $html
        );
    }

    /**
     * Replace plain #1234 tokens with links to torrent details.
     */
    private static function renderTorrents(string $html): string
    {
        if ($html === '' || strpos($html, '#') === false) {
            return $html;
        }

        /** @var array<int, bool> $cache */
        static $cache = [];

        return (string) preg_replace_callback(
            '/(?<![\w&"\/=])#(\d{1,9})(?!\w)/',
            function (array $m) use (&$cache): string {
                $id = (int) $m[1];
                if ($id <= 0) {
                    return $m[0];
                }
                if (! array_key_exists($id, $cache)) {
                    $cache[$id] = app(ShoutboxRepository::class)->torrentExists($id);
                }
                if (! $cache[$id]) {
                    return $m[0];
                }

                return '<a class="shout-torrent" href="details.php?id='.$id.'" target="_blank">#'.$id.'</a>';
            },
            $html
        );
    }
}
