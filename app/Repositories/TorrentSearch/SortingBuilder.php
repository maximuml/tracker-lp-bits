<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

/**
 * Build the sort order and pager link from the sort/type search params.
 *
 * Extracted from QueryBuilder to keep both classes under the 400-line
 * ratchet.
 */
final class SortingBuilder
{
    /**
     * Build the sort order and pager link from the sort/type search params.
     *
     * @param  array<string, mixed>  $searchParams
     * @return array{column: string, ascdesc: string, linkascdesc: string, orderBy: array<int, array{0: string, 1: string}>, pagerlink: string}
     */
    public function build(array $searchParams): array
    {
        $column = '';
        $ascdesc = '';
        $linkascdesc = '';
        if (isset($searchParams['sort']) && $searchParams['sort'] && isset($searchParams['type']) && $searchParams['type']) {

            switch ($searchParams['sort']) {
                case '1': $column = 'name';
                    break;
                case '2': $column = 'numfiles';
                    break;
                case '3': $column = 'comments';
                    break;
                case '4': $column = 'added';
                    break;
                case '5': $column = 'size';
                    break;
                case '6': $column = 'times_completed';
                    break;
                case '7': $column = 'seeders';
                    break;
                case '8': $column = 'leechers';
                    break;
                case '9': $column = 'owner';
                    break;
                default: $column = 'id';
                    break;
            }

            switch ($searchParams['type']) {
                case 'asc': $ascdesc = 'ASC';
                    $linkascdesc = 'asc';
                    break;
                case 'desc': $ascdesc = 'DESC';
                    $linkascdesc = 'desc';
                    break;
                default: $ascdesc = 'DESC';
                    $linkascdesc = 'desc';
                    break;
            }

            if ($column == 'owner') {
                $orderBy = [
                    ['pos_state', 'desc'],
                    ['torrents.anonymous', 'asc'],
                    ['users.username', $ascdesc],
                ];
            } else {
                $orderBy = [
                    ['pos_state', 'desc'],
                    ['torrents.'.$column, $ascdesc],
                ];
            }

            $pagerlink = 'sort='.intval($searchParams['sort']).'&type='.$linkascdesc.'&';

        } else {

            $orderBy = [
                ['pos_state', 'desc'],
                ['torrents.id', 'desc'],
            ];
            $pagerlink = '';

        }

        return [
            'column' => $column,
            'ascdesc' => $ascdesc,
            'linkascdesc' => $linkascdesc,
            'orderBy' => $orderBy,
            'pagerlink' => $pagerlink,
        ];
    }
}
