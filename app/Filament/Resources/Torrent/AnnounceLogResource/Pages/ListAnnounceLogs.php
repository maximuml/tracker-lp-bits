<?php

namespace App\Filament\Resources\Torrent\AnnounceLogResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\Torrent\AnnounceLogResource;
use App\Models\AnnounceLog;
use App\Repositories\AnnounceLogRepository;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class ListAnnounceLogs extends PageListSingle
{
    protected static string $resource = AnnounceLogResource::class;

    /**
     * @return Collection<int, AnnounceLog>|Paginator<int, AnnounceLog>|CursorPaginator<int, AnnounceLog>|LengthAwarePaginator<int, AnnounceLog>
     */
    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        if (config('clickhouse.connection.host') == '') {
            $request = request();
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 10);
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        $filterableColumns = [
            'user_id' => 'is_numeric',
            'torrent_id'=> 'is_numeric',
            'peer_id' => 'is_string',
            'ip' => 'is_string',
            'event' => 'is_string',
        ];
        $sortableColumns = ['timestamp', 'uploaded_total', 'uploaded_increment', 'downloaded_total', 'downloaded_increment', 'left', 'announce_time'];
        $sortableDirections = ['asc', 'desc'];
        $request = request();
//        dd($request->all());
        $filters = [];
        foreach ($request->get('tableFilters', []) as $field => $values) {
            if (!isset($filterableColumns[$field])) {
                continue;
            }
            foreach ($values as $k => $v) {
                if (isset($filterableColumns[$k])) {
                    $filters[$field] = $v;
                }
            }
        }
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 10);
        $sortColumn = null;
        $sortDirection = null;
        $sortColumnFromQuery = $request->get("tableSortColumn");
        $sortDirectionFromQuery = $request->get("tableSortDirection");
        if (in_array($sortColumnFromQuery, $sortableColumns)) {
            $sortColumn = $sortColumnFromQuery;
        }
        if (in_array($sortDirectionFromQuery, $sortableDirections)) {
            $sortDirection = $sortDirectionFromQuery;
        }

        $sorts = [];
        $components = $request->input('components', []);
        if (! is_array($components)) {
            $components = [];
        }
        foreach ($components as $component) {
            if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
                continue;
            }
            $snapshot = json_decode($component['snapshot'], true);
            if (! is_array($snapshot)) {
                continue;
            }
//            do_log("snapshot: " . $component['snapshot']);
            if (isset($snapshot['data']['tableRecordsPerPage'])) {
                $perPage = $snapshot['data']['tableRecordsPerPage'];
            }
            if (isset($snapshot['data']['tableSortColumn']) && in_array($snapshot['data']['tableSortColumn'], $sortableColumns)) {
                $sortColumn = $snapshot['data']['tableSortColumn'];
            }
            if (isset($snapshot['data']['tableSortDirection']) && in_array($snapshot['data']['tableSortDirection'], $sortableDirections)) {
                $sortDirection = $snapshot['data']['tableSortDirection'];
            }
            if ($sortColumn && $sortDirection) {
                $sorts[$sortColumn] = $sortDirection;
            }
            if (isset($snapshot['data']['paginators'])) {
                foreach ($snapshot['data']['paginators'] as $paginator) {
                    if (isset($paginator['page'])) {
                        $page = $paginator['page'];
                    }
                }
            }
            if (isset($snapshot['data']['tableFilters'])) {
//                dd($snapshot['data']['tableFilters']);
                foreach ($snapshot['data']['tableFilters'] as  $filterItems) {
                    foreach ($filterItems as $field => $items) {
                        if (!isset($filterableColumns[$field]) || !is_array($items)) {
                            continue;
                        }
                        foreach ($items as $values) {
                            if (!is_array($values)) {
                                continue;
                            }
                            foreach ($values as $subField => $value) {
                                if ($field == $subField && $value !== null) {
                                    $filters[$field] = $value;
                                }
                            }
                        }
                    }
                }
            }
//            do_log("updates: " . json_encode($component['updates'] ?? []));
            if (isset($component['updates']['tableRecordsPerPage'])) {
                $perPage = $component['updates']['tableRecordsPerPage'];
            }
//            do_log("calls: " . json_encode($component['calls'] ?? []));
            if (isset($component['calls'])) {
                foreach ($component['calls'] as $call) {
                    if ($call['method'] == "gotoPage") {
                        $page = $call['params'][0];
                    }
                    if ($call['method'] == "sortTable") {
                        if (!in_array($call['params'][0], $sortableColumns)) {
                            continue;
                        }
                        $sortColumn = $call['params'][0];
                        if (!isset($sorts[$sortColumn])) {
                            $sortDirection = "asc";
                        } elseif ($sorts[$sortColumn] == "asc") {
                            $sortDirection = "desc";
                        } elseif ($sorts[$sortColumn] == "desc") {
                            $sortDirection = null;
                        }
                    }
                    if ($call['method'] == "resetTableFiltersForm") {
                        $filters = [];
                    }
                }
            }
            foreach ($filterableColumns as $field => $filterFunc) {
                if (isset($component['updates']["tableFilters.$field.$field"])) {
                    $filters[$field] = $component['updates']["tableFilters.$field.$field"];
                }
            }
        }
        foreach ($filters as $field => $value) {
            if (! call_user_func($filterableColumns[$field], $value)) {
                unset($filters[$field]);
            }
        }
        $page = (int) $page;
        $perPage = (int) $perPage;

        $rep = new AnnounceLogRepository();
        $result = $rep->listAll($filters, $page, $perPage, $sortColumn, $sortDirection);
        if (! is_array($result) || ! isset($result['data'], $result['total'])) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        // 转换数据格式以适配 Filament 表格
        $items = [];
        /** @var array<int, array<string, mixed>> $data */
        $data = $result['data'];
        foreach ($data as $announceLog) {
            $model = new AnnounceLog($announceLog);
            $items[] = $model;
        }
        return new LengthAwarePaginator($items, $result['total'], $perPage, $page);
    }

    protected function getHeaderActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function resolveTableRecord(?string $key): ?Model
    {
        if (config('clickhouse.connection.host') == '') {
            return null;
        }
        $rep = new AnnounceLogRepository();
        return $rep->getById($key);
    }
}
