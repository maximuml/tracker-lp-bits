<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;

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

        return "\x08tempCode_$key\x08";
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
    ): SafeHtml {
        if ($text === '') {
            return SafeHtml::fromTrustedHtml('');
        }

        self::resetTempCode();

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

        $s = nl2br($s);

        /** @var array<string, string> $literalMap */
        $literalMap = config('bbcode.literal_map');
        $s = str_replace(
            ['[siteurl]', '[site]', ...array_keys($literalMap)],
            [Url::schemeAndHost(), SiteConfig::current()->basic->siteName(), ...array_values($literalMap)],
            $s,
        );

        /** @var array<string, string> $spanMap */
        $spanMap = config('bbcode.span_map');
        $s = (string) preg_replace(array_keys($spanMap), array_values($spanMap), $s);

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

                return $smile ? trim(view('support._em-img', ['smile' => $smile, 'n' => $m[1]])->render()) : '[em'.$m[1].']';
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

        return SafeHtml::fromTrustedHtml(str_replace("\x08", '', $s));
    }

    private static function resolveTempCodes(string $s): string
    {
        $j = 0;
        while (count(self::$tempCode) > 0 && $j <= 5) {
            foreach (self::$tempCode as $key => $code) {
                $s = str_replace("\x08tempCode_$key\x08", $code, $s, $count);
                if ($count) {
                    unset(self::$tempCode[$key]);
                }
            }
            $j++;
        }

        return $s;
    }
}
