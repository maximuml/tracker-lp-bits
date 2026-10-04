<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TorrentCustomField;
use App\Models\TorrentCustomFieldValue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Custom torrent-field metadata + per-torrent values — backs
 * Support\CustomField's upload/edit rendering and save path.
 */
final class CustomFieldRepository
{
    /**
     * Enabled field definitions ordered by priority (desc) — the
     * upload-page render order.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, array<string, mixed>>
     */
    public function listFieldsByIdsOrdered(array $ids): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return TorrentCustomField::query()
            ->whereIn('id', $ids)
            ->orderBy('priority', 'desc')
            ->toBase()
            ->get()
            ->map(fn ($row) => (array) $row);
    }

    /**
     * Field definitions for the save path — SearchBox::custom_fields is a
     * serialized id list, so `find()` here hits the ids directly.
     *
     * @param  array<int, int>|null  $ids
     * @return EloquentCollection<int, TorrentCustomField>
     */
    public function findFieldsByIds(?array $ids): EloquentCollection
    {
        /** @var EloquentCollection<int, TorrentCustomField> */
        return TorrentCustomField::query()->whereIn('id', $ids ?? [])->get();
    }

    /**
     * Per-torrent values joined with their field definitions.
     *
     * @param  array<int, int>  $torrentIds
     * @param  array<int, int>  $fieldIds
     * @return Collection<int, array<string, mixed>>
     */
    public function listValuesForTorrents(array $torrentIds, array $fieldIds): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return TorrentCustomFieldValue::query()
            ->from('torrents_custom_field_values as v')
            ->join('torrents_custom_fields as f', 'v.custom_field_id', '=', 'f.id')
            ->whereIn('v.torrent_id', $torrentIds)
            ->whereIn('f.id', $fieldIds)
            ->orderBy('f.priority', 'desc')
            ->select('f.*', 'v.custom_field_value', 'v.torrent_id')
            ->toBase()
            ->get()
            ->map(fn ($row) => (array) $row);
    }

    public function deleteValuesForTorrent(int $torrentId): int
    {
        return TorrentCustomFieldValue::query()->where('torrent_id', $torrentId)->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertValues(array $rows): bool
    {
        return TorrentCustomFieldValue::query()->insert($rows);
    }
}
