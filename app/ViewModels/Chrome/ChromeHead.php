<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\PageLayoutContext;
use App\Support\SearchBox;
use App\Support\Style;

/**
 * `<head>` chrome data (ADR 0018): title assembly, html-lang/theme/fontsize
 * attributes, meta tags, per-variant stylesheet and script lists, the
 * legacy `addicode` inline block and the forum pic folder.
 */
final class ChromeHead
{
    /**
     * @param  list<string>  $headStyles
     * @param  list<string>  $headScripts
     */
    private function __construct(
        public readonly string $title,
        public readonly string $locale,
        public readonly string $theme,
        public readonly string $fontSize,
        public readonly string $cspNonce,
        public readonly string $metaKeywords,
        public readonly string $metaDescription,
        public readonly array $headStyles,
        public readonly array $headScripts,
        public readonly SafeHtml $inlineHeadHtml,
        public readonly string $picFolder,
    ) {}

    public static function load(
        PageLayoutContext $context,
        string $title,
        string $variant,
        string $cspNonce,
    ): self {
        $fullTitle = $title === '' ? $context->siteName : $context->siteName.' :: '.$title;
        if ($context->titleKeywordsTweak !== '') {
            $fullTitle .= ' '.$context->titleKeywordsTweak;
        }
        $fullTitle .= ' - Powered by '.PROJECTNAME;

        [$headStyles, $headScripts, $inlineHeadHtml, $picFolder] = self::headAssets($context, $variant, $cspNonce);

        return new self(
            title: $fullTitle,
            locale: str_replace('_', '-', app()->getLocale()),
            theme: $context->userTheme(),
            fontSize: $context->userFontSize() ?? 'medium',
            cspNonce: $cspNonce,
            metaKeywords: $context->metaKeywordsTweak,
            metaDescription: $context->metaDescriptionTweak,
            headStyles: $headStyles,
            headScripts: $headScripts,
            inlineHeadHtml: $inlineHeadHtml,
            picFolder: $picFolder,
        );
    }

    /**
     * Stylesheets/scripts that only the legacy variant needs on top of the
     * shared chrome assets: the user's theme, font size and forum sprites,
     * plus the `addicode` block keyed to the chosen stylesheet.
     *
     * @return array{0: list<string>, 1: list<string>, 2: SafeHtml, 3: string}
     */
    private static function headAssets(PageLayoutContext $context, string $variant, string $cspNonce): array
    {
        $picFolder = Forum::picFolder($context->langDir);
        $cssUpdateDate = $context->cssDateTweak !== '' ? '?'.$context->cssDateTweak : '';

        // Icon-pack stylesheets (category sprites) apply to both chrome
        // variants — sprite classes are used by category grids everywhere.
        $iconStyles = [];
        if ($context->user !== null) {
            $requireSearchBoxIds = SearchBox::requiredIds();
            if ($requireSearchBoxIds !== []) {
                foreach (app(SearchBoxRepositoryInterface::class)->listIcon($requireSearchBoxIds) as $icon) {
                    $cssfile = trim((string) ($icon['cssfile'] ?? ''), '/');
                    if ($cssfile !== '') {
                        $iconStyles[] = $cssfile.$cssUpdateDate;
                    }
                }
            }
        }

        if ($variant !== 'legacy') {
            return [
                array_merge(['styles/sprites.css', 'styles/nexus.css'], $iconStyles),
                [],
                SafeHtml::fromTrustedHtml(''),
                $picFolder,
            ];
        }

        $cssUri = Style::cssUri($context->cache, $context->userStylesheet(), $context->defaultStylesheet);

        $headStyles = array_merge([
            'styles/sprites.css'.$cssUpdateDate,
            $picFolder.'/forumsprites.css'.$cssUpdateDate,
            $cssUri.'theme.css'.$cssUpdateDate,
            'styles/nexus.css'.$cssUpdateDate,
        ], $iconStyles);

        $addiCode = Style::addiCode($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        if ($cspNonce !== '' && $addiCode !== '') {
            $addiCode = (string) preg_replace('/<style(?![^>]*\snonce=)/i', '<style nonce="'.$cspNonce.'"', $addiCode);
        }

        return [
            $headStyles,
            [],
            SafeHtml::fromTrustedHtml($addiCode),
            $picFolder,
        ];
    }
}
