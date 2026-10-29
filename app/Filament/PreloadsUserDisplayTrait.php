<?php

declare(strict_types=1);

namespace App\Filament;

use App\Support\UserDisplay;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

trait PreloadsUserDisplayTrait
{
    /**
     * @return Collection<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        $records = parent::getTableRecords();
        $this->preloadUserDisplayRows($records);

        return $records;
    }

    /**
     * Warm the UserDisplay in-request row cache for every user id that a
     * table column will render via username()/adminUsername() — one batch
     * WHERE IN pair instead of two queries per row.
     *
     * @param  Collection<int, mixed>|Paginator<int, mixed>|CursorPaginator<int, mixed>  $records
     */
    protected function preloadUserDisplayRows(Collection|Paginator|CursorPaginator $records): void
    {
        $ids = [];
        foreach ($this->userDisplayKeys() as $key) {
            $items = method_exists($records, 'getCollection') ? $records->getCollection() : $records;
            $ids = array_merge($ids, $items->pluck($key)->all());
        }
        if ($ids !== []) {
            UserDisplay::preload($ids);
        }
    }

    /**
     * Record attributes holding user ids whose display rows are rendered
     * via UserDisplay::username()/adminUsername() in the table.
     *
     * @return list<string>
     */
    protected function userDisplayKeys(): array
    {
        return [];
    }
}
