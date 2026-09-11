<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Category;
use App\Support\Form;

/**
 * Builds the "add offer" form section.
 */
final class OfferPageAddBuilder
{
    /**
     * @param  array<string, mixed>  $lang
     * @return array<string, mixed>
     */
    public function build(array $lang, mixed $browsecatmode): array
    {
        $typeOptions = '<select name=type>'."\n".'<option value=0>'.(string) ($lang['select_type_select'] ?? '')."</option>\n";
        foreach (Category::listByModeWithContext($browsecatmode) as $row) {
            $rowArr = (array) $row;
            $typeOptions .= '<option value='.(int) $rowArr['id'].'>'.htmlspecialchars((string) $rowArr['name'])."</option>\n";
        }
        $typeOptions .= "</select>\n";

        return [
            'typeOptions' => $typeOptions,
            'bbcodeEditor' => Form::bbcodeEditor('compose', 'body', '', false, 130, true),
        ];
    }
}
