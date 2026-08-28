<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\ShoutboxRepository;
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
     * Validate a shoutbox CSRF token. Accepts the current and the previous
     * hourly window so users on a stale page still work during a rollover.
     */
    public static function validateCsrfToken(int $userId, string $token): bool
    {
        $secret = self::getAppKey();
        if ($secret === '') {
            return false;
        }
        $parts = explode(':', $token, 2);
        if (count($parts) !== 2 || ! ctype_digit($parts[0])) {
            return false;
        }
        $window = $parts[0];
        $expected = $window.':'.hash_hmac('sha256', 'shoutbox:'.$userId.':'.$window, $secret);
        if (hash_equals($expected, $token)) {
            return true;
        }
        $previousWindow = (string) ($window - 1);
        $previousExpected = $previousWindow.':'.hash_hmac('sha256', 'shoutbox:'.$userId.':'.$previousWindow, $secret);

        return hash_equals($previousExpected, $token);
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
        ShoutboxRepository::applyTypeFilter($query, $type, $user);
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
        $panelId = 'shoutbox-emoji-panel';
        $lang = app(Language::class)->shoutbox();

        $b = $lang['toolbar_bold'] ?? 'B';
        $i = $lang['toolbar_italic'] ?? 'I';
        $spoiler = $lang['toolbar_spoiler'] ?? 'spoiler';
        $code = $lang['toolbar_code'] ?? 'code';
        $quote = $lang['toolbar_quote'] ?? 'quote';
        $link = $lang['toolbar_link'] ?? 'link';
        $emoji = $lang['toolbar_emoji'] ?? 'emoji';

        $html = '<div class="shoutbox-toolbar">';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxWrap(\'b\', \''.$formName.'\', \''.$fieldName.'\')" title="[b]"><b>'.$b.'</b></button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxWrap(\'i\', \''.$formName.'\', \''.$fieldName.'\')" title="[i]"><i>'.$i.'</i></button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxSpoiler(\''.$formName.'\', \''.$fieldName.'\')" title="[spoiler]">'.$spoiler.'</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxWrap(\'code\', \''.$formName.'\', \''.$fieldName.'\')" title="[code]">'.$code.'</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxQuote(\''.$formName.'\', \''.$fieldName.'\')" title="[quote]">'.$quote.'</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxLink(\''.$formName.'\', \''.$fieldName.'\')" title="[url]">'.$link.'</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxToggleEmoji(\''.$formName.'\', \''.$fieldName.'\')" title="Emoji">'.$emoji.'</button>';
        $html .= '</div>';

        $html .= '<div id="'.$panelId.'" class="shoutbox-emoji-panel" style="display:none">';
        $html .= Smilies::quickRow($formName, $fieldName);
        $html .= '</div>';

        return $html;
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
    public static function formatMessage(string $text, int $currentUserId, bool &$mentionsMe = false): string
    {
        if ($text === '') {
            return '';
        }

        $html = Comment::format($text, true, false, true, true, 600, true, false);
        $html = self::renderMentions($html, $currentUserId, $mentionsMe);
        $html = self::renderTorrents($html);

        return $html;
    }

    /**
     * Build a small role badge for staff/VIP-tier classes.
     * Returns empty string for regular users.
     */
    public static function classBadge(int $class): string
    {
        static $map = null;
        if ($map === null) {
            $map = [
                UC_VIP => ['VIP', '#9c27b0'],
                UC_RETIREE => ['RET', '#607d8b'],
                UC_UPLOADER => ['UPL', '#1976d2'],
                UC_MODERATOR => ['MOD', '#388e3c'],
                UC_ADMINISTRATOR => ['ADM', '#d32f2f'],
                UC_SYSOP => ['SYS', '#b71c1c'],
                UC_STAFFLEADER => ['CHIEF', '#e65100'],
            ];
        }
        $class = (int) $class;
        if (! isset($map[$class])) {
            return '';
        }
        $label = $map[$class][0];
        $color = $map[$class][1];
        $tooltip = '';
        if (function_exists('get_user_class_name')) {
            $tooltip = (string) UserClass::name($class, false, false, true);
        }

        return '<span class="shout-class-badge" style="background:'.$color.'" title="'.htmlspecialchars($tooltip, ENT_QUOTES).'">'.$label.'</span>';
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

        $lang = app(Language::class)->shoutbox();
        $editLabel = $lang['text_edit'] ?? 'edit';
        $delLabel = $lang['text_del'] ?? 'del';
        $html = '<span class="shout-actions">';
        if ($canEdit) {
            $html .= ' <a href="javascript:void(0)" class="shout-action-edit" onclick="shoutboxEdit('.$msgId.')" title="'.htmlspecialchars((string) ($lang['title_edit_shout'] ?? 'Edit'), ENT_QUOTES).'">['.htmlspecialchars($editLabel).']</a>';
        }
        if ($canDelete) {
            $html .= ' <a href="javascript:void(0)" class="shout-action-del" onclick="shoutboxDelete('.$msgId.')" title="'.htmlspecialchars((string) ($lang['title_delete_shout'] ?? 'Delete'), ENT_QUOTES).'">['.htmlspecialchars($delLabel).']</a>';
        }
        $html .= '</span>';

        return $html;
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

        return ShoutboxRepository::prefetchReactions($shoutIds, $currentUserId);
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
            $counts = ShoutboxRepository::getReactionCounts($shoutId);
            $myReactions = ShoutboxRepository::getMyReactions($shoutId, $currentUserId);

            $reactors = [];
        }

        $lang = app(Language::class)->shoutbox();
        $titleReact = (string) ($lang['title_react'] ?? 'React');
        $titleAdd = (string) ($lang['title_add_reaction'] ?? 'Add reaction');
        $titleReacted = (string) ($lang['title_reacted_by'] ?? 'Reacted by');

        $html = '<span class="shout-reactions">';
        foreach (self::REACTIONS as $emoji) {
            $cnt = (int) ($counts[$emoji] ?? 0);
            if ($cnt <= 0) {
                continue;
            }
            $active = in_array($emoji, $myReactions, true) ? ' active' : '';
            $encoded = (string) json_encode($emoji, JSON_UNESCAPED_UNICODE);
            $title = self::buildReactorTooltip($cnt, $reactors[$emoji] ?? []);
            $html .= '<button type="button" class="shout-reaction'.$active.'" onclick="shoutboxReact('.$shoutId.', '.htmlspecialchars($encoded, ENT_QUOTES, 'UTF-8').')" title="'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'">'.$emoji.' '.$cnt.'</button>';
        }

        if ($currentUserId > 0) {
            $html .= '<button type="button" class="shout-reaction shout-reaction-add" onclick="shoutboxToggleReactionPicker('.$shoutId.')" title="'.htmlspecialchars($titleAdd, ENT_QUOTES, 'UTF-8').'">+</button>';
            $html .= '<span class="shout-reaction-picker" id="shout-reaction-picker-'.$shoutId.'" style="display:none">';
            foreach (self::REACTIONS as $emoji) {
                $encoded = (string) json_encode($emoji, JSON_UNESCAPED_UNICODE);
                $html .= '<button type="button" class="shout-reaction" onclick="shoutboxReact('.$shoutId.', '.htmlspecialchars($encoded, ENT_QUOTES, 'UTF-8').'); shoutboxToggleReactionPicker('.$shoutId.')" title="'.htmlspecialchars($titleReact, ENT_QUOTES, 'UTF-8').'">'.$emoji.'</button>';
            }
            $html .= '</span>';
        }
        $html .= '</span>';

        return $html;
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
            return (string) (app(Language::class)->shoutbox()['title_react'] ?? 'React');
        }

        return ((string) (app(Language::class)->shoutbox()['title_reacted_by'] ?? 'Reacted by')).': '.$text;
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
                    $cache[$key] = ShoutboxRepository::findUserByUsername($nick) ?? false;
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
                $shoutboxLang = app(Language::class)->shoutbox();
                if (isset($shoutboxLang['tooltip_nick_reply'])) {
                    $title = ' title="'.htmlspecialchars((string) $shoutboxLang['tooltip_nick_reply'], ENT_QUOTES).'"';
                }
                if ($currentUserId > 0) {
                    $onclick = 'return shoutReply('.htmlspecialchars((string) json_encode($name, JSON_UNESCAPED_UNICODE), ENT_QUOTES).')';

                    return '<a class="'.$cls.'" href="userdetails.php?id='.$cache[$key]['id'].'" onclick="'.$onclick.'"'.$title.'>@'.htmlspecialchars($name).'</a>';
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
                    $cache[$id] = ShoutboxRepository::torrentExists($id);
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
