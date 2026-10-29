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

        $key = $this->userDisplayKey();
        if ($key !== null) {
            $items = method_exists($records, 'getCollection') ? $records->getCollection() : $records;
            UserDisplay::preload($items->pluck($key)->all());
        }

        return $records;
    }

    protected function userDisplayKey(): ?string
    {
        return null;
    }
}
