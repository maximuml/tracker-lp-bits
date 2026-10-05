<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * BBCode editor with server-side preview — replaces the
 * preview.php AJAX fragment behind the compose preview/edit toggle:
 * preview() renders formatComment($body) server-side, the textarea
 * keeps its form name so the enclosing POST form is unchanged.
 */
final class BbcodeEditor extends Component
{
    public string $form = '';

    public string $text = 'body';

    public string $body = '';

    public bool $previewMode = false;

    public bool $invalid = false;

    public string $describedBy = '';

    public string $label = '';

    public function mount(
        string $form = '',
        string $text = 'body',
        string $content = '',
        bool $invalid = false,
        string $describedBy = '',
        string $label = '',
    ): void {
        $this->form = $form;
        $this->text = $text;
        $this->body = $content;
        $this->invalid = $invalid;
        $this->describedBy = $describedBy;
        $this->label = $label;
    }

    public function preview(): void
    {
        $this->previewMode = true;
    }

    public function unpreview(): void
    {
        $this->previewMode = false;
    }

    #[On('setlist-append')]
    public function appendSetlist(string $form, string $text, string $content): void
    {
        if ($form !== $this->form || $text !== $this->text) {
            return;
        }
        $this->body = trim($this->body) === '' ? $content : $this->body."\n\n".$content;
        $this->previewMode = false;
    }

    public function render(): View
    {
        return view('livewire.bbcode-editor');
    }
}
