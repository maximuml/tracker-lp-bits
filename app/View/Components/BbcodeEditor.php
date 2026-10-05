<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\Url;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Legacy BBCode toolbar/textarea editor.
 *
 * Replaces the ob_start heredoc previously emitted by
 * App\Support\Form::bbcodeEditor(). Behaviour lives in
 * public/js/bbcode-editor.js (queued once per request); the delegated
 * listeners in public/js/common.js dispatch the data-bbcode-action /
 * data-bbcode-alterfont / data-smile attributes rendered here.
 *
 * Blade views use <x-bbcode-editor> (or <livewire:bbcode-editor> when the
 * preview toggle should work server-side).
 */
final class BbcodeEditor extends Component
{
    /** @var list<int> */
    private const QUICK_SMILIES = [1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 13, 16, 17, 19, 20, 21, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 39, 40, 41];

    /** @var list<int> */
    public readonly array $quickSmilies;

    public readonly bool $enableAttach;

    public readonly string $attachUrl;

    public function __construct(
        public readonly string $form = '',
        public readonly string $text = '',
        public readonly string $content = '',
        public readonly bool $invalid = false,
        public readonly string $describedBy = '',
        public readonly string $label = '',
        public readonly string $wireModel = '',
    ) {
        $this->quickSmilies = self::QUICK_SMILIES;
        $this->enableAttach = SiteConfig::current()->attachment->enableAttach();
        $this->attachUrl = Url::schemeAndHost().'/attachment.php';
    }

    public function render(): View
    {
        AssetAppender::js('js/bbcode-editor.js', 'footer', true, 'bbcode-editor');

        return view('components.bbcode-editor');
    }
}
