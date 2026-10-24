<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\ShoutboxRepositoryInterface;
use App\Support\Html\SafeHtml;
use App\Support\State\ShoutboxState;
use Illuminate\Database\Query\Builder;

/**
 * Helpers for the shoutbox / live chat UI: formatting, reactions,
 * edit/delete controls, toolbar markup and shared rendering.
 */
final class Shoutbox
{
    public const EDIT_WINDOW = 120;

    public const MAX_MESSAGE_LENGTH = 1000;

    public const REACTIONS = ['👍', '🔥', '❤️', '😂', '😮', '😢'];

    public static function resetState(): void
    {
        ShoutboxState::instance()->reset();
    }

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
        self::shoutboxRepository()->applyTypeFilter($query, $type, $user);
    }

    /**
     * Build the message-formatting toolbar and emoji picker for the
     * shoutbox input form. Returns a small chunk of HTML that is
     * placed right above the input in public/index.php.
     *
     * @param  string  $formName  Name of the form wrapping the textarea
     * @param  string  $fieldName  Name of the textarea element
     */
    public static function toolbar(string $formName = 'shbox', string $fieldName = 'shbox_text'): SafeHtml
    {
        return SafeHtml::fromTrustedHtml(view('components.shoutbox.toolbar', [
            'formName' => $formName,
            'fieldName' => $fieldName,
            'smiliesHtml' => SafeHtml::fromTrustedHtml(Smilies::quickRow($formName, $fieldName)),
        ])->render());
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
            return SafeHtml::fromPlainText('');
        }

        $html = (string) Comment::format($text, true, false, true, true, 600, true, false);
        $html = self::renderMentions($html, $currentUserId, $mentionsMe);
        $html = self::renderTorrents($html);

        return SafeHtml::fromTrustedHtml($html);
    }

    /**
     * Role badge data for staff/VIP-tier classes, rendered by the
     * `x-shoutbox.class-badge` component. Returns null for regular users.
     *
     * @return array{label: string, modifier: string, tooltip: string}|null
     */
    public static function classBadgeData(int $class): ?array
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
            return null;
        }
        [$label, $modifier] = $map[$class];
        $tooltip = '';
        if (function_exists('get_user_class_name')) {
            $tooltip = (string) UserClass::name($class, false, false, true);
        }

        return ['label' => $label, 'modifier' => $modifier, 'tooltip' => $tooltip];
    }

    /**
     * [edit]/[delete] action data for a single message, rendered by the
     * `x-shoutbox.actions` component. Returns null when the viewer may
     * neither edit nor delete.
     *
     * @param  array<string, mixed>  $message  Shoutbox row
     * @return array{msgId: int, canEdit: bool, canDelete: bool}|null
     */
    public static function actionsData(array $message, int $currentUserId, bool $isStaff): ?array
    {
        $msgUserId = (int) ($message['userid'] ?? 0);
        $msgDate = (int) ($message['date'] ?? 0);
        $msgId = (int) ($message['id'] ?? 0);

        if ($msgId <= 0) {
            return null;
        }

        $now = defined('TIMENOW') ? (int) TIMENOW : time();
        $inWindow = ($now - $msgDate) <= self::EDIT_WINDOW;
        $isOwn = $msgUserId > 0 && $msgUserId === $currentUserId;

        $canEdit = $isOwn && $inWindow;
        $canDelete = $isStaff || ($isOwn && $inWindow);

        if (! $canEdit && ! $canDelete) {
            return null;
        }

        return ['msgId' => $msgId, 'canEdit' => $canEdit, 'canDelete' => $canDelete];
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

        return self::shoutboxRepository()->prefetchReactions($shoutIds, $currentUserId);
    }

    /**
     * Reaction button-bar data for a message (Discord/Slack style),
     * rendered by the `x-shoutbox.reactions` component.
     *
     * @param  int  $shoutId  Message id
     * @param  int  $currentUserId  Id of the viewing user
     * @param  array<string, int>|null  $countsMap  Reaction counts (from prefetchReactions)
     * @param  list<string>|null  $myReactionsMap  Current user's reactions
     * @param  array<string, list<string>>|null  $reactorMap  Reactor names per emoji
     * @return array{shoutId: int, reactions: list<array{emoji: string, count: int, active: bool, title: string}>, showPicker: bool, pickerEmojis: list<string>}|null
     */
    public static function reactionsData(int $shoutId, int $currentUserId, ?array $countsMap = null, ?array $myReactionsMap = null, ?array $reactorMap = null): ?array
    {
        if ($shoutId <= 0) {
            return null;
        }

        if ($countsMap !== null || $myReactionsMap !== null) {
            $counts = $countsMap ?? [];
            $myReactions = $myReactionsMap ?? [];
            $reactors = $reactorMap ?? [];
        } else {
            $counts = self::shoutboxRepository()->getReactionCounts($shoutId);
            $myReactions = self::shoutboxRepository()->getMyReactions($shoutId, $currentUserId);

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

        return [
            'shoutId' => $shoutId,
            'reactions' => $reactionRows,
            'showPicker' => $currentUserId > 0,
            'pickerEmojis' => self::REACTIONS,
        ];
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
            return (string) __('shoutbox.title_react');
        }

        return ((string) __('shoutbox.title_reacted_by')).': '.$text;
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

        return (string) preg_replace_callback(
            '/(?<![\w\-\[\]\(\)])@([\w\-\[\]\(\)]{2,40})(?![\w\-\[\]\(\)])/u',
            function (array $m) use ($currentUserId, &$mentionsMe): string {
                $nick = $m[1];
                $key = strtolower($nick);
                if (! array_key_exists($key, ShoutboxState::instance()->mentionUserCache)) {
                    ShoutboxState::instance()->mentionUserCache[$key] = self::shoutboxRepository()->findUserByUsername($nick) ?? false;
                }
                if (! ShoutboxState::instance()->mentionUserCache[$key]) {
                    return $m[0];
                }
                $isMe = $currentUserId > 0 && ShoutboxState::instance()->mentionUserCache[$key]['id'] === $currentUserId;
                if ($isMe) {
                    $mentionsMe = true;
                }
                $cls = $isMe ? 'shout-mention shout-mention-me' : 'shout-mention';
                $name = ShoutboxState::instance()->mentionUserCache[$key]['name'];
                $tooltip = __('shoutbox.tooltip_nick_reply');
                $title = $tooltip !== 'shoutbox.tooltip_nick_reply' ? (string) $tooltip : '';

                return trim(view('components.shoutbox.mention', [
                    'cls' => $cls,
                    'userId' => ShoutboxState::instance()->mentionUserCache[$key]['id'],
                    'name' => $name,
                    'title' => $title,
                    'mention' => '@'.$name,
                    'loggedIn' => $currentUserId > 0,
                ])->render());
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

        return (string) preg_replace_callback(
            '/(?<![\w&"\/=])#(\d{1,9})(?!\w)/',
            function (array $m): string {
                $id = (int) $m[1];
                if ($id <= 0) {
                    return $m[0];
                }
                if (! array_key_exists($id, ShoutboxState::instance()->torrentExistsCache)) {
                    ShoutboxState::instance()->torrentExistsCache[$id] = self::shoutboxRepository()->torrentExists($id);
                }
                if (! ShoutboxState::instance()->torrentExistsCache[$id]) {
                    return $m[0];
                }

                return trim(view('components.shoutbox.torrent-link', ['id' => $id])->render());
            },
            $html
        );
    }

    /**
     * @param  iterable<int, mixed>  $rows
     * @param  array<string, mixed>  $currentUser
     * @param  array<string, mixed>  $reactionData
     * @return list<array<string, mixed>>
     */
    public static function decorateRows(iterable $rows, array $currentUser, int $currentUserId, bool $isStaff, array $reactionData): array
    {
        $reactionCounts = (array) ($reactionData['counts'] ?? []);
        $reactionMine = (array) ($reactionData['mine'] ?? []);
        $reactionUsers = (array) ($reactionData['users'] ?? []);
        $showAvatars = YesNo::isYes($currentUser['avatars'] ?? null);
        $tooltipAvatar = (string) (__('shoutbox.tooltip_avatar'));
        $tooltipReply = (string) (__('shoutbox.tooltip_nick_reply'));
        $labelMore = (string) (__('shoutbox.shout_show_more'));
        $labelLess = (string) (__('shoutbox.shout_show_less'));
        $groupWindowSec = 120;

        $items = [];
        $prevUserId = 0;
        $prevDate = 0;
        foreach ($rows as $row) {
            $arr = (array) $row;
            $currUserId = (int) ($arr['userid'] ?? 0);
            $currDate = (int) ($arr['date'] ?? 0);
            $shoutId = (int) ($arr['id'] ?? 0);
            $isContinuation = $currUserId > 0
                && $currUserId === $prevUserId
                && $prevDate > 0
                && abs($prevDate - $currDate) <= $groupWindowSec;

            $editedTime = null;
            if (! empty($arr['edited_at']) && (int) $arr['edited_at'] > 0) {
                $editedTime = SafeHtml::fromTrustedHtml(self::formatTime((int) $arr['edited_at'], true));
            }

            $avatarUrl = 'pic/default_avatar.png';
            $nickReplyName = '';
            if ($currUserId > 0) {
                $username = UserDisplay::username($currUserId, false, true, true, true, false, false, '', true);
                $userRow = UserDisplay::row($currUserId);
                $userRow = is_array($userRow) ? $userRow : [];
                $nickReplyName = trim((string) ($userRow['username'] ?? ''));
                $classBadge = self::classBadgeData((int) ($userRow['class'] ?? 0));
                if ($showAvatars) {
                    $rawAvatar = trim((string) ($userRow['avatar'] ?? ''));
                    if ($rawAvatar !== '') {
                        $avatarUrl = $rawAvatar;
                    }
                }
                if ($nickReplyName !== '' && $currentUserId > 0) {
                    $username = (string) preg_replace(
                        '#href="[^"]*userdetails\.php\?id=\d+"#',
                        'href="#" class="shout-nick-reply" data-nick="'.htmlspecialchars($nickReplyName, ENT_QUOTES).'" title="'.htmlspecialchars($tooltipReply, ENT_QUOTES).'"',
                        (string) $username,
                        1
                    );
                }
            } else {
                $username = (string) (__('shoutbox.text_guest'));
                $classBadge = null;
            }

            $mentionsMe = false;
            $message = self::formatMessage((string) ($arr['text'] ?? ''), $currentUserId, $mentionsMe);
            $isLong = mb_strlen(strip_tags((string) $message)) > 280;

            $rowClasses = ['shoutrow'];
            if ($mentionsMe) {
                $rowClasses[] = 'shoutrow-mentions-me';
            }
            if ($isContinuation) {
                $rowClasses[] = 'shout-row-grouped';
                $username = '';
                $classBadge = null;
            }

            $items[] = [
                'rowClass' => implode(' ', $rowClasses),
                'time' => SafeHtml::fromTrustedHtml(self::formatTime($currDate, true)),
                'actions' => self::actionsData($arr, $currentUserId, $isStaff),
                'avatarUrl' => $avatarUrl,
                'avatarUserId' => $currUserId,
                'avatarTooltip' => $tooltipAvatar,
                'avatarSpacer' => $isContinuation,
                'classBadge' => $classBadge,
                'username' => SafeHtml::fromTrustedHtml($username),
                'isGuest' => $currUserId <= 0,
                'reactions' => self::reactionsData(
                    $shoutId,
                    $currentUserId,
                    is_array($reactionCounts[$shoutId] ?? null) ? $reactionCounts[$shoutId] : [],
                    is_array($reactionMine[$shoutId] ?? null) ? array_values($reactionMine[$shoutId]) : [],
                    is_array($reactionUsers[$shoutId] ?? null) ? $reactionUsers[$shoutId] : []
                ),
                'msgId' => $shoutId,
                'msgLong' => $isLong,
                'msgRaw' => (string) ($arr['text'] ?? ''),
                'msgFormatted' => $message,
                'editedTime' => $editedTime,
                'labelMore' => $labelMore,
                'labelLess' => $labelLess,
            ];

            $prevUserId = $currUserId;
            $prevDate = $currDate;
        }

        return $items;
    }

    private static function shoutboxRepository(): ShoutboxRepositoryInterface
    {
        return app(ShoutboxRepositoryInterface::class);
    }
}
