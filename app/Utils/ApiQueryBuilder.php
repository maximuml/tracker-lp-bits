<?php

declare(strict_types=1);

namespace App\Utils;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * GET /api/v1/torrents
 * ?includes=author,comments.user
 * &include_counts=thanks_user,reward_logs
 * &extra_fields=id,title
 * &include_fields[torrent]=thanks_user,reward_logs
 * &filter[status]=published
 * &filter[size][gt]=1024
 * filter_any[source][gt]=1
 * filter_any[codec]=1
 * &sorts=-created_at
 * &page=1
 * &per_page=10
 */
class ApiQueryBuilder
{
    /** @var array<int, string> */
    protected array $allowedIncludes = [];

    /** @var array<int, string> */
    protected array $requestIncludes = [];

    /** @var array<int, string> */
    protected array $allowedFilters = [];

    /** @var array<int, string> */
    protected array $allowedSorts = [];

    /** @var array<string, string> */
    protected array $requestSorts = [];

    /** @var array<int, string> */
    protected array $allowedIncludeCounts = [];

    /** @var array<int, string> */
    protected array $allowedIncludeFields = [];

    /** @var array<int, string> */
    protected array $requestIncludeFields = [];

    /** @var array<string, callable> */
    protected array $customFilterCallbacks = [];

    const PARAM_NAME_INCLUDES = 'includes';

    const PARAM_NAME_INCLUDE_COUNTS = 'include_counts';

    const PARAM_NAME_INCLUDE_FIELDS = 'include_fields';

    const PARAM_NAME_FILTER = 'filter';

    const PARAM_NAME_FILTER_ANY = 'filter_any';

    const PARAM_NAME_SORTS = 'sorts';

    protected Builder $query;

    public function __construct(protected readonly Request $request, Builder $query, protected readonly string $resourceName)
    {
        $this->query = $query;
    }

    public static function for(string $resourceName, Builder $query, ?Request $request = null): self
    {
        return new self($request ?? request(), $query, $resourceName);
    }

    /**
     * @param  array<int, string>  $includes
     */
    public function allowIncludes(array $includes): self
    {
        $this->allowedIncludes = $includes;
        $requestIncludesStr = (string) $this->request->input(self::PARAM_NAME_INCLUDES, '');
        $this->requestIncludes = explode(',', $requestIncludesStr);

        return $this;
    }

    /**
     * @param  array<int, string>  $filters
     */
    public function allowFilters(array $filters): self
    {
        $this->allowedFilters = $filters;

        return $this;
    }

    /**
     * @param  array<int, string>  $sorts
     */
    public function allowSorts(array $sorts): self
    {
        $this->allowedSorts = $sorts;
        $requestSortsStr = (string) $this->request->input(self::PARAM_NAME_SORTS, '');
        foreach (explode(',', $requestSortsStr) as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $field = ltrim($sort, '-');
            $this->requestSorts[$field] = $direction;
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $counts
     */
    public function allowIncludeCounts(array $counts): self
    {
        $this->allowedIncludeCounts = $counts;

        return $this;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function allowIncludeFields(array $fields): self
    {
        $this->allowedIncludeFields = $fields;
        $requestIncludeFieldsStr = (string) $this->request->input(sprintf('%s.%s', self::PARAM_NAME_INCLUDE_FIELDS, $this->resourceName), '');
        $this->requestIncludeFields = explode(',', $requestIncludeFieldsStr);

        return $this;
    }

    public function build(): Builder
    {
        $this->applyIncludes();
        $this->applyFilters();
        $this->applyOrFilters();
        $this->applyCustomFilters();
        $this->applySorts();
        $this->applyIncludeCounts();

        return $this->query;
    }

    protected function applyIncludes(): void
    {
        $includes = explode(',', $this->request->query(self::PARAM_NAME_INCLUDES, ''));
        $valid = array_intersect($this->allowedIncludes, $includes);
        $this->query->with($valid);
    }

    protected function applyIncludeCounts(): void
    {
        $includeCounts = explode(',', $this->request->query(self::PARAM_NAME_INCLUDE_COUNTS, ''));
        $valid = array_intersect($this->allowedIncludeCounts, $includeCounts);
        //        do_log(sprintf(
        //            "includeCounts: %s, allow: %s, valid: %s",
        //            json_encode($includeCounts), json_encode($this->allowedIncludeCounts), json_encode($valid)
        //        ));
        $this->query->withCount($valid);
    }

    protected function applyFilters(): void
    {
        $filters = $this->request->input(self::PARAM_NAME_FILTER, []);
        if (! is_array($filters)) {
            return;
        }

        foreach ($filters as $field => $value) {
            $field = (string) $field;
            if (! in_array($field, $this->allowedFilters)) {
                continue;
            }
            if (isset($this->customFilterCallbacks[$field])) {
                continue;
            }

            // 如果是复杂条件
            if (is_array($value)) {
                foreach ($value as $operator => $val) {
                    $this->applyFilterOperator($field, (string) $operator, $val);
                }
            } else {
                // 简单形式，默认等于
                $this->query->where($field, '=', $value);
            }
        }
    }

    protected function applyFilterOperator(string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            'eq' => $this->query->where($field, '=', $value),
            'gt' => $this->query->where($field, '>', $value),
            'lt' => $this->query->where($field, '<', $value),
            'gte' => $this->query->where($field, '>=', $value),
            'lte' => $this->query->where($field, '<=', $value),
            'like' => $this->query->where($field, 'like', $value),
            'in' => $this->query->whereIn($field, is_array($value) ? $value : explode(',', $value)),
            default => null
        };
    }

    protected function applyOrFilters(): void
    {
        $filters = $this->request->input(self::PARAM_NAME_FILTER_ANY, []);

        if (! is_array($filters) || empty($filters)) {
            return;
        }

        $this->query->where(function ($q) use ($filters) {
            foreach ($filters as $field => $value) {
                $field = (string) $field;
                if (! in_array($field, $this->allowedFilters)) {
                    continue;
                }
                if (isset($this->customFilterCallbacks[$field])) {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $operator => $val) {
                        $this->applyFilterAnyOperator($q, $field, (string) $operator, $val);
                    }
                } else {
                    $q->orWhere($field, '=', $value);
                }
            }
        });
    }

    protected function applyFilterAnyOperator(Builder $query, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            'eq' => $query->orWhere($field, '=', $value),
            'gt' => $query->orWhere($field, '>', $value),
            'lt' => $query->orWhere($field, '<', $value),
            'gte' => $query->orWhere($field, '>=', $value),
            'lte' => $query->orWhere($field, '<=', $value),
            'like' => $query->orWhere($field, 'like', $value),
            'in' => $query->orWhereIn($field, is_array($value) ? $value : explode(',', $value)),
            default => null
        };
    }

    public function registerCustomFilter(string $field, callable $callback): self
    {
        $this->customFilterCallbacks[$field] = $callback;

        return $this;
    }

    protected function applyCustomFilters(): void
    {
        foreach ($this->customFilterCallbacks as $field => $callback) {
            call_user_func($callback, $this->query, $this->request);
        }
    }

    protected function applySorts(): void
    {
        $sorts = explode(',', $this->request->query(self::PARAM_NAME_SORTS, ''));
        foreach ($sorts as $sort) {
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $field = ltrim($sort, '-');
            if (in_array($field, $this->allowedSorts)) {
                $this->query->orderBy($field, $direction);
            }
        }
    }

    public function hasIncludeField(?string $field = null): bool
    {
        return $this->hasBoth($this->allowedIncludeFields, $this->requestIncludeFields, $field);
    }

    public function hasInclude(?string $name = null): bool
    {
        return $this->hasBoth($this->allowedIncludes, $this->requestIncludes, $name);
    }

    public function hasSort(string $field = ''): bool
    {
        return $this->hasBoth($this->allowedSorts, array_keys($this->requestSorts), $field);
    }

    /**
     * @param  array<int, string>  $dataA
     * @param  array<int, string>  $dataB
     */
    private function hasBoth(array $dataA, array $dataB, mixed $oneKey = null): bool
    {
        $result = array_intersect($dataA, $dataB);
        if (empty($oneKey)) {
            return ! empty($result);
        }

        return in_array($oneKey, $result);
    }
}
