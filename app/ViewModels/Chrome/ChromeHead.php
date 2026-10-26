<?php

declare(strict_types=1);

namespace App\ViewModels\Chrome;

use App\Support\Forum;
use App\Support\PageLayoutContext;
use App\Support\SearchBox;
use App\Support\Style;
use Illuminate\Support\Facades\App;

/**
 * `<head>` chrome data (ADR 0018): title assembly, html-lang/theme/fontsize
 * attributes, meta tags, per-variant stylesheet and script lists, and the
 * forum pic folder.
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

        [$headStyles, $headScripts, $picFolder, $packThemeUrl] = self::headAssets($context, $variant, $chrome);

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
            picFolder: $picFolder,
            packThemeUrl: $packThemeUrl,
        );
    }

    /**
     * Stylesheets/scripts that the boxed variant needs on top of the
     * shared chrome assets: the user's theme, font size and forum sprites.
     *
     * @return array{0: list<string>, 1: list<string>, 2: string, 3: ?string}
     */
    private static function headAssets(PageLayoutContext $context, string $variant, ChromeRepositories $chrome): array
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
        $baseStyles = array_merge([
            'styles/sprites.css'.$cssUpdateDate,
            $picFolder.'/forumsprites.css'.$cssUpdateDate,
        ], $iconStyles);

        // Boxed chrome shares the modern cascade order: theme.css rides in
        // the packThemeUrl slot AFTER css/modern.css so the pack palette
        // overrides the base instead of being stomped by it, and nxt.css
        // loads once via head-assets.blade.php (Tailwind utilities layer
        // stays last). Unlike modern pages, boxed markup still needs
        // Classic's theme.css — the pack file carries table.colhead styles.
        $packThemeUrl = $variant === 'boxed' || $cssUri !== 'styles/Classic/'
            ? $cssUri.'theme.css'.$cssUpdateDate
            : null;

        return [
            $baseStyles,
            [],
            $picFolder,
            $packThemeUrl,
        ];
    }
}
