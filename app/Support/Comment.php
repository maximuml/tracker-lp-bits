<?php

namespace App\Support;

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
     * @param bool $xssclean Unused legacy parameter, kept for call-site compatibility.
     * @param bool $enableflash Unused legacy parameter, kept for call-site compatibility.
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

        $lang_functions = $GLOBALS['lang_functions'] ?? [];
        $s = $text;

        if ($stripHtml) {
            $s = htmlspecialchars($s);
        }

        if (strpos($s, '[code]') !== false && strpos($s, '[/code]') !== false) {
            $s = (string) preg_replace_callback(
                '/\[code\](.+?)\[\/code\]/is',
                static fn (array $m): string => \formatCode($m[1]),
                $s,
            );
        }

        if (strpos($s, '[raw]') !== false && strpos($s, '[/raw]') !== false) {
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
            \get_protocol_prefix() . \get_setting('basic.BASEURL'),
            \get_setting('basic.SITENAME'),
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

        $imageresizer = $imageresizer ? 1 : 0;

        if ($enableimage) {
            $imgReplaceCount = 0;
            $s = (string) preg_replace_callback(
                '/\[img\]([^\<\r\n"\']+?)\[\/img\]/i',
                function (array $m) use ($imageresizer, $imageMaxWidth, $imageMaxHeight): string {
                    return \formatImg($m[1], $imageresizer, $imageMaxWidth, $imageMaxHeight);
                },
                $s,
                $imagenum,
                $imgReplaceCount,
            );
            $s = (string) preg_replace_callback(
                '/\[img=([^\<\r\n"\']+?)\]/i',
                function (array $m) use ($imageresizer, $imageMaxWidth, $imageMaxHeight): string {
                    return \formatImg($m[1], $imageresizer, $imageMaxWidth, $imageMaxHeight);
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
                static fn (array $m): string => \formatYoutube($m[4], $m[2] ?: 0, $m[3] ?: 0),
                $s,
            );
        }

        if (str_contains($s, '[video')) {
            $s = (string) preg_replace_callback(
                '/\[video(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|https):\/\/[^\s\'"<>]+)\[\/video\]/i',
                static fn (array $m): string => \formatVideo($m[4], $m[2] ?: 0, $m[3] ?: 0),
                $s,
            );
        }

        if (str_contains($s, '[audio')) {
            $s = (string) preg_replace_callback(
                '/\[audio\]((http|https):\/\/[^\s\'"<>]+)\[\/audio\]/i',
                static fn (array $m): string => \formatAudio($m[1]),
                $s,
            );
        }

        $s = (string) preg_replace_callback(
            '/\[url=([^\[\s]+?)\](.+?)\[\/url\]/i',
            function (array $m) use ($newtab): string {
                return \formatUrl($m[1], $newtab, $m[2], 'faqlink');
            },
            $s,
        );

        $s = (string) preg_replace_callback(
            '/\[url\]([^\[\s]+?)\[\/url\]/i',
            function (array $m) use ($newtab): string {
                return \formatUrl($m[1], $newtab, '', 'faqlink');
            },
            $s,
        );

        $s = (string) preg_replace_callback(
            '/\[left\](.*)\[\/left\]/isU',
            static fn (array $m): string => \formatTextAlign($m[1], 'left'),
            $s,
        );
        $s = (string) preg_replace_callback(
            '/\[center\](.*)\[\/center\]/isU',
            static fn (array $m): string => \formatTextAlign($m[1], 'center'),
            $s,
        );
        $s = (string) preg_replace_callback(
            '/\[right\](.*)\[\/right\]/isU',
            static fn (array $m): string => \formatTextAlign($m[1], 'right'),
            $s,
        );
        $s = (string) preg_replace_callback(
            '/\[hide\](.*)\[\/hide\]/isU',
            static fn (array $m): string => \formatHidden($m[1]),
            $s,
        );

        $s = \format_urls($s, $newtab);

        if (strpos($s, '[quote') !== false && strpos($s, '[/quote]') !== false) {
            $s = \format_quotes($s);
        }

        $s = (string) preg_replace_callback(
            '/\[em([1-9][0-9]*)\]/i',
            static function (array $m): string {
                $smile = \get_smile($m[1]);
                return $smile ? '<img src="'.$smile.'" alt="[em'.$m[1].']" />' : '[em'.$m[1].']';
            },
            $s,
        );

        if (str_contains($s, '[spoiler')) {
            $s = (string) preg_replace_callback(
                '/\[spoiler(=(.*))?\](.*)\[\/spoiler\]/isU',
                function (array $m) use ($lang_functions): string {
                    return \formatSpoiler(
                        $m[3],
                        $m[2],
                        \nexus()->getScript() != 'preview',
                    );
                },
                $s,
            );
        }

        $enableattach_attachment = \get_setting('attachment.enableattach');
        if ($enableattach_attachment === 'yes' && $imagenum != 1) {
            $limit = 20;
            $s = (string) preg_replace_callback(
                '/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/is',
                function (array $m) use ($enableimage, $imageresizer): string {
                    return \print_attachment($m[1], $enableimage ? 1 : 0, $imageresizer ? 1 : 0);
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
}
