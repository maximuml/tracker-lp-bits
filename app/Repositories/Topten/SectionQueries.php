<?php

declare(strict_types=1);

namespace App\Repositories\Topten;

use Illuminate\Support\Collection;

/**
 * Shared formatting helpers for the top-ten section builders.
 */
abstract class SectionQueries
{
    protected function caption(string $topPrefix, int $limit, string $label, ?string $note = null): string
    {
        $html = $topPrefix.$limit.' '.$label;

        if ($note !== null && $note !== '') {
            $html .= '<span class="small">'.$note.'</span>';
        }

        return $html;
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    protected function toArray(Collection $rows): array
    {
        return array_values($rows->map(fn ($row) => (array) $row)->all());
    }
}
