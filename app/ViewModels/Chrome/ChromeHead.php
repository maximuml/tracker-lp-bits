<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Support\Forum;
use App\Support\Html\SafeHtml;
use App\Support\PageLayoutContext;
use App\Support\SearchBox;
use App\Support\Style;
use Illuminate\Support\Facades\App;

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
        public readonly ?string $packThemeUrl,
    ) {}

    public static function load(
        PageLayoutContext $context,
        string $title,
        string $variant,
        string $cspNonce,
        ChromeRepositories $chrome,
    ): self {
        $fullTitle = $title === '' ? $context->siteName : $context->siteName.' :: '.$title;
        if ($context->titleKeywordsTweak !== '') {
            $fullTitle .= ' '.$context->titleKeywordsTweak;
        }
        $fullTitle .= ' - Powered by '.PROJECTNAME;

        [$headStyles, $headScripts, $inlineHeadHtml, $picFolder, $packThemeUrl] = self::headAssets($context, $variant, $cspNonce, $chrome);

        return new self(
            title: $fullTitle,
            locale: str_replace('_', '-', App::getLocale()),
            theme: $context->userTheme(),
            fontSize: $context->userFontSize() ?? 'medium',
            cspNonce: $cspNonce,
            metaKeywords: $context->metaKeywordsTweak,
            metaDescription: $context->metaDescriptionTweak,
            headStyles: $headStyles,
            headScripts: $headScripts,
            inlineHeadHtml: $inlineHeadHtml,
            picFolder: $picFolder,
            packThemeUrl: $packThemeUrl,
        );
    }

    /**
     * Stylesheets/scripts that only the legacy variant needs on top of the
     * shared chrome assets: the user's theme, font size and forum sprites,
     * plus the `addicode` block keyed to the chosen stylesheet.
     *
     * @return array{0: list<string>, 1: list<string>, 2: SafeHtml, 3: string, 4: ?string}
     */
    private static function headAssets(PageLayoutContext $context, string $variant, string $cspNonce, ChromeRepositories $chrome): array
    {
        $picFolder = Forum::picFolder($context->langDir);
        $cssUpdateDate = $context->cssDateTweak !== '' ? '?'.$context->cssDateTweak : '';

        // Icon-pack stylesheets (category sprites) apply to both chrome
        // variants — sprite classes are used by category grids everywhere.
        $iconStyles = [];
        if ($context->user !== null) {
            $requireSearchBoxIds = SearchBox::requiredIds();
            if ($requireSearchBoxIds !== []) {
                foreach ($chrome->searchBox->listIcon($requireSearchBoxIds) as $icon) {
                    $cssfile = trim((string) ($icon['cssfile'] ?? ''), '/');
                    if ($cssfile !== '') {
                        $iconStyles[] = $cssfile.$cssUpdateDate;
                    }
                }
            }
        }

        $cssUri = Style::cssUri($context->cache, $context->userStylesheet(), $context->defaultStylesheet);

        if ($variant !== 'legacy') {
            // Modern chrome only loads Classic's pack files historically;
            // a non-Classic pack still gets its theme.css — after
            // css/modern.css in head-assets.blade.php so its variable
            // overrides win the cascade outright.
            $packThemeUrl = $cssUri === 'styles/Classic/' ? null : $cssUri.'theme.css'.$cssUpdateDate;

            return [
                array_merge(['styles/sprites.css', $picFolder.'/forumsprites.css'.$cssUpdateDate, 'styles/nexus.css'], $iconStyles),
                [],
                SafeHtml::fromTrustedHtml(''),
                $picFolder,
                $packThemeUrl,
            ];
        }

        $headStyles = array_merge([
            'styles/sprites.css'.$cssUpdateDate,
            $picFolder.'/forumsprites.css'.$cssUpdateDate,
            $cssUri.'theme.css'.$cssUpdateDate,
            'styles/nexus.css'.$cssUpdateDate,
        ], $iconStyles);

        // Tailwind utilities layer (converted components are shared between
        // chromes — without it they render unstyled on legacy pages). Last
        // in the cascade; missing on clones that have not run `make css`.
        if (file_exists(public_path('css/nxt.css'))) {
            $headStyles[] = 'css/nxt.css';
        }

        $addiCode = Style::addiCode($context->cache, $context->userStylesheet(), $context->defaultStylesheet);
        if ($cspNonce !== '' && $addiCode !== '') {
            $addiCode = (string) preg_replace('/<style(?![^>]*\snonce=)/i', '<style nonce="'.$cspNonce.'"', $addiCode);
        }

        return [
            $headStyles,
            [],
            SafeHtml::fromTrustedHtml($addiCode),
            $picFolder,
            null,
        ];
    }
}
