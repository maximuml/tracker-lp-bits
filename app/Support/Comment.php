<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Config\SiteConfig;

/**
 * Legacy BBCode formatter extracted from `include/functions.php`.
 *
 * Backs the legacy `format_comment()` global and the `addTempCode()`
 * placeholder mechanism it uses. The per-request temporary-code store
 * lives in this class instead of `global $tempCode` so the formatter
 * can be unit-tested without relying on legacy global state.
 */
final class Comment
{
    /** @var array<int, string> */
    private static array $tempCode = [];

    private static int $tempCodeCount = 0;

    public static function resetTempCode(): void
    {
        self::$tempCode = [];
        self::$tempCodeCount = 0;
    }

    public static function addTempCode(string $value): string
    {
        $key = self::$tempCodeCount;
        self::$tempCode[$key] = $value;
        self::$tempCodeCount++;

        return "<tempCode_$key>";
    }

    /**
     * Format BBCode text into HTML.
     *
     * Mirrors the legacy `format_comment()` global from
     * `include/functions.php` as closely as possible; helper globals
     * (`formatImg`, `formatYoutube`, `formatUrl`, etc.) are still called
     * because they handle their own `filter_src()` / `addTempCode()` dance.
     *
     * @param  bool  $xssclean  Unused legacy parameter, kept for call-site compatibility.
     * @param  bool  $enableflash  Unused legacy parameter, kept for call-site compatibility.
     */
    public static function format(
        string $text,
        bool $stripHtml = true,
        bool $xssclean = false,
        bool $newtab = true,
        bool $imageresizer = true,
        int $imageMaxWidth = 700,
        bool $enableimage = true,
        bool $enableflash = true,
        int $imagenum = -1,
        int $imageMaxHeight = 0,
    ): string {
        if ($text === '') {
            return '';
        }

        self::resetTempCode();

        $lang_functions = app(Language::class)->functions();
        $s = $text;

        if ($stripHtml) {
            $s = htmlspecialchars($s);
        }

        if (str_contains($s, '[code]') && str_contains($s, '[/code]')) {
            $s = (string) preg_replace_callback(
                '/\[code\](.+?)\[\/code\]/is',
                static fn (array $m): string => self::addTempCode(BBCode::code((string) $m[1], (string) Locale::trans('label.text_code'))),
                $s,
            );
        }

        if (str_contains($s, '[raw]') && str_contains($s, '[/raw]')) {
            $s = (string) preg_replace_callback(
                '/\[raw\](.+?)\[\/raw\]/is',
                static fn (array $m): string => self::addTempCode($m[1]),
                $s,
            );
        }

        $s = nl2br($s);

        $originalBbTagArray = [
            '[siteurl]', '[site]', '[*]', '[b]', '[/b]', '[i]', '[/i]',
            '[u]', '[/u]', '[s]', '[/s]', '[pre]', '[/pre]', '[/color]',
            '[/font]', '[/size]', '[hr]', '  ',
        ];
        $replaceXhtmlTagArray = [
            Url::schemeAndHost(),
            SiteConfig::current()->basic->siteName(),
            '&#x2022; ',
            '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<s>', '</s>',
            '<pre>', '</pre>', '</span>', '</font>', '</font>', '<hr>',
            ' &nbsp;',
        ];
        $s = str_replace($originalBbTagArray, $replaceXhtmlTagArray, $s);

        $originalBbTagArray = [
            "/\[font=([^\[\(&\\\\;]+?)\]/is",
            "/\[color=([#0-9a-z]{1,15})\]/is",
            "/\[color=([a-z]+)\]/is",
            "/\[size=([1-7])\]/is",
        ];
        $replaceXhtmlTagArray = [
            '<font face="\\1">',
            '<span style="color: \\1;word-break: break-word">',
            '<span style="color: \\1;word-break: break-word">',
            '<font size="\\1">',
        ];
        $s = (string) preg_replace($originalBbTagArray, $replaceXhtmlTagArray, $s);

        if ($enableimage) {
            $imgReplaceCount = 0;
            $s = (string) preg_replace_callback(
                '/\[img\]([^\<\r\n"\']+?)\[\/img\]/i',
                function (array $m) use ($imageresizer, $imageMaxWidth, $imageMaxHeight): string {
                    return Html::formatImg($m[1], $imageresizer, $imageMaxWidth, $imageMaxHeight);
                },
                $s,
                $imagenum,
                $imgReplaceCount,
            );
            $s = (string) preg_replace_callback(
                '/\[img=([^\<\r\n"\']+?)\]/i',
                function (array $m) use ($imageresizer, $imageMaxWidth, $imageMaxHeight): string {
                    return Html::formatImg($m[1], $imageresizer, $imageMaxWidth, $imageMaxHeight);
                },
                $s,
                ($imagenum != -1 ? max($imagenum - $imgReplaceCount, 0) : -1),
            );
        } else {
            $s = (string) preg_replace('/\[img\]([^\<\r\n"\']+?)\[\/img\]/i', '', $s, -1);
            $s = (string) preg_replace('/\[img=([^\<\r\n"\']+?)\]/i', '', $s, -1);
        }

        if (str_contains($s, '[youtube') && str_contains($s, 'v=')) {
            $s = (string) preg_replace_callback(
                '/\[youtube(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|https):\/\/[^\s\'"<>]+)\[\/youtube\]/i',
                static fn (array $m): string => Html::formatYoutube($m[4], $m[2] ?: 0, $m[3] ?: 0),
                $s,
            );
        }

        if (str_contains($s, '[video')) {
            $s = (string) preg_replace_callback(
                '/\[video(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|https):\/\/[^\s\'"<>]+)\[\/video\]/i',
                static fn (array $m): string => Html::formatVideo($m[4], $m[2] ?: 0, $m[3] ?: 0),
                $s,
            );
        }

        if (str_contains($s, '[audio')) {
            $s = (string) preg_replace_callback(
                '/\[audio\]((http|https):\/\/[^\s\'"<>]+)\[\/audio\]/i',
                static fn (array $m): string => Html::formatAudio($m[1]),
                $s,
            );
        }

        $s = (string) preg_replace_callback(
            '/\[url=([^\[\s]+?)\](.+?)\[\/url\]/i',
            function (array $m) use ($newtab): string {
                return Html::formatUrl($m[1], $newtab, $m[2], 'faqlink');
            },
            $s,
        );

        $s = (string) preg_replace_callback(
            '/\[url\]([^\[\s]+?)\[\/url\]/i',
            function (array $m) use ($newtab): string {
                return Html::formatUrl($m[1], $newtab, '', 'faqlink');
            },
            $s,
        );

        $s = (string) preg_replace_callback(
            '/\[left\](.*)\[\/left\]/isU',
            static fn (array $m): string => Html::formatTextAlign($m[1], 'left'),
            $s,
        );
        $s = (string) preg_replace_callback(
            '/\[center\](.*)\[\/center\]/isU',
            static fn (array $m): string => Html::formatTextAlign($m[1], 'center'),
            $s,
        );
        $s = (string) preg_replace_callback(
            '/\[right\](.*)\[\/right\]/isU',
            static fn (array $m): string => Html::formatTextAlign($m[1], 'right'),
            $s,
        );
        $s = (string) preg_replace_callback(
            '/\[hide\](.*)\[\/hide\]/isU',
            static fn (array $m): string => Html::formatHidden($m[1]),
            $s,
        );

        $s = Format::formatUrls($s, $newtab);

        if (str_contains($s, '[quote') && str_contains($s, '[/quote]')) {
            $s = BBCode::quotes($s, Locale::trans('label.text_quote'));
        }

        $s = (string) preg_replace_callback(
            '/\[em([1-9][0-9]*)\]/i',
            static function (array $m): string {
                $smile = Smilies::pathFor((int) (int) $m[1]);

                return $smile ? '<img src="'.$smile.'" alt="[em'.$m[1].']" />' : '[em'.$m[1].']';
            },
            $s,
        );

        if (str_contains($s, '[spoiler')) {
            $s = (string) preg_replace_callback(
                '/\[spoiler(=(.*))?\](.*)\[\/spoiler\]/isU',
                function (array $m): string {
                    return Html::formatSpoiler(
                        $m[3],
                        $m[2],
                        RequestContext::instance()->getScript() != 'preview',
                    );
                },
                $s,
            );
        }

        $enableattach_attachment = SiteConfig::current()->attachment->enableAttach();
        if ($enableattach_attachment && $imagenum != 1) {
            $limit = 20;
            $s = (string) preg_replace_callback(
                '/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/is',
                function (array $m) use ($enableimage, $imageresizer): string {
                    return Attachment::renderByKey((string) $m[1], (bool) $enableimage, (bool) $imageresizer);
                },
                $s,
                $limit,
            );
        }

        $s = self::resolveTempCodes($s);

        return str_replace("\x08", '', $s);
    }

    private static function resolveTempCodes(string $s): string
    {
        $j = 0;
        while (count(self::$tempCode) || $j > 5) {
            foreach (self::$tempCode as $key => $code) {
                $s = str_replace("<tempCode_$key>", $code, $s, $count);
                if ($count) {
                    unset(self::$tempCode[$key]);
                }
            }
            $j++;
        }

        return $s;
    }

    /**
     * Render the legacy comment table HTML for a list of comment rows.
     *
     * Mirrors `commenttable()` from `include/functions.php`.
     */
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function table(array $rows, string $type, int|string $parentId, bool $review = false): string
    {
        $lang_functions = app(Language::class)->functions();
        $CURUSER = app(CurrentUser::class)->get();
        $commanage_class = (int) app(Globals::class)->get('commanage_class', 0);

        $contentWidth = \defined('CONTENT_WIDTH') ? (int) CONTENT_WIDTH : 100;
        $html = Frame::mainOpen('', false, 100, $contentWidth)
            .Frame::open('', false, 10, '100%', 'left');

        $uidArr = array_values(array_filter(array_map('intval', array_column($rows, 'user'))));
        $neededColumns = ['id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
        $userInfoArr = app(UserRepository::class)->getByIds($uidArr, $neededColumns);

        foreach ($rows as $row) {
            $userInfo = $userInfoArr->get($row['user'], User::defaultUser());
            $userRow = $userInfo->toArray();

            $html .= '<div style="margin-top: 8pt; margin-bottom: 8pt;"><table id="cid'.$row['id'].'" border="0" cellspacing="0" cellpadding="0" width="100%"><tr><td class="embedded" width="99%">#'.$row['id'].'&nbsp;&nbsp;<font color="gray">'.($lang_functions['text_by'] ?? '').'</font>';
            $html .= UserDisplay::username($row['user'], false, true, true, false, false, true);
            $html .= '&nbsp;&nbsp;<font color="gray">'.($lang_functions['text_at'] ?? '').'</font>'.Time::format($row['added'])
                .($row['editedby'] && Permission::can(PermissionEnum::COM_MANAGE) ? ' - [<a href="comment.php?action=vieworiginal&amp;cid='.$row['id'].'&amp;type='.$type.'">'.($lang_functions['text_view_original'] ?? '').'</a>]' : '')
                .'</td><td class="embedded nowrap" width="1%"><a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="Top" /></a>&nbsp;&nbsp;</td></tr></table></div>';

            $avatar = ($CURUSER['avatars'] ?? false) ? \htmlspecialchars(trim($userRow['avatar'])) : '';
            if (! $avatar) {
                $avatar = 'pic/default_avatar.png';
            }
            $text = Format::formatComment($row['text']);
            $textEditby = '';
            if ($row['editedby']) {
                $lastedittime = Time::format($row['editdate'], true, false);
                $textEditby = '<br /><p><font class="small">'.($lang_functions['text_last_edited_by'] ?? '').UserDisplay::username($row['editedby']).($lang_functions['text_edited_at'] ?? '').$lastedittime."</font></p>\n";
            }

            $html .= '<table class="main" width="100%" border="0" cellspacing="0" cellpadding="5">'."\n";
            $secs = 900;
            $dt = date('Y-m-d H:i:s', TIMENOW - $secs);
            $html .= '<tr>'."\n";
            $html .= '<td class="rowfollow" width="150" valign="top" style="padding: 0px;">'.UserDisplay::avatarImageWithContext($avatar).'</td>'."\n";
            $html .= '<td class="rowfollow word-break-all" valign="top"><br />'.$text.$textEditby.'</td>'."\n";
            $html .= '</tr>'."\n";

            $actionbar = '<a href="comment.php?action=add&amp;sub=quote&amp;cid='.$row['id'].'&amp;pid='.$parentId.'&amp;type='.$type.'"><img class="f_quote" src="pic/trans.gif" alt="Quote" title="'.($lang_functions['title_reply_with_quote'] ?? '').'" /></a>'
                .'<a href="comment.php?action=add&amp;pid='.$parentId.'&amp;type='.$type.'"><img class="f_reply" src="pic/trans.gif" alt="Add Reply" title="'.($lang_functions['title_add_reply'] ?? '').'" /></a>'
                .(Permission::can(PermissionEnum::COM_MANAGE) ? '<a href="comment.php?action=delete&amp;cid='.$row['id'].'&amp;type='.$type.'"><img class="f_delete" src="pic/trans.gif" alt="Delete" title="'.($lang_functions['title_delete'] ?? '').'" /></a>' : '')
                .(((is_array($CURUSER) && $row['user'] == ($CURUSER['id'] ?? 0)) || UserDisplay::currentClass() >= $commanage_class) ? '<a href="comment.php?action=edit&amp;cid='.$row['id'].'&amp;type='.$type.'"><img class="f_edit" src="pic/trans.gif" alt="Edit" title="'.($lang_functions['title_edit'] ?? '').'" /></a>' : '');

            $onlineIcon = ($userRow['last_access'] > $dt)
                ? '<img class="f_online" src="pic/trans.gif" alt="Online" title="'.($lang_functions['title_online'] ?? '').'" />'
                : '<img class="f_offline" src="pic/trans.gif" alt="Offline" title="'.($lang_functions['title_offline'] ?? '').'" />';

            $html .= '<tr><td class="toolbox"> '.$onlineIcon.'<a href="sendmessage.php?receiver='.\htmlspecialchars(trim((string) $row['user'])).'"><img class="f_pm" src="pic/trans.gif" alt="PM" title="'.($lang_functions['title_send_message_to'] ?? '').\htmlspecialchars($userRow['username']).'" /></a><a href="report.php?commentid='.\htmlspecialchars(trim((string) $row['id'])).'"><img class="f_report" src="pic/trans.gif" alt="Report" title="'.($lang_functions['title_report_this_comment'] ?? '').'" /></a></td><td class="toolbox" align="right">'.$actionbar.'</td>';

            $html .= '</tr></table>'."\n";
        }

        $html .= Frame::CLOSE.Frame::CLOSE;

        return $html;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function tableVoid(array $rows, string $type, int|string $parentId, bool $review = false): void
    {
        echo self::table($rows, $type, $parentId, $review);
    }
}
