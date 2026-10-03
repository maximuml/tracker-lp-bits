<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RuleRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Site rules repository: CRUD for the `rules` table behind modrules.
 */
final class RuleRepository implements RuleRepositoryInterface
{
    /** @param  array<string, mixed>  $data */
    public function insert(array $data): void
    {
        DB::table('rules')->insert($data);
    }

    /** @param  array<string, mixed>  $data */
    public function updateById(int $id, array $data): void
    {
        DB::table('rules')->where('id', $id)->update($data);
    }

    public function deleteById(int $id): void
    {
        DB::table('rules')->where('id', $id)->delete();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $row = DB::table('rules')->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    /** @return list<array<string, mixed>> */
    public function listAllWithLang(): array
    {
        return array_values(DB::table('rules')
            ->leftJoin('language', 'rules.lang_id', '=', 'language.id')
            ->orderBy('lang_name')
            ->orderBy('rules.id')
            ->get(['rules.*', 'language.lang_name'])
            ->map(fn ($r): array => (array) $r)
            ->all());
    }
}
