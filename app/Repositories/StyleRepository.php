<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

final class StyleRepository
{
    /** @var array<int, array<string, mixed>>|null */
    private static ?array $rows = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (self::$rows === null) {
            $rows = [];
            foreach (DB::table('stylesheets')->orderBy('id')->get() as $row) {
                $row = (array) $row;
                $rows[(int) $row['id']] = $row;
            }
            self::$rows = $rows;
        }

        return self::$rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function row(int|string $id): ?array
    {
        return $this->all()[(int) $id] ?? null;
    }

    public function uri(int|string $id): ?string
    {
        $row = $this->row($id);

        return $row !== null ? (string) ($row['uri'] ?? '') : null;
    }

    public function highlightColor(int|string $id): ?string
    {
        $row = $this->row($id);

        return $row !== null ? ($row['hltr'] ?? null) : null;
    }

    public function firstId(): ?int
    {
        $rows = $this->all();

        return $rows === [] ? null : (int) array_key_first($rows);
    }
}
