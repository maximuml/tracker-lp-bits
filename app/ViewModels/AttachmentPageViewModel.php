<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the standalone attachment manager page (attachment/index.blade.php).
 *
 * Assembled by AttachmentController::renderAttachment(). The page is a
 * bare in-frame document rendered outside the site chrome; the template
 * reads these props directly, so the controller still spreads the VM via
 * ->toArray() into view data (standalone document, not a chrome page).
 */
final class AttachmentPageViewModel extends ViewModel
{
    public function __construct(
        public readonly string $theme,
        public readonly string $fontSize,
        public readonly string $css_uri,
        public readonly SafeHtml $script,
        public readonly bool $enableAttachment,
        public readonly string $callback_func,
        public readonly string $altsize,
        public readonly string $warning,
        public readonly int $count_left,
        public readonly int $count_limit,
        public readonly int $size_limit,
        public readonly string $allowedextsblock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
