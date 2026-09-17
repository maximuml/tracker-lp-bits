<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\AssetAppender;
use App\Support\Globals;
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
 * PHP call sites that need the markup as a string use ::html(); Blade
 * views use <x-bbcode-editor>.
 */
final class BbcodeEditor extends Component
{
    /** @var list<int> */
    private const QUICK_SMILIES = [1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 13, 16, 17, 19, 20, 21, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 39, 40, 41];

    public readonly string $editId;

    public readonly string $previewId;

    public readonly string $btnEditId;

    public readonly string $btnPreviewId;

    /** @var list<int> */
    public readonly array $quickSmilies;

    public readonly bool $enableAttach;

    public readonly string $attachUrl;

    public function __construct(
        private readonly Globals $globals,
        public readonly string $form = '',
        public readonly string $text = '',
        public readonly string $content = '',
        public readonly bool $withPreview = false,
    ) {
        $this->editId = "$form-$text-edit";
        $this->previewId = "$form-$text-preview";
        $this->btnEditId = "$form-$text-btn-edit";
        $this->btnPreviewId = "$form-$text-btn-preview";
        $this->quickSmilies = self::QUICK_SMILIES;
        $this->enableAttach = $this->globals->get('enableattach_attachment', '') === 'yes';
        $this->attachUrl = Url::schemeAndHost().'/attachment.php';
    }

    public function render(): View
    {
        AssetAppender::js('js/bbcode-editor.js', 'footer', true, 'bbcode-editor');

        return view('components.bbcode-editor');
    }

    /**
     * Render the editor to an HTML string for legacy call sites that
     * concatenate markup into view data.
     *
     * @param  array{form: string, text: string, content?: string, withPreview?: bool}  $props
     */
    public static function html(array $props): string
    {
        $component = self::resolve($props);

        return $component->render()->with($component->data())->render();
    }
}
