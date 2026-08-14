<?php
namespace App\Repositories;

use App\Auth\Permission;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Support\Json;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Nexus\Database\NexusDB;

class TagRepository extends BaseRepository
{
    /** @var  mixed */
    private static $orderByFieldIdString;

    /** @var \Illuminate\Database\Eloquent\Collection<int, Tag>|null */
    private static $allTags;

    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    public function getList(array $params)
    {
        $query = $this->createBasicQuery();
        return $query->paginate();
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return  mixed
     */
    public function store(array $params)
    {
        $model = Tag::query()->create($params);
        return $model;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @param  mixed  $id
     * @return  mixed
     */
    public function update(array $params, $id)
    {
        $model = Tag::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function getDetail($id)
    {
        $model = Tag::query()->findOrFail($id);
        return $model;
    }

    /**
     * @param  mixed  $id
     * @return  mixed
     */
    public function delete($id)
    {
        $model = Tag::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }

    /** @return  mixed */
    public static function createBasicQuery()
    {
        return Tag::query()->orderBy('priority', 'desc')->orderBy('id', 'desc');
    }

    /**
     * @param  int  $searchBoxId
     * @param  array<int|string, mixed>  $checked
     * @param  mixed  $ignorePermission
     */
    public function renderCheckbox(int $searchBoxId, array $checked = [], $ignorePermission = false): string
    {
        $html = '';
        $results = $this->listAll($searchBoxId);
        if (!$ignorePermission && !Permission::canSetTorrentSpecialTag()) {
            $specialTags = Tag::listSpecial();
            $results = $results->filter(fn ($item) => !in_array($item->id, $specialTags));
        }
        foreach ($results as $value) {
            $html .= sprintf(
                '<label><input type="checkbox" name="tags[%s][]" value="%s"%s />%s</label>',
                $searchBoxId, $value->id, in_array($value->id, $checked) ? ' checked' : '', $value->name
            );
        }
        return $html;
    }

    /**
     * @param  int  $searchBoxId
     * @param  array<int|string, mixed>  $renderIdArr
     * @param  mixed  $withFilterLink
     */
    public function renderSpan(int $searchBoxId, array $renderIdArr = [], $withFilterLink = false): string
    {
        $html = '';
        foreach ($this->listAll($searchBoxId) as $value) {
            if (in_array($value->id, $renderIdArr) || (isset($renderIdArr[0]) && $renderIdArr[0] == '*')) {
                $tagId = $value->id;
                $item = sprintf(
                    "<span style=\"background-color:%s;color:%s;border-radius:%s;font-size:%s;margin:%s;padding:%s\" title=\"%s\">%s</span>",
                    $value->color, $value->font_color, $value->border_radius, $value->font_size, $value->margin, $value->padding, $value->description, $value->name
                );
                if ($withFilterLink) {
                    $html .= sprintf('<a href="?tag_id=%s">%s</a>', $tagId, $item);
                } else {
                    $html .= $item;
                }
            }
        }
        return $html;
    }

    /** @return  mixed */
    public function migrateTorrentTag()
    {
        $page = 1;
        $size = 1000;
        $baseQuery = Torrent::query()->where('tags', '>', 0);
        \App\Support\Logger::writeWithContext((string) ("torrent to migrate hr counts: " . (clone $baseQuery)->count()), (string) 'info', (bool) false);
        $dateTimeStringNow = date('Y-m-d H:i:s');
        $tags = [];
        $priority = count(Tag::DEFAULTS);
        foreach (Tag::DEFAULTS as $value) {
            $attributes = [
                'name' => $value['name'],
            ];
            $values = [
                'priority' => $priority,
                'color' => $value['color'],
                'created_at' => $dateTimeStringNow,
                'updated_at' => $dateTimeStringNow,
            ];
            $tags[] = Tag::query()->firstOrCreate($attributes, $values);
            $priority--;
        }
        \App\Support\Logger::writeWithContext((string) "insert default tags done!", (string) 'info', (bool) false);

        $rows = [];
        while (true) {
            $logPrefix = "page: $page, size: $size";
            $results = (clone $baseQuery)->forPage($page, $size)->get();
            if ($results->isEmpty()) {
                \App\Support\Logger::writeWithContext((string) "{$logPrefix}, no more data...", (string) 'info', (bool) false);
                break;
            }
            foreach ($results as $torrent) {
                foreach ($tags as $key => $tag) {
                    $currentValue = pow(2, $key);
                    if ($currentValue & $torrent->getAttributes()['tags']) {
                        //this torrent has this tag
                        $rows[] = [
                            'torrent_id' => (int) $torrent->id,
                            'tag_id' => (int) $tag->id,
                            'created_at' => $dateTimeStringNow,
                            'updated_at' => $dateTimeStringNow,
                        ];
                    }
                }
            }
            $page++;
        }
        if (!empty($rows)) {
            NexusDB::table('torrent_tags')->upsert($rows, ['torrent_id', 'tag_id'], ['updated_at']);
        }
        \App\Support\Logger::writeWithContext((string) "[MIGRATE_TORRENT_TAG] done!", (string) 'info', (bool) false);
        return count($rows);
    }

    public static function getOrderByFieldIdString(): string
    {
        if (is_null(self::$orderByFieldIdString)) {
            $results = self::createBasicQuery()->get(['id']);
            self::$orderByFieldIdString = $results->isEmpty() ? '0' : $results->implode('id', ',');
        }
        return self::$orderByFieldIdString;
    }

    /**
     * Persist tag assignments for a torrent.
     *
     * Mirrors the legacy {@see \App\Support\TorrentTags::insert()}.
     *
     * @param  array<int, int>  $tagIdArr
     */
    public function syncTorrentTags(int|string $torrentId, array $tagIdArr, bool $sync = false): void
    {
        $specialTags = Tag::listSpecial();
        $canSetSpecialTag = Permission::canSetTorrentSpecialTag();
        $dateTimeStringNow = date('Y-m-d H:i:s');

        if ($sync) {
            $delQuery = TorrentTag::query()->where('torrent_id', $torrentId);
            if (! $canSetSpecialTag) {
                $delQuery->whereNotIn('tag_id', $specialTags);
            }
            $delQuery->delete();
        }

        if (empty($tagIdArr)) {
            return;
        }

        $records = [];
        foreach ($tagIdArr as $tagId) {
            if (in_array($tagId, $specialTags) && ! $canSetSpecialTag) {
                Logger::writeWithContext("special tag: $tagId, and user no permission");
                continue;
            }
            if (! isset($records[$tagId])) {
                $records[$tagId] = [
                    'torrent_id' => $torrentId,
                    'tag_id' => $tagId,
                    'created_at' => $dateTimeStringNow,
                    'updated_at' => $dateTimeStringNow,
                ];
            }
        }

        if (empty($records)) {
            return;
        }

        Logger::writeWithContext("[INSERT_TAGS], torrent: $torrentId with tags: " . Json::encode($tagIdArr));
        TorrentTag::query()->insert(array_values($records));
    }

    /**
     * @param  int  $searchBoxId
     * @return  \Illuminate\Database\Eloquent\Collection<int, Tag>
     */
    public static function listAll(int $searchBoxId = 0): EloquentCollection
    {
        if (empty(self::$allTags)) {
            self::$allTags = self::createBasicQuery()->get();
        }
        if ($searchBoxId > 0) {
            return self::$allTags->filter(fn (Tag $d) => in_array($d->mode, [0, $searchBoxId]));
        }
        return self::$allTags;
    }


    /**
     * @param  int  $searchBoxId
     * @param  mixed  $name
     * @param  mixed  $value
     */
    public function buildSelect(int $searchBoxId, $name, $value): string
    {
        $list = $this->listAll($searchBoxId);
        $select = sprintf('<select name="%s"><option value="">%s</option>', $name, \App\Support\Locale::trans('nexus.select_one_please', [], null));
        foreach ($list as $item) {
            $selected = '';
            if ($item->id == $value) {
                $selected = ' selected';
            }
            $select .= sprintf('<option value="%s"%s>%s</option>', $item->id, $selected, $item->name);
        }
        $select .= '</select>';
        return $select;
    }


}
