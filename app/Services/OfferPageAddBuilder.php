<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Category;
use App\View\Components\BbcodeEditor;

/**
 * Builds the "add offer" form section.
 */
final class OfferPageAddBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(mixed $browsecatmode): array
    {
        $typeOptions = '<select name=type>'."\n".'<option value=0>'.(string) (__('legacy/offers.select_type_select'))."</option>\n";
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $typeOptions .= '<option value='.(int) $rowArr['id'].'>'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }
        $typeOptions .= "</select>\n";

        return [
            'typeOptions' => $typeOptions,
            'bbcodeEditor' => BbcodeEditor::html(['form' => 'compose', 'text' => 'body', 'withPreview' => true]),
        ];
    }
}
