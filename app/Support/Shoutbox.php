<?php

namespace App\Support;

use App\Models\Torrent;
use App\Models\User;
use Nexus\Database\NexusDB;

/**
 * Helpers for the shoutbox / live chat UI: formatting, reactions,
 * edit/delete controls, toolbar markup and shared rendering.
 */
final class Shoutbox
{
    public const EDIT_WINDOW = 120;

    /** @var list<string> */
    public const REACTIONS = ['👍', '🔥', '❤️', '😂', '😮', '😢'];

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
        $lang = $GLOBALS['lang_shoutbox'] ?? [];

        $b = $lang['toolbar_bold'] ?? 'B';
        $i = $lang['toolbar_italic'] ?? 'I';
        $spoiler = $lang['toolbar_spoiler'] ?? 'spoiler';
        $code = $lang['toolbar_code'] ?? 'code';
        $quote = $lang['toolbar_quote'] ?? 'quote';
        $link = $lang['toolbar_link'] ?? 'link';
        $emoji = $lang['toolbar_emoji'] ?? 'emoji';

        $html = '<div class="shoutbox-toolbar">';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxWrap(\'b\', \'' . $formName . '\', \'' . $fieldName . '\')" title="[b]"><b>' . $b . '</b></button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxWrap(\'i\', \'' . $formName . '\', \'' . $fieldName . '\')" title="[i]"><i>' . $i . '</i></button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxSpoiler(\'' . $formName . '\', \'' . $fieldName . '\')" title="[spoiler]">' . $spoiler . '</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxWrap(\'code\', \'' . $formName . '\', \'' . $fieldName . '\')" title="[code]">' . $code . '</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxQuote(\'' . $formName . '\', \'' . $fieldName . '\')" title="[quote]">' . $quote . '</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxLink(\'' . $formName . '\', \'' . $fieldName . '\')" title="[url]">' . $link . '</button>';
        $html .= '<button type="button" class="btn shoutbox-tool" onclick="shoutboxToggleEmoji(\'' . $formName . '\', \'' . $fieldName . '\')" title="Emoji">' . $emoji . '</button>';
        $html .= '</div>';

        $html .= '<div id="' . $panelId . '" class="shoutbox-emoji-panel" style="display:none">';
        $html .= Smilies::quickRow($formName, $fieldName);
        $html .= '</div>';

        return $html;
    }

    /**
     * Format a raw shoutbox message into safe HTML: BBCode, smilies,
     * @mentions, #torrent links.
     *
     * @param  string  $text         Raw message text
     * @param  int     $currentUserId Id of the viewing user
     * @param  bool    $mentionsMe   Set to true when the message mentions the viewer
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

        $lang = $GLOBALS['lang_shoutbox'] ?? [];
        $editLabel = $lang['text_edit'] ?? 'edit';
        $delLabel = $lang['text_del'] ?? 'del';
        $html = '<span class="shout-actions">';
        if ($canEdit) {
            $html .= ' <a href="javascript:void(0)" class="shout-action-edit" onclick="shoutboxEdit(' . $msgId . ')" title="' . htmlspecialchars((string) ($lang['title_edit_shout'] ?? 'Edit'), ENT_QUOTES) . '">[' . htmlspecialchars($editLabel) . ']</a>';
        }
        if ($canDelete) {
            $html .= ' <a href="javascript:void(0)" class="shout-action-del" onclick="shoutboxDelete(' . $msgId . ')" title="' . htmlspecialchars((string) ($lang['title_delete_shout'] ?? 'Delete'), ENT_QUOTES) . '">[' . htmlspecialchars($delLabel) . ']</a>';
        }
        $html .= '</span>';

        return $html;
    }

    /**
     * Build the reaction button bar for a message.
     *
     * @param  int  $shoutId        Message id
     * @param  int  $currentUserId  Id of the viewing user
     */
    public static function renderReactions(int $shoutId, int $currentUserId): string
    {
        if ($shoutId <= 0) {
            return '';
        }

        /** @var array<string, int> $counts */
        $counts = NexusDB::table('shoutbox_reactions')
            ->select('reaction', NexusDB::raw('COUNT(*) as cnt'))
            ->where('shoutbox_id', $shoutId)
            ->groupBy('reaction')
            ->pluck('cnt', 'reaction')
            ->toArray();

        /** @var list<string> $myReactions */
        $myReactions = NexusDB::table('shoutbox_reactions')
            ->where('shoutbox_id', $shoutId)
            ->where('user_id', $currentUserId)
            ->pluck('reaction')
            ->toArray();

        $html = '<span class="shout-reactions">';
        foreach (self::REACTIONS as $emoji) {
            $cnt = (int) ($counts[$emoji] ?? 0);
            $active = in_array($emoji, $myReactions, true) ? ' active' : '';
            $encoded = json_encode($emoji, JSON_UNESCAPED_UNICODE);
            $html .= '<button type="button" class="shout-reaction' . $active . '" onclick="shoutboxReact(' . $shoutId . ', ' . htmlspecialchars($encoded, ENT_QUOTES, 'UTF-8') . ')" title="' . htmlspecialchars((string) ($GLOBALS['lang_shoutbox']['title_react'] ?? 'React'), ENT_QUOTES) . '">' . $emoji . ' ' . $cnt . '</button>';
        }
        $html .= '</span>';

        return $html;
    }

    /**
     * Render a formatted relative/absolute timestamp for a shoutbox row.
     */
    public static function formatTime(int $timestamp, bool $oneUnit = true): string
    {
        $timeString = date('Y-m-d H:i:s', $timestamp);

        return \gettime($timeString, true, false, true, $oneUnit);
    }

    /**
     * Replace plain @username tokens with links to userdetails.
     *
     * @param  string  $html       Already-rendered HTML
     * @param  int     $currentUserId
     * @param  bool    $mentionsMe
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
                    $row = User::query()->whereRaw('LOWER(username) = LOWER(?)', [$nick])->first(['id', 'username']);
                    $cache[$key] = $row ? ['id' => (int) $row->id, 'name' => (string) $row->username] : false;
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
                if (isset($GLOBALS['lang_shoutbox']['tooltip_nick_reply'])) {
                    $title = ' title="' . htmlspecialchars((string) $GLOBALS['lang_shoutbox']['tooltip_nick_reply'], ENT_QUOTES) . '"';
                }
                if ($currentUserId > 0) {
                    $onclick = 'return shoutReply(' . htmlspecialchars(json_encode($name, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')';
                    return '<a class="' . $cls . '" href="userdetails.php?id=' . $cache[$key]['id'] . '" onclick="' . $onclick . '"' . $title . '>@' . htmlspecialchars($name) . '</a>';
                }
                return '<a class="' . $cls . '" href="userdetails.php?id=' . $cache[$key]['id'] . '">@' . htmlspecialchars($name) . '</a>';
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
                    $cache[$id] = Torrent::query()->where('id', $id)->exists();
                }
                if (! $cache[$id]) {
                    return $m[0];
                }
                return '<a class="shout-torrent" href="details.php?id=' . $id . '" target="_blank">#' . $id . '</a>';
            },
            $html
        );
    }
}
